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

		$challengeData = $securedChallengeData = [];

		foreach (array_keys($data) as $key) {
			if (is_int($key)) {
				throw new InvalidArgumentExceptionInvalidArgumentException('Keys for data cannot be integers');
				// data = [$password]. Must be data = ['password' => $password]
			}
		}

		foreach ($keyNames as $keyName) {
		    if (!isset($data[$keyName]) || !is_string($data[$keyName]) || $data[$keyName] === '') {

                return; // not being challenged or empty challenge - do not count
            }
			$challengeData[$keyName] = $securedChallengeData[$keyName] = $data[$keyName];
            if ($this->isKeyEncrypted($keyName, $config)) {
                $securedChallengeData[$keyName] = password_hash($data[$keyName], PASSWORD_DEFAULT);
            }
        }
        $unencryptedFirstKey = $challengeData[$keyNames[0]];

		$ipData = Cache::read($this->cacheKey($name), $config['cacheName']);
		if (empty($ipData)) {
			$ipData = ['attempts' => []]; // first login attempt - initialize data for cache
		}
		// remove old attempts based on configured time window
		$ipData['attempts'] = array_filter($ipData['attempts'], static function ($attempt) use ($config) {
			return $attempt['time'] > (time() - $config['timeWindow']);
		});

		// analyse history of this user
		$total_attempts = count($ipData['attempts']);
		$attemptedChallenges = Hash::extract($ipData['attempts'], '{n}.challengeDataHash');
		$first_key_attempts = 0;
		if ($config['firstKeyAttemptLimit']) {
			foreach ($ipData['attempts'] as $k => $attempt) {
				if ($this->isKeyEncrypted($keyNames[0], $config)) {
                    if (!password_verify($unencryptedFirstKey, $attempt['firstKey'])) {
                        continue;
                    }
				} else {
                    if ($unencryptedFirstKey !== $attempt['firstKey']) {
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

			foreach ($challengeData as $keyName => $datum) {
			    if (!$this->isSameStringOrMatchHash(
			        $datum,
                    $existingChallengeData[$keyName],
                    $this->isKeyEncrypted($keyName, $config)
                )) {
                    continue(2);
                }
			}

			return; // if reached here, that means exactly same attempt previously - do not count
		}

		if (
		    $total_attempts > $config['totalAttemptsLimit']
            || ($config['firstKeyAttemptLimit'] && $first_key_attempts > $config['firstKeyAttemptLimit'])
        ) {
			Log::alert(
			    "Bruteforce blocked\nIP: {$this->getController()->getRequest()->getEnv('REMOTE_ADDR')}\n",
                serialize($ipData)
            );
			throw new TooManyAttemptsException();
		}

		// this new attempt
		$ipData['attempts'][] = [
		    'firstKey' => $securedChallengeData[$keyNames[0]],
            'challengeDataHash' => serialize($securedChallengeData),
            'time' => time(),
        ];

		Cache::write($this->cacheKey($name), $ipData, $config['cacheName']);
	}

    /**
     * @return string
     */
	private function ipKey(): string {
        $ip = $this->getRequest()->getEnv('REMOTE_ADDR') ?? null;
        if (!$ip) {
            return 'noIP';
        }
        return str_replace(':', '.', $ip);
    }

    /**
     * @param $name
     *
     * @return string
     */
    public function cacheKey($name): string {
        return 'BruteforceData.' . str_replace(':', '.', $this->ipKey()) . '.' . $name;
    }

    /**
     * @param string $newString
     * @param string $oldString
     * @param bool $oldEncrypted
     *
     * @return bool
     */
    private function isSameStringOrMatchHash(string $newString, string $oldString, bool $oldEncrypted): bool {

        return $oldEncrypted ? password_verify($newString, $oldString) : $newString === $oldString;
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
