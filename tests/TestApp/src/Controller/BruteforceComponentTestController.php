<?php

namespace TestApp\Controller;

use Ali1\BruteForceShield\Configuration;
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
		$bruteConfig = new Configuration();
		$bruteConfig->setTotalAttemptsLimit(4);
		$bruteConfig->setStricterLimitOnKey('username', 3);
		$bruteConfig->addUnencryptedKey('username');
		$this->Bruteforce->validate(
			'login',
			$this->getRequest()->getData(),
			$bruteConfig
		);
	}

	/**
	 * @return void
	 */
	public function loginAddExtraKeys(): void {
		$this->autoRender = false;
		$bruteConfig = new Configuration();
		$bruteConfig->setTotalAttemptsLimit(4);
		$bruteConfig->setStricterLimitOnKey('username', 3);
		$bruteConfig->addUnencryptedKey('username');
		$this->Bruteforce->validate(
			'loginAddExtraKeys',
			array_merge($this->getRequest()->getData(), ['str_' . mt_rand() => mt_rand()]),
			$bruteConfig
		);
	}

	/**
	 * @return void
	 */
	public function loginEncrypted(): void {
		$this->autoRender = false;
		$bruteConfig = new Configuration();
		$bruteConfig->setTotalAttemptsLimit(4);
		$bruteConfig->setStricterLimitOnKey('username', 3);
		$this->Bruteforce->validate(
			'loginEncrypted',
			$this->getRequest()->getData(),
			$bruteConfig
		);
	}

	/**
	 * @return void
	 */
	public function loginUnencrypted(): void {
		$this->autoRender = false;
		$bruteConfig = new Configuration();
		$bruteConfig->setTotalAttemptsLimit(4);
		$bruteConfig->setStricterLimitOnKey('username', 3);
		$bruteConfig->addUnencryptedKey('username');
		$bruteConfig->addUnencryptedKey('password');
		$this->Bruteforce->validate(
			'loginUnencrypted',
			$this->getRequest()->getData(),
			$bruteConfig
		);
	}

	/**
	 * @param string $secret
	 *
	 * @return void
	 */
	public function loginByUrl($secret): void {
		$this->autoRender = false;
		$bruteConfig = new Configuration();
		$bruteConfig->setTotalAttemptsLimit(2);
		$this->Bruteforce->validate(
			'loginByUrl',
			['secret' => $secret],
			$bruteConfig
		);
	}

	/**
	 * @param string $secret
	 *
	 * @return void
	 */
	public function shortTimeWindow($secret): void {
		$this->autoRender = false;
		$bruteConfig = new Configuration();
		$bruteConfig->setTotalAttemptsLimit(1);
		$bruteConfig->setTimeWindow(4);
		$this->Bruteforce->validate(
			'loginByUrl',
			['secret' => $secret],
			$bruteConfig
		);
	}

}
