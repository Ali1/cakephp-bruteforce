<?php
declare(strict_types=1);

namespace Bruteforce\Controller\Component;

use Ali1\BruteForceShield\Configuration as LegacyConfiguration;
use Bruteforce\Configuration;
use Bruteforce\Utility\BruteforceLimiter;
use Cake\Controller\Component;

class BruteforceComponent extends Component
{
    /**
     * @param string $name A unique name for each protected flow.
     * @param array $data Challenge data, commonly `$this->request->getData()`.
     * @param \Bruteforce\Configuration|\Ali1\BruteForceShield\Configuration|array|null $bruteConfig Limiter configuration or limiter options.
     * @param string $cache Cache configuration name.
     * @param array $config Extra limiter options.
     * @return bool
     */
    public function validate(
        string $name,
        array $data,
        Configuration|LegacyConfiguration|array|null $bruteConfig = null,
        string $cache = 'default',
        array $config = [],
    ): bool {
        if (is_array($bruteConfig)) {
            $config = $bruteConfig + $config;
            $bruteConfig = null;
        }

        $config += [
            'timeWindow' => $bruteConfig ? $bruteConfig->getTimeWindow() : 300,
            'totalLimit' => $bruteConfig ? $bruteConfig->getTotalAttemptsLimit() : 8,
            'stricterKey' => $bruteConfig ? $bruteConfig->getStricterLimitKey() : null,
            'stricterLimit' => $bruteConfig ? $bruteConfig->getStricterLimitAttempts() : null,
            'cache' => $cache,
        ];

        return (new BruteforceLimiter())->validate(
            $name,
            $data,
            $this->clientIp(),
            $config,
        );
    }

    /**
     * The cache key holding this client's attempt history for a guarded action.
     *
     * @param string $name The name of the guarded action.
     * @return string
     */
    public function cacheKey(string $name): string
    {
        return 'BruteforceData.' . str_replace([':', '\\', '/', ' '], '.', $this->clientIp()) . '.' . $name;
    }

    /**
     * The requesting client's IP, or 'noIP' when it cannot be determined.
     *
     * @return string
     */
    private function clientIp(): string
    {
        $request = $this->getController()->getRequest();
        $clientIp = (string)$request->clientIp();
        if (filter_var($clientIp, FILTER_VALIDATE_IP) !== false) {
            return $clientIp;
        }

        $remoteIp = (string)$request->getEnv('REMOTE_ADDR');
        if (filter_var($remoteIp, FILTER_VALIDATE_IP) !== false) {
            return $remoteIp;
        }

        return 'noIP';
    }
}
