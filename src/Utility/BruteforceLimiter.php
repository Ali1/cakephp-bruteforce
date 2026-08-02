<?php
declare(strict_types=1);

namespace Bruteforce\Utility;

use Bruteforce\Exception\TooManyAttemptsException;
use Cake\Cache\Cache;
use Cake\Log\Log;
use Cake\Utility\Security;
use Throwable;

class BruteforceLimiter
{
    /**
     * Record an attempt and throw once the configured limits are exceeded.
     *
     * @param string $name The name of the guarded action.
     * @param array<array-key, mixed> $data The submitted data forming the challenge.
     * @param string $clientIp The client IP the attempt came from.
     * @param array<string, mixed> $config Limiter config, merged over the defaults.
     * @return bool
     * @throws \Bruteforce\Exception\TooManyAttemptsException When a limit is exceeded.
     */
    public function validate(string $name, array $data, string $clientIp, array $config = []): bool
    {
        $config += [
            'timeWindow' => 300,
            'totalLimit' => 8,
            'stricterKey' => null,
            'stricterLimit' => null,
            'cache' => 'default',
            'globalTotalLimit' => 100,
            'globalStricterLimit' => null,
            'globalTimeWindow' => null,
            'skipGlobal' => false,
            'challengeKeys' => null,
            'caseInsensitiveKeys' => [],
        ];

        $challenge = $this->normaliseChallenge(
            $data,
            $config['challengeKeys'] === null ? null : (array)$config['challengeKeys'],
            (array)$config['caseInsensitiveKeys'],
        );
        if ($challenge === []) {
            return true;
        }

        $scopeConfigs = [[
            'config' => $config,
            'cacheKey' => $this->cacheKey($name, $clientIp),
            'scope' => 'ip',
        ]];
        if (!$config['skipGlobal'] && $config['globalTotalLimit'] !== null) {
            $globalConfig = $config;
            $globalConfig['totalLimit'] = (int)$config['globalTotalLimit'];
            $globalConfig['stricterLimit'] = $config['globalStricterLimit'] ?? $config['stricterLimit'];
            $globalConfig['timeWindow'] = $config['globalTimeWindow'] ?? $config['timeWindow'];
            $scopeConfigs[] = [
                'config' => $globalConfig,
                'cacheKey' => $this->globalCacheKey($name),
                'scope' => 'global',
            ];
        }

        $cacheKeys = array_column($scopeConfigs, 'cacheKey');
        $this->withLocks($cacheKeys, (string)$config['cache'], function () use (
            $challenge,
            $clientIp,
            $config,
            $name,
            $scopeConfigs,
        ): void {
            $scopes = [];
            foreach ($scopeConfigs as $scopeConfig) {
                $scopes[] = $this->checkScope(
                    $challenge,
                    $scopeConfig['config'],
                    $scopeConfig['cacheKey'],
                    $scopeConfig['scope'],
                );
            }

            foreach ($scopes as $scope) {
                if ($scope['blocked']) {
                    Log::alert('Bruteforce blocked', [
                        'ip' => $clientIp,
                        'name' => $name,
                        'scope' => $scope['scope'],
                        'fields' => array_keys($challenge),
                        'attempts' => count($scope['history']['attempts']),
                    ]);

                    throw new TooManyAttemptsException();
                }
            }

            $storedChallenge = $this->hashChallenge($challenge);
            foreach ($scopes as $scope) {
                if ($scope['duplicate']) {
                    continue;
                }
                $history = $scope['history'];
                $history['attempts'][] = [
                    'challenge' => $storedChallenge,
                    'time' => time(),
                ];
                $this->writeHistory($scope['cacheKey'], $history, (string)$config['cache']);
            }
        });

        return true;
    }

    /**
     * Evaluate one scope (per-IP or global) against its stored attempt history.
     *
     * @param array<string, string> $challenge The hashed challenge for this attempt.
     * @param array<string, mixed> $config Limiter config for this scope.
     * @param string $cacheKey The cache key holding this scope's history.
     * @param string $scope The scope name, for logging.
     * @return array<string, mixed>
     */
    private function checkScope(array $challenge, array $config, string $cacheKey, string $scope): array
    {
        try {
            $history = Cache::read($cacheKey, $config['cache']);
        } catch (Throwable $exception) {
            $this->storageFailure('read', $cacheKey, (string)$config['cache'], $exception);
        }
        $history = is_array($history) ? $history : ['attempts' => []];
        $oldestAllowed = time() - (int)$config['timeWindow'];
        $history['attempts'] = array_values(array_filter(
            (array)$history['attempts'],
            static fn(array $attempt): bool => (int)($attempt['time'] ?? 0) > $oldestAllowed,
        ));

        $sameStrictKeyAttempts = 0;
        foreach ($history['attempts'] as $attempt) {
            $oldChallenge = (array)($attempt['challenge'] ?? []);
            if ($this->matches($challenge, $oldChallenge)) {
                return [
                    'blocked' => false,
                    'cacheKey' => $cacheKey,
                    'duplicate' => true,
                    'history' => $history,
                    'scope' => $scope,
                ];
            }

            if (
                $config['stricterKey']
                && isset($challenge[$config['stricterKey']], $oldChallenge[$config['stricterKey']])
                && $this->matchesValue(
                    (string)$challenge[$config['stricterKey']],
                    (string)$oldChallenge[$config['stricterKey']],
                )
            ) {
                $sameStrictKeyAttempts++;
            }
        }

        $blocked = count($history['attempts']) >= (int)$config['totalLimit']
            || (
                $config['stricterKey']
                && $config['stricterLimit'] !== null
                && $sameStrictKeyAttempts >= (int)$config['stricterLimit']
            );

        return [
            'blocked' => $blocked,
            'cacheKey' => $cacheKey,
            'duplicate' => false,
            'history' => $history,
            'scope' => $scope,
        ];
    }

