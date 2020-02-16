<?php
namespace BruteForceProtection\Controller\Component;

use Cake\Cache\Cache;
use Cake\Controller\Component;
use Cake\Controller\ComponentRegistry;
use Cake\Log\Log;
use Cake\Routing\Router;
use Cake\Utility\Hash;

class BruteForceProtectionComponent extends Component
{
    /**
     * @var \App\Controller\AppController
     */
    private $Controller;

    /**
     * @var array
     */
    public $_defaultConfig = [
        'timeWindow' => 300, // 5 minutes
        'totalAttemptsLimit' => 8,
        'firstKeyAttemptLimit' => null, // use integer smaller than totalAttemptsLimit to make tighter restrictions on
        //                                  repeated tries on first key (i.e. 5 tries with a single username, but then
        //                                  can try a few more times if realises the username was wrong
        'unencryptedKeyNames' => [], // keysName for which the data will be stored unencrypted in cache (i.e. usernames)
        'flash' => 'Login attempts have been blocked for a few minutes. Please try again later.', // null for no Flash
        'redirectUrl' => null, // redirect to self
    ];

    /**
     * @inheritDoc
     */
    public function __construct(ComponentRegistry $registry, array $config = [])
    {
        parent::__construct($registry, $config);
        $this->Controller = $this->_registry->getController();
    }

    /**
     * @param string $name
     * @param array $keyNames the key names in the data whose combinations will be checked
     * @param array $data can use $this->request->getData() or any other array, or for BruteForce of single
     *                              value, you can enter a string alone
     * @param array $config options
     * @return void
     */
    public function applyProtection(string $name, array $keyNames, array $data, array $config = [])
    {
        $config = array_merge($this->getConfig(), $config);

        $challengeData = [];

        foreach (array_keys($data) as $key) {
            if (is_int($key)) {
                throw new \InvalidArgumentException('Keys for data cannot be integers');
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
        $cacheKey = 'BruteForceData.' . str_replace(":", ".", $ip) . '.' . $name;

        $ip_data = Cache::read($cacheKey);

        if (empty($ip_data)) {
            // first login attempt - initialize data for cache
            $ip_data = ['attempts' => []];
        }

        // this new attempt
        $newAttempt = ['firstKey' => null, 'challengeDataHash' => null, 'time' => time()];

        $securedChallengeData = $challengeData;
        foreach ($challengeData as $key => $datum) {
            if (!in_array($key, $config['unencryptedKeyNames'])) {
                $securedChallengeData[$key] = password_hash($datum, PASSWORD_DEFAULT);
            }
        }

        $newAttempt['firstKey'] = $securedChallengeData[$keyNames[0]];
        $newAttempt['challengeDataHash'] = serialize($securedChallengeData);

        // remove old attempts based on configured time window
        $ip_data['attempts'] = array_filter($ip_data['attempts'], function ($attempt) use ($config) {
            return ($attempt['time'] > (time() - $config['timeWindow']));
        });

        // analyse history of this user
        $total_attempts = count($ip_data['attempts']);
        $attemptedChallenges = Hash::extract($ip_data['attempts'], '{n}.challengeDataHash');
        $first_key_attempts = 0;
        foreach ($ip_data['attempts'] as $k => $attempt) {
            if ($config['firstKeyAttemptLimit'] && $newAttempt['firstKey'] == $attempt['firstKey']) {
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
            if ($config['flash']) {
                $this->Controller->Flash->error($config['flash']);
            }
            header('Location: ' . Router::url($config['redirectUrl']));
            die();
        }
        $ip_data['attempts'][] = $newAttempt;

        Cache::write($cacheKey, $ip_data);
    }

    /**
     * @param string $ip
     * @param string $key unique string related to this type of challenge
     */
    public function recordFail($ip, $key)
    {
        $key = 'BruteForceData.' . str_replace(":", ".", $ip) . '.' . $key;
        $ip_data = Cache::read($key);

        if (empty($ip_data)) {
            // first login attempt - initialize data for cache
            $ip_data = ['attempts' => []];
        }
        // this new attempt
        $newAttempt = ['firstKey' => null, 'challengeDataHash' => null, 'time' => time()];


    }
}
