<?php
namespace BruteForceProtection\Controller\Component;

use Cake\Cache\Cache;
use Cake\Controller\Component;
use Cake\Log\Log;
use Cake\Routing\Router;
use Cake\Utility\Hash;

/**
 * @property \Cake\Controller\Component\FlashComponent $Flash
 */
class BruteForceProtectionComponent extends Component
{
    /**
     * @var array
     */
    public $components = ['Flash'];

    /**
     * @var array
     */
    public $_defaultConfig = [
        'name' => 'login', // important to set if application uses component in more than 1 process
        'timeWindow' => 300, // 5 minutes
        'totalAttemptsLimit' => 8,
        'keyNames' => ['username', 'password'],
        'firstKeyAttemptLimit' => false, // can be used for example when you want tighter limits on username
        'security' => 'all', // which inputs should be encrypted in cache - none, firstKeyUnsecure (i.e. username), all
        'flash' => true,
        'redirectUrl' => '/',
        'data' => null, // uses request->getData if null, otherwise provide an array of input data
    ];

    /**
     * @param array $config
     *
     * @return void
     */
    public function applyProtection(array $config)
    {
        $config = array_merge($this->getConfig(), $config);
        if (is_string($config['keyNames'])) {
            $config['keyNames'] = [$config['keyNames']];
        }
        $controller = $this->_registry->getController();

        // Check and record attempts input data against config
        if ($config['data'] === null) {
            $data = $this->request->getData();
        } else {
            $data = $config['data'];
        }
        $challengeData = [];
        foreach ($config['keyNames'] as $keyName) {
            $challengeData[$keyName] = empty($data[$keyName]) ? '' : $data[$keyName];
            if (!$challengeData[$keyName]) {
                return; // not being challenged or empty challenge - do not count
            }
        }

        // prepare cache object for this IP address and this $config instance
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = 'BruteForceData.' . $ip . '.' . $config['name'];
        $ip_data = Cache::read($key);

        if (empty($ip_data)) {
            // first login attempt - initialize data for cache
            $ip_data = ['attempts' => []];
        }

        // this new attempt
        $newAttempt = ['firstKey' => null, 'challengeDataHash' => null, 'time' => time()];

        if ($config['security'] === 'none') {
            $newAttempt['firstKey'] = $challengeData[$config['keyNames'][0]];
            $newAttempt['challengeDataHash'] = serialize($challengeData);
        } elseif ($config['security'] === 'firstKeyUnsecure') {
            $newAttempt['firstKey'] = $challengeData[$config['keyNames'][0]];
            $newAttempt['challengeDataHash'] = password_hash(serialize($challengeData), PASSWORD_DEFAULT);
        } else {
            $newAttempt['firstKey'] = password_hash($challengeData[$config['keyNames'][0]], PASSWORD_DEFAULT);
            $newAttempt['challengeDataHash'] = password_hash(serialize($challengeData), PASSWORD_DEFAULT);
        }
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
            if ($config['security'] === 'none') {
                if (serialize($challengeData) == $existingChallengeDataHash) {
                    return;
                }
            } else {
                if (password_verify(serialize($challengeData), $existingChallengeDataHash)) {
                    return; // has been counted previously
                }
            }
        }

        if ($total_attempts > $config['totalAttemptsLimit'] || ($config['firstKeyAttemptLimit'] && $first_key_attempts > $config['firstKeyAttemptLimit'])) {
            Log::alert("Blocked login attempt\nIP: $ip\n\n", serialize($ip_data));
            if ($config['flash']) {
                $this->Flash->error('Login attempts have been blocked for a few minutes. Please try again later.');
            }
            header('Location: ' . Router::url($config['redirectUrl']));
            die();
        }
        $ip_data['attempts'][] = $newAttempt;
        Cache::write($key, $ip_data);
    }

    /**
     * @param string $id
     * @param string $key unique string related to this type of challenge
     */
    public function recordFail($ip, $key)
    {
        $key = 'BruteForceData.' . $ip . '.' . $key;
        $ip_data = Cache::read($key);

        if (empty($ip_data)) {
            // first login attempt - initialize data for cache
            $ip_data = ['attempts' => []];
        }
        // this new attempt
        $newAttempt = ['firstKey' => null, 'challengeDataHash' => null, 'time' => time()];


    }
}