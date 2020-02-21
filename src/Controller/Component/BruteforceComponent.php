<?php

namespace Bruteforce\Controller\Component;

use Ali1\BruteForceShield\BruteForceShield;
use Ali1\BruteForceShield\Configuration;
use Bruteforce\Exception\TooManyAttemptsException;
use Cake\Cache\Cache;
use Cake\Controller\Component;
use Cake\Log\Log;

class BruteforceComponent extends Component {

	/**
	 * /**
	 *
	 * @param string $name a unique string to store the data under (different $name for different uses of Brute
	 *                          force protection within the same application.
	 * @param array $data an array of data, can use $this->request->getData()
	 * @param \Ali1\BruteForceShield\Configuration|null $bruteConfig
	 * @param string $cache (default: 'default')
	 *
	 * @return bool
	 */
	public function validate(string $name, array $data, ?Configuration $bruteConfig = null, $cache = 'default'): bool {
		$cacheKey = $this->cacheKey($name);
		$shield = new BruteForceShield();
		$userDataRaw = Cache::read($cacheKey, $cache);
		$userData = $userDataRaw ? json_decode($userDataRaw, true) : null;
		$userData = $shield->validate($userData, $data, $bruteConfig);
		Cache::write($cacheKey, json_encode($userData), $cache);
		if (!$shield->isValidated()) {
			Log::alert(
				"Bruteforce blocked\nIP: {$this->getController()->getRequest()->getEnv('REMOTE_ADDR')}\n",
				json_encode($userData)
			);

			throw new TooManyAttemptsException();
		}

		return $shield->isValidated();
	}

	/**
	 * @return string
	 */
	private function ipKey(): string {
		$ip = $this->getController()->getRequest()->getEnv('REMOTE_ADDR');
		if (!$ip) {
			return 'noIP';
		}
		return str_replace(':', '.', $ip);
	}

	/**
	 * @param string $name
	 *
	 * @return string
	 */
	public function cacheKey(string $name): string {
		return 'BruteforceData.' . $this->ipKey() . '.' . $name;
	}

}
