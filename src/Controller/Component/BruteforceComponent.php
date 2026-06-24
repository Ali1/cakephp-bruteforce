<?php

declare(strict_types=1);

namespace Bruteforce\Controller\Component;

use Ali1\BruteForceShield\Configuration;
use Bruteforce\Utility\BruteforceLimiter;
use Cake\Controller\Component;

class BruteforceComponent extends Component
{

    /**
     * @param string $name A unique name for each protected flow.
     * @param array $data Challenge data, commonly `$this->request->getData()`.
     * @param \Ali1\BruteForceShield\Configuration|null $bruteConfig Legacy configuration object.
     * @param string $cache Cache configuration name.
     * @param array $config Extra limiter options.
     * @return bool
     */
    public function validate(
        string $name,
        array $data,
        ?Configuration $bruteConfig = null,
        string $cache = 'default',
        array $config = []
    ): bool {
        $config += [
            'timeWindow' => $bruteConfig ? $bruteConfig->getTimeWindow() : 300,
            'totalLimit' => $bruteConfig ? $bruteConfig->getTotalAttemptsLimit() : 8,
            'stricterKey' => $bruteConfig ? $bruteConfig->getStricterLimitKey() : null,
            'stricterLimit' => $bruteConfig ? $bruteConfig->getStricterLimitAttempts() : null,
            'plainKeys' => $bruteConfig ? $bruteConfig->getUnencryptedKeyNames() : [],
            'cache' => $cache,
        ];

        return (new BruteforceLimiter())->validate(
            $name,
            $data,
            $this->clientIp(),
            $config
        );
    }

    public function cacheKey(string $name): string
    {
        return 'BruteforceData.' . str_replace([':', '\\', '/', ' '], '.', $this->clientIp()) . '.' . $name;
    }

    private function clientIp(): string
    {
        return (string)($this->getController()->getRequest()->clientIp() ?: 'noIP');
    }
}
