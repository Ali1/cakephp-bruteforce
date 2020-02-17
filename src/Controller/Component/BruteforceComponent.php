<?php

namespace Bruteforce\Controller\Component;

use Bruteforce\Challenge;
use Bruteforce\Exception\TooManyAttemptsException;
use Cake\Cache\Cache;
use Cake\Controller\Component;
use Cake\Log\Log;
use InvalidArgumentException as InvalidArgumentExceptionInvalidArgumentException;

class BruteforceComponent extends Component {

	/**
	 * @var array
	 */
	public $_defaultConfig = [
		'cacheName' => 'default',
		'timeWindow' => 300, // 5 minutes
		'totalAttemptsLimit' => 8,
		'firstKeyAttemptLimit' => null, // use integer smaller than totalAttemptsLimit to make tighter restrictions on
		//                                  repeated tries on first key (i.e. 5 tries with a single username, but then
		//                                  can try a few more times if realises the username was wrong
		'unencryptedKeyNames' => [], // keysName for which the data will be stored unencrypted in cache (i.e. usernames)
	];

	/**
	 * /**
	 *
	 * @param string $name a unique string to store the data under (different $name for different uses of Brute
	 *                          force protection within the same application.
	 * @param array $keyNames an array of key names in the data that you intend to interrogate
	 * @param array $data an array of data, can use $this->request->getData()
	 * @param array $config options
	 *
	 * @throws \Bruteforce\Exception\TooManyAttemptsException
	 * @throws \InvalidArgumentException
	 *
	 * @return void
	 */
	public function applyProtection(string $name, array $keyNames, array $data, array $config = []): void {
		$config = array_merge($this->getConfig(), $config);

		foreach (array_keys($data) as $key) {
			if (is_int($key)) {
				throw new InvalidArgumentExceptionInvalidArgumentException('Keys for data cannot be integers');
				// i.e. $data parameter cannot be array($password). Must be array('password' => $password)
			}
		}

		$newChallenge = new Challenge();

		foreach ($keyNames as $keyName) {
		    if (!isset($data[$keyName]) || !is_string($data[$keyName]) || $data[$keyName] === '') {

				return; // not being challenged or empty challenge - do not count
			}
		    $newChallenge->addData($keyName, $data[$keyName], $this->isKeyEncrypted($keyName, $config));
		}

		$ipData = Cache::read($this->cacheKey($name), $config['cacheName']);
		if (empty($ipData)) {
			$ipData = ['attempts' => []]; // first login attempt - initialize data for cache
		}
		// remove old attempts based on configured time window
		$ipData['attempts'] = array_filter($ipData['attempts'], static function ($attempt) use ($config) {
			return $attempt['time'] > (time() - $config['timeWindow']);
		});

		// analyse history of this user
		$totalAttempts = count($ipData['attempts']);
		$firstKeyAttempts = 0;

		foreach ($ipData['attempts'] as $attempt) {
			/** @var \Bruteforce\Challenge $oldChallenge */
			$oldChallenge = unserialize($attempt['challenge'], [Challenge::class]);
			// no need to applyProtection and count this challenge if it is identical to a previous challenge attempt
			if ($newChallenge->matchesAnOldChallenge($oldChallenge)) {
				return; // if reached here, that means exactly same attempt previously - do not count
			}

			if ($newChallenge->matchesAnOldChallenge($oldChallenge, true)) {
				$firstKeyAttempts++;
			}
		}

		if (
		    $totalAttempts > $config['totalAttemptsLimit']
			|| ($config['firstKeyAttemptLimit'] && $firstKeyAttempts > $config['firstKeyAttemptLimit'])
		) {
			Log::alert(
			    "Bruteforce blocked\nIP: {$this->getController()->getRequest()->getEnv('REMOTE_ADDR')}\n",
				serialize($ipData)
			);
			throw new TooManyAttemptsException();
		}

		// this new attempt
		$ipData['attempts'][] = [
			'challenge' => serialize($newChallenge),
			'time' => time(),
		];

		Cache::write($this->cacheKey($name), $ipData, $config['cacheName']);
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
		return 'BruteforceData.' . str_replace(':', '.', $this->ipKey()) . '.' . $name;
	}

	/**
	 * @param string $keyName
	 * @param array $config
	 *
	 * @return bool
	 */
	private function isKeyEncrypted(string $keyName, array $config): bool {
		return !in_array($keyName, $config['unencryptedKeyNames'], true);
	}

}
