# CakePHP Brute Force Plugin

[![Framework](https://img.shields.io/badge/Framework-CakePHP%205.x-orange.svg)](https://cakephp.org)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://www.php.net/)
[![license](https://img.shields.io/github/license/ali1/cakephp-bruteforce.svg?maxAge=2592000)](/blob/master/LICENSE)

A CakePHP 5 plugin providing cache-backed brute-force protection for controller actions. The limiter is implemented in
this package; it is not a wrapper around BruteForceShield.

## Features

- Per-client-IP and cross-IP attempt budgets
- Optional stricter limit for repeated attempts against one field, such as a username
- Duplicate-challenge detection, so retrying exactly the same values does not consume another attempt
- Server-keyed HMAC-SHA256 storage for every submitted value
- Inter-process locking around each complete cache read/check/write transaction
- Fail-closed cache writes and lock acquisition, with critical logging
- Alert logging for blocked attempts without submitted values

## Requirements

- PHP 8.2+
- CakePHP 5.x
- A CakePHP cache configuration shared by all PHP workers serving the application
- A non-empty, secret `Security.salt`

## Development

Install development dependencies and run the complete quality gate:

```sh
composer install
composer check
```

The gate runs CakePHP coding standards, PHPStan, and the PHPUnit suite.

The lock files live in PHP's system temporary directory and coordinate workers on one host. Each cache configuration
uses a fixed pool of 256 striped locks, so rotating client addresses cannot create unbounded files. A multi-host
deployment must put that directory on shared storage or add a distributed lock before sharing one limiter cache across
hosts.

## Installation

```sh
composer require ali1/cakephp-bruteforce
```

Load the plugin in `src/Application.php`:

```php
$this->addPlugin('Bruteforce');
```

Load the component in a controller:

```php
public function initialize(): void
{
    parent::initialize();
    $this->loadComponent('Bruteforce.Bruteforce');
}
```

## Basic use

Call `validate()` before checking or acting on submitted credentials:

```php
use Bruteforce\Configuration;

$configuration = (new Configuration())
    ->setTotalAttemptsLimit(60)
    ->setStricterLimitOnKey('username', 7);

$this->Bruteforce->validate(
    'login',
    [
        'username' => $this->request->getData('username'),
        'password' => $this->request->getData('password'),
    ],
    $configuration,
);
```

The component throws `Bruteforce\Exception\TooManyAttemptsException` when a limit is reached or protection cannot
safely persist the attempt.

Applications upgrading from version 6.0 may temporarily keep importing
`Ali1\BruteForceShield\Configuration`; this package provides that name as a deprecated compatibility class. New code
should use `Bruteforce\Configuration`.

## Limiter options

The fifth `validate()` argument accepts additional options. For applications migrating from a local limiter whose third
argument was an options array, the component also accepts that array directly as its third argument.

| Option | Default | Meaning |
|---|---:|---|
| `timeWindow` | `300` | Per-IP rolling window in seconds |
| `totalLimit` | `8` | Distinct attempts allowed per IP |
| `stricterKey` | `null` | Field receiving a lower per-value limit |
| `stricterLimit` | `null` | Distinct attempts allowed for `stricterKey` |
| `globalTotalLimit` | `100` | Distinct attempts allowed across all IPs |
| `globalStricterLimit` | per-IP value | Cross-IP limit for `stricterKey` |
| `globalTimeWindow` | per-IP value | Cross-IP rolling window in seconds |
| `skipGlobal` | `false` | Explicitly disable the cross-IP check for this request |
| `challengeKeys` | all fields | Submitted fields included in duplicate and limit checks |
| `caseInsensitiveKeys` | none | Fields lowercased before comparison, commonly usernames |
| `cache` | component argument | CakePHP cache configuration name |

The cross-IP budget is enabled by default. It is the backstop when an attacker can rotate source addresses or when a
proxy configuration mistakenly accepts spoofed `X-Forwarded-For` values. Set an application-specific value based on
legitimate aggregate traffic; disable it only when another trusted edge enforces an equivalent global budget.

## Proxy configuration

The component accepts `ServerRequest::clientIp()` only when it is a valid single IP address and otherwise falls back to
the direct `REMOTE_ADDR`. This validation does not make an untrusted forwarding header trustworthy. If CakePHP request
proxy trust is enabled, configure an explicit allowlist of trusted reverse-proxy addresses; never enable unrestricted
proxy trust. Keep the global budget enabled even with a correct allowlist.

## Stored and logged data

Every non-empty scalar challenge value is normalized in memory and stored only as
`HMAC-SHA256(value, Security.salt)`. The random-looking cache value is deterministic so duplicate and stricter-key
comparisons remain efficient, but an attacker who obtains only the shared cache cannot run an offline dictionary attack
without the server secret.

Blocked-attempt logs contain the client IP, action name, limiting scope, submitted field names, and attempt count. They
never contain submitted values. The legacy `addUnencryptedKey()` method is retained only so existing applications keep
running; it no longer causes plaintext cache storage or logging and should be removed from application code.

## URL-token protection

Secret URL tokens use the same protection and must not be marked for plaintext handling:

```php
$configuration = (new Configuration())->setTotalAttemptsLimit(5);

$this->Bruteforce->validate(
    'publicAuthUrl',
    ['hashedid' => $hashedid],
    $configuration,
);
```
