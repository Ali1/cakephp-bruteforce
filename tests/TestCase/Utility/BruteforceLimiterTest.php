<?php
declare(strict_types=1);

namespace TestCase\Utility;

use Ali1\BruteForceShield\Configuration as LegacyConfiguration;
use Bruteforce\Configuration;
use Bruteforce\Exception\TooManyAttemptsException;
use Bruteforce\Utility\BruteforceLimiter;
use Cake\Cache\Cache;
use Cake\TestSuite\TestCase;
use Cake\Utility\Security;

class BruteforceLimiterTest extends TestCase
{
    private BruteforceLimiter $limiter;

    public function setUp(): void
    {
        parent::setUp();
        $this->limiter = new BruteforceLimiter();
        Cache::clear('default');
    }

    public function testStoredChallengeUsesKeyedHmacAndNeverPlaintext(): void
    {
        $this->limiter->validate(
            'hmac-test',
            ['username' => 'admin@example.test', 'password' => 'correct horse battery staple'],
            '192.0.2.10',
            ['globalTotalLimit' => null],
        );

        $history = Cache::read('BruteforceData.192.0.2.10.hmac-test');
        $stored = $history['attempts'][0]['challenge'];

        $this->assertSame(
            hash_hmac('sha256', 'correct horse battery staple', Security::getSalt()),
            $stored['password'],
        );
        $this->assertNotSame('correct horse battery staple', $stored['password']);
        $this->assertNotSame(hash('sha256', 'correct horse battery staple'), $stored['password']);
        $this->assertStringNotContainsString('admin@example.test', serialize($history));
    }

    public function testRepeatedChallengeStillDoesNotConsumeAnotherAttempt(): void
    {
        $config = ['totalLimit' => 1, 'globalTotalLimit' => null];
        $challenge = ['username' => 'Ali', 'password' => 'same-password'];

        $this->assertTrue($this->limiter->validate('duplicate-test', $challenge, '192.0.2.11', $config));
        $this->assertTrue($this->limiter->validate('duplicate-test', $challenge, '192.0.2.11', $config));

        $history = Cache::read('BruteforceData.192.0.2.11.duplicate-test');
        $this->assertCount(1, $history['attempts']);
    }

    public function testDefaultGlobalBudgetStopsRotatingAddresses(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->assertTrue($this->limiter->validate(
                'global-default-test',
                ['token' => 'token-' . $i],
                '198.51.100.' . (($i % 250) + 1),
                ['totalLimit' => 1000],
            ));
        }

        $this->expectException(TooManyAttemptsException::class);
        $this->limiter->validate(
            'global-default-test',
            ['token' => 'blocked-token'],
            '203.0.113.1',
            ['totalLimit' => 1000],
        );
    }

    public function testLegacyConfigurationNameRemainsSourceCompatible(): void
    {
        $configuration = (new LegacyConfiguration())
            ->setTotalAttemptsLimit(10)
            ->setStricterLimitOnKey('username', 5)
            ->addUnencryptedKey('username');

        $this->assertInstanceOf(Configuration::class, $configuration);
        $this->assertSame(['username'], $configuration->getUnencryptedKeyNames());
        $this->assertTrue($configuration->isKeyEncrypted('username'));
    }

    public function testCacheReadFailureBlocksInsteadOfFailingOpen(): void
    {
        $this->expectException(TooManyAttemptsException::class);

        $this->limiter->validate(
            'broken-cache-test',
            ['password' => 'never-processed'],
            '192.0.2.12',
            ['cache' => 'missing-cache-configuration', 'globalTotalLimit' => null],
        );
    }

    public function testBlockedExceptionCarriesHttp429WithoutNewerCakephpClass(): void
    {
        $exception = new TooManyAttemptsException();

        $this->assertSame(429, $exception->getCode());
    }

    public function testParallelWorkersCannotPassTheConfiguredLimit(): void
    {
        $name = 'concurrency-' . bin2hex(random_bytes(8));
        $processes = [];

        for ($i = 0; $i < 12; $i++) {
            $pipes = [];
            $process = proc_open(
                [PHP_BINARY, TESTS . 'concurrency-worker.php', $name, 'password-' . $i],
                [
                    ['pipe', 'r'],
                    ['pipe', 'w'],
                    ['pipe', 'w'],
                ],
                $pipes,
            );
            $this->assertIsResource($process);
            fclose($pipes[0]);
            $processes[] = [$process, $pipes];
        }

        $results = [];
        foreach ($processes as [$process, $pipes]) {
            $results[] = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), $errors);
        }

        $this->assertSame(3, count(array_filter($results, static fn(string $result): bool => $result === 'allowed')));
        $history = Cache::read('BruteforceData.192.0.2.99.' . $name);
        $this->assertCount(3, $history['attempts']);
    }

    public function testRotatingAddressesUseABoundedLockPool(): void
    {
        $cacheNamespace = substr(hash('sha256', 'default'), 0, 8);
        $pattern = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'cakephp-bruteforce-' . $cacheNamespace . '-??.lock';

        for ($i = 0; $i < 300; $i++) {
            $this->limiter->validate(
                'bounded-lock-test-' . $i,
                ['password' => 'password-' . $i],
                '198.51.100.' . ($i % 255),
                ['globalTotalLimit' => null],
            );
        }

        $lockFiles = glob($pattern);
        $this->assertIsArray($lockFiles);
        $this->assertNotEmpty($lockFiles);
        $this->assertLessThanOrEqual(256, count($lockFiles));
    }
}
