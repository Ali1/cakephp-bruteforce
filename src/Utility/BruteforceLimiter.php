<?php
declare(strict_types=1);

namespace Bruteforce\Utility;

use Bruteforce\Exception\TooManyAttemptsException;
use Cake\Cache\Cache;
use Cake\Log\Log;

class BruteforceLimiter
{
    /**
     * Record an attempt and throw once the configured limits are exceeded.
     *
     * @param string $name The name of the guarded action.
     * @param array<string, mixed> $data The submitted data forming the challenge.
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
            'plainKeys' => [],
            'cache' => 'default',
            'globalTotalLimit' => null,
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

        $scopes = [
            $this->checkScope($challenge, $config, $this->cacheKey($name, $clientIp), 'ip'),
        ];
        if (!$config['skipGlobal'] && $config['globalTotalLimit'] !== null) {
            $globalConfig = $config;
            $globalConfig['totalLimit'] = (int)$config['globalTotalLimit'];
            $globalConfig['stricterLimit'] = $config['globalStricterLimit'] ?? $config['stricterLimit'];
            $globalConfig['timeWindow'] = $config['globalTimeWindow'] ?? $config['timeWindow'];
            $scopes[] = $this->checkScope($challenge, $globalConfig, $this->globalCacheKey($name), 'global');
        }

        foreach ($scopes as $scope) {
            if ($scope['blocked']) {
                Log::alert('Bruteforce blocked', [
                    'ip' => $clientIp,
                    'name' => $name,
                    'scope' => $scope['scope'],
                    'data' => $this->loggableChallenge($data, $challenge, (array)$config['plainKeys']),
                    'attempts' => count($scope['history']['attempts']),
                ]);

                throw new TooManyAttemptsException();
            }
        }

        foreach ($scopes as $scope) {
            if ($scope['duplicate']) {
                continue;
            }
            $history = $scope['history'];
            $history['attempts'][] = [
                'challenge' => $challenge,
                'time' => time(),
            ];
            Cache::write($scope['cacheKey'], $history, $config['cache']);
        }

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
        $history = Cache::read($cacheKey, $config['cache']);
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
                && hash_equals(
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
     * Reduce submitted data to a sorted map of hashed, non-empty scalar values.
     *
     * @param array<string, mixed> $data The submitted data.
     * @param array<string>|null $challengeKeys Keys to include, or null for all.
     * @param array<string> $caseInsensitiveKeys Keys to lowercase before hashing.
     * @return array<string, string>
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

            $challenge[$key] = hash('sha256', $value);
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
            if (!isset($oldChallenge[$key]) || !hash_equals((string)$value, (string)$oldChallenge[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build a log-safe view of the challenge, redacting everything not explicitly allowed.
     *
     * @param array<string, mixed> $data The raw submitted data.
     * @param array<string, string> $challenge The hashed challenge.
     * @param array<string> $plainKeys Keys that may be logged in the clear.
     * @return array<string, string>
     */
    private function loggableChallenge(array $data, array $challenge, array $plainKeys): array
    {
        if ($plainKeys === []) {
            return array_fill_keys(array_keys($challenge), '[redacted]');
        }

        $loggable = [];
        foreach ($challenge as $key => $value) {
            $loggable[$key] = in_array($key, $plainKeys, true)
                ? (string)($data[$key] ?? '')
                : '[redacted]';
        }

        return $loggable;
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
