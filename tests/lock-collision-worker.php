<?php
declare(strict_types=1);

use Bruteforce\Exception\TooManyAttemptsException;
use Bruteforce\Utility\BruteforceLimiter;
use Cake\Cache\Cache;

require __DIR__ . '/bootstrap.php';

// These IP/global keys collide in the bounded lock pool for the real login flow.
$limiter = new BruteforceLimiter();
$config = ['totalLimit' => 1, 'globalTotalLimit' => 1];
$challenge = ['password' => 'first'];
$limiter->validate('login', $challenge, '192.0.5.138', $config);
$limiter->validate('login', $challenge, '192.0.5.138', $config);

try {
    $limiter->validate('login', ['password' => 'second'], '192.0.5.138', $config);
    echo 'incorrectly allowed';
} catch (TooManyAttemptsException) {
    echo json_encode([
        'ip' => count(Cache::read('BruteforceData.192.0.5.138.login')['attempts']),
        'global' => count(Cache::read('BruteforceData.global.login')['attempts']),
        'blocked' => true,
    ]);
}
