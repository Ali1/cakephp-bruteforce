<?php

namespace Bruteforce\Controller\Component;

use Bruteforce\Exception\TooManyAttemptsException;
use Cake\Cache\Cache;
use Cake\Controller\Component;
use Cake\Log\Log;
use Cake\Utility\Hash;
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

		$challengeData = [];

		foreach (array_keys($data) as $key) {
			if (is_int($key)) {
				throw new InvalidArgumentExceptionInvalidArgumentException('Keys for data cannot be integers');
				// data = [$password]. Must be data = ['password' => $password]
			}
		}

		foreach ($keyNames as $keyName) {
			$challengeData[$keyName] = empty($data[$keyName]) ? '' : $data[$keyName];
			if (!$challengeData[$keyName]) {
				return; // not being challenged or empty challenge - do not count
			}
		}

		// prepare cache object for this IP address and this $config instance
		$ip = $_SERVER['REMOTE_ADDR'];
		$cacheKey = 'BruteForceData.' . str_replace(':', '.', $ip) . '.' . $name;

		$ip_data = Cache::read($cacheKey, $config['cacheName']);

		if (empty($ip_data)) {
			// first login attempt - initialize data for cache
			$ip_data = ['attempts' => []];
		}

		$securedChallengeData = $challengeData;
		foreach ($challengeData as $key => $datum) {
			if (!in_array($key, $config['unencryptedKeyNames'])) {
				$securedChallengeData[$key] = password_hash($datum, PASSWORD_DEFAULT);
			}
		}

		$unencryptedFirstKey = $challengeData[$keyNames[0]];

		// remove old attempts based on configured time window
		$ip_data['attempts'] = array_filter($ip_data['attempts'], function ($attempt) use ($config) {
			return $attempt['time'] > (time() - $config['timeWindow']);
		});

		// analyse history of this user
		$total_attempts = count($ip_data['attempts']);
		$attemptedChallenges = Hash::extract($ip_data['attempts'], '{n}.challengeDataHash');
		$first_key_attempts = 0;
		if ($config['firstKeyAttemptLimit']) {
			foreach ($ip_data['attempts'] as $k => $attempt) {
				if (in_array($keyNames[0], $config['unencryptedKeyNames'])) {
					if ($unencryptedFirstKey !== $attempt['firstKey']) {
						continue;
					}
				} else {
					if (!password_verify($unencryptedFirstKey, $attempt['firstKey'])) {
						continue;
					}
				}
				$first_key_attempts++;
			}
		}

		// don't count this as a challenge if it's a repeat of a previous combination
		foreach ($attemptedChallenges as $existingChallengeDataHash) {
			$existingChallengeData = unserialize($existingChallengeDataHash);
			if (array_keys($securedChallengeData) !== array_keys($existingChallengeData)) {
				continue;
			}

			foreach ($challengeData as $key => $datum) {
				if (in_array($key, $config['unencryptedKeyNames'])) {
					if ($datum !== $existingChallengeData[$key]) {
						continue(2);
					}
				} else {
					if (!password_verify($datum, $existingChallengeData[$key])) {
						continue(2);
					}
				}
			}

			return; // if got to here, that means exactly same attempt previously - do not count
		}

		if ($total_attempts > $config['totalAttemptsLimit'] || ($config['firstKeyAttemptLimit'] && $first_key_attempts > $config['firstKeyAttemptLimit'])) {
			Log::alert("Blocked login attempt\nIP: $ip\n\n", serialize($ip_data));
			throw new TooManyAttemptsException();
		}

		// this new attempt
		$newAttempt = ['firstKey' => null, 'challengeDataHash' => null, 'time' => time()];
		$newAttempt['firstKey'] = $securedChallengeData[$keyNames[0]];
		$newAttempt['challengeDataHash'] = serialize($securedChallengeData);
		$ip_data['attempts'][] = $newAttempt;

		Cache::write($cacheKey, $ip_data, $config['cacheName']);
	}

	/**
	 * @param string $ip
	 * @param string $key unique string related to this type of challenge
	 *
	 * @return void
	 */
	public function recordFail($ip, $key) {
		$key = 'BruteForceData.' . str_replace(':', '.', $ip) . '.' . $key;
		$ip_data = Cache::read($key);

		if (empty($ip_data)) {
			// first login attempt - initialize data for cache
			$ip_data = ['attempts' => []];
		}
		// this new attempt
		$newAttempt = ['firstKey' => null, 'challengeDataHash' => null, 'time' => time()];
	}

}
