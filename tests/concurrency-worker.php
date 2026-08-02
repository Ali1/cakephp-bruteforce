<?php
declare(strict_types=1);

use Bruteforce\Exception\TooManyAttemptsException;
use Bruteforce\Utility\BruteforceLimiter;

require __DIR__ . '/bootstrap.php';

$name = (string)($argv[1] ?? 'missing');
$value = (string)($argv[2] ?? 'missing');

try {
    (new BruteforceLimiter())->validate(
        $name,
        ['password' => $value],
        '192.0.2.99',
        ['totalLimit' => 3, 'globalTotalLimit' => null],
    );
    echo 'allowed';
} catch (TooManyAttemptsException) {
    echo 'blocked';
}