    /**
     * Reduce submitted data to a sorted map of normalized, non-empty scalar values.
     *
     * @param array<array-key, mixed> $data The submitted data.
     * @param array<string>|null $challengeKeys Keys to include, or null for all.
     * @param array<string> $caseInsensitiveKeys Keys to lowercase before hashing.
     * @return array<string, string> Plaintext values held only for this request.
     */
    private function normaliseChallenge(array $data, ?array $challengeKeys, array $caseInsensitiveKeys): array
    {
        $challenge = [];
        foreach ($data as $key => $value) {
            if (is_int($key)) {
                continue;
            }
            $key = (string)$key;
            if ($challengeKeys !== null && !in_array($key, $challengeKeys, true)) {
                continue;
            }
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $value = trim((string)$value);
            if ($value === '') {
                continue;
            }

            if (in_array($key, $caseInsensitiveKeys, true)) {
                $value = strtolower($value);
            }

            $challenge[$key] = $value;
        }

        ksort($challenge);

        return $challenge;
    }

    /**
     * Whether two challenges are the same attempt repeated.
     *
     * @param array<string, string> $challenge The current challenge.
     * @param array<string, string> $oldChallenge A previously stored challenge.
     * @return bool
     */
    private function matches(array $challenge, array $oldChallenge): bool
    {
        if (array_keys($challenge) !== array_keys($oldChallenge)) {
            return false;
        }

        foreach ($challenge as $key => $value) {
            if (!isset($oldChallenge[$key]) || !$this->matchesValue($value, (string)$oldChallenge[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Hash a challenge with a server-side secret before it reaches the cache.
     *
     * @param array<string, string> $challenge Normalized plaintext challenge.
     * @return array<string, string>
     */
    private function hashChallenge(array $challenge): array
    {
        $hashKey = $this->hashKey();

        foreach ($challenge as $key => $value) {
            $challenge[$key] = hash_hmac('sha256', $value, $hashKey);
        }

        return $challenge;
    }

    /**
     * Compare against current HMACs and cache entries from earlier plugin versions.
     */
    private function matchesValue(string $value, string $storedValue): bool
    {
        $hashKey = $this->hashKey();

        $hmac = hash_hmac('sha256', $value, $hashKey);
        if (hash_equals($hmac, $storedValue)) {
            return true;
        }

        // Compatibility for the unsalted SHA-256 cache entries written by 6.0.x.
        return preg_match('/^[a-f0-9]{64}$/D', $storedValue) === 1
            && hash_equals(hash('sha256', $value), $storedValue);
    }

    /**
     * Serialize the full read/check/write transaction for each scope on this host.
     *
     * @param array<string> $cacheKeys
     * @param callable(): void $callback
     */
    private function withLocks(array $cacheKeys, string $cache, callable $callback): void
    {
        sort($cacheKeys, SORT_STRING);
        $handles = [];

        try {
            foreach ($cacheKeys as $cacheKey) {
                $cacheNamespace = substr(hash('sha256', $cache), 0, 8);
                $lockStripe = substr(hash('sha256', $cacheKey), 0, 2);
                $lockPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                    . 'cakephp-bruteforce-' . $cacheNamespace . '-' . $lockStripe . '.lock';
                $handle = fopen($lockPath, 'c+b');
                if ($handle === false || !flock($handle, LOCK_EX)) {
                    if (is_resource($handle)) {
                        fclose($handle);
                    }
                    $this->storageFailure('lock', $cacheKey, $cache);
                }
                $handles[] = $handle;
            }

            $callback();
        } finally {
            foreach (array_reverse($handles) as $handle) {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        }
    }

    /**
     * Return CakePHP's active application salt after bootstrap has consumed its config value.
     */
    private function hashKey(): string
    {
        try {
            $hashKey = Security::getSalt();
        } catch (Throwable $exception) {
            $this->storageFailure('hash', '[challenge]', '[configuration]', $exception);
        }

        if ($hashKey === '') {
            $this->storageFailure('hash', '[challenge]', '[configuration]');
        }

        return $hashKey;
    }

    /**
     * @param array<string, mixed> $history
     */
    private function writeHistory(string $cacheKey, array $history, string $cache): void
    {
        try {
            $written = Cache::write($cacheKey, $history, $cache);
        } catch (Throwable $exception) {
            $this->storageFailure('write', $cacheKey, $cache, $exception);
        }

        if (!$written) {
            $this->storageFailure('write', $cacheKey, $cache);
        }
    }

    /**
     * Block the request after logging a limiter infrastructure failure.
     */
    private function storageFailure(
        string $operation,
        string $cacheKey,
        string $cache,
        ?Throwable $exception = null,
    ): never {
        Log::critical('Bruteforce protection storage failure; request blocked', [
            'operation' => $operation,
            'cache' => $cache,
            'cacheKey' => $cacheKey,
            'error' => $exception ? $exception::class : null,
        ]);

        throw new TooManyAttemptsException();
    }

    /**
     * The per-IP cache key for a guarded action.
     *
     * @param string $name The name of the guarded action.
     * @param string $clientIp The client IP.
     * @return string
     */
    private function cacheKey(string $name, string $clientIp): string
    {
        return 'BruteforceData.' . str_replace([':', '\\', '/', ' '], '.', $clientIp) . '.' . $name;
    }

    /**
     * The cross-IP cache key for a guarded action.
     *
     * @param string $name The name of the guarded action.
     * @return string
     */
    private function globalCacheKey(string $name): string
    {
        return 'BruteforceData.global.' . $name;
    }
}
