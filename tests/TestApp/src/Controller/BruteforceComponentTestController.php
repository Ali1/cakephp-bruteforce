<?php

namespace TestApp\Controller;

use Cake\Controller\Controller;

/**
 * Use Controller instead of AppController to avoid conflicts
 *
 * @property \Bruteforce\Controller\Component\BruteforceComponent $Bruteforce
 */
class BruteforceComponentTestController extends Controller {

	/**
	 * @throws \Exception
	 *
	 * @return void
	 */
	public function initialize(): void {
		$this->loadComponent('Bruteforce.Bruteforce');
	}

	/**
	 * @return void
	 */
	public function login(): void {
		$this->autoRender = false;
		$this->Bruteforce->applyProtection(
			'login',
			['username', 'password'],
			$this->getRequest()->getData(),
			['totalAttemptsLimit' => 4, 'firstKeyAttemptLimit' => 3, 'unencryptedKeyNames' => ['username']]
		);
	}

    /**
     * @return void
     */
    public function loginEncrypted(): void {
        $this->autoRender = false;
        $this->Bruteforce->applyProtection(
            'loginEncrypted',
            ['username', 'password'],
            $this->getRequest()->getData(),
            ['totalAttemptsLimit' => 4, 'firstKeyAttemptLimit' => 3]
        );
    }

    /**
     * @return void
     */
    public function loginUnencrypted(): void {
        $this->autoRender = false;
        $this->Bruteforce->applyProtection(
            'loginUnencrypted',
            ['username', 'password'],
            $this->getRequest()->getData(),
            ['totalAttemptsLimit' => 4, 'firstKeyAttemptLimit' => 3, 'unencryptedKeyNames' => ['username', 'password']]
        );
    }

	/**
	 * @param string $secret
	 *
	 * @return void
	 */
	public function loginByUrl($secret): void {
		$this->autoRender = false;
		$this->Bruteforce->applyProtection(
			'loginByUrl',
			['secret'],
			['secret' => $secret],
			['totalAttemptsLimit' => 2]
		);
	}

	/**
	 * @param string $secret
	 *
	 * @return void
	 */
	public function shortTimeWindow($secret): void {
		$this->autoRender = false;
		$this->Bruteforce->applyProtection(
			'loginByUrl',
			['secret'],
			['secret' => $secret],
			['totalAttemptsLimit' => 1, 'timeWindow' => 4]
		);
	}

}
