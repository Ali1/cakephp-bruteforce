<?php

declare(strict_types=1);

namespace Bruteforce\Utility;

use Bruteforce\Exception\TooManyAttemptsException;
use Cake\Cache\Cache;
use Cake\Log\Log;

class BruteforceLimiter
{
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
            (array)$config['caseInsensitiveKeys']
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

    private function checkScope(array $challenge, array $config, string $cacheKey, string $scope): array
    {
        $history = Cache::read($cacheKey, $config['cache']);
        $history = is_array($history) ? $history : ['attempts' => []];
        $oldestAllowed = time() - (int)$config['timeWindow'];
        $history['attempts'] = array_values(array_filter(
            (array)$history['attempts'],
            static fn (array $attempt): bool => (int)($attempt['time'] ?? 0) > $oldestAllowed
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
                && hash_equals((string)$challenge[$config['stricterKey']], (string)$oldChallenge[$config['stricterKey']])
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

    private function cacheKey(string $name, string $clientIp): string
    {
        return 'BruteforceData.' . str_replace([':', '\\', '/', ' '], '.', $clientIp) . '.' . $name;
    }

    private function globalCacheKey(string $name): string
    {
        return 'BruteforceData.global.' . $name;
    }
}
