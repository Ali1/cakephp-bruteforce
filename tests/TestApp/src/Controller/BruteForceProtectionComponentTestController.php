<?php

namespace TestApp\Controller;

use Cake\Controller\Controller;

/**
 * Use Controller instead of AppController to avoid conflicts
 *
 * @property \BruteForceProtection\Controller\Component\BruteForceProtectionComponent $BruteForceProtection
 */
class BruteForceProtectionComponentTestController extends Controller {

    /**
     * @throws \Exception
     * @return void
     */
	public function initialize(): void {
        $this->loadComponent('BruteForceProtection.BruteForceProtection');
	}

    public function login(): void {
        $this->autoRender = false;
        $this->BruteForceProtection->applyProtection(
            'login',
            ['username', 'password'],
            $this->getRequest()->getData(),
            ['totalAttemptsLimit' => 4, 'firstKeyAttemptLimit' => 3, 'unencryptedKeyNames' => ['username']]
        );
    }
    public function loginEncrypted(): void {
        $this->autoRender = false;
        $this->BruteForceProtection->applyProtection(
            'loginEncrypted',
            ['username', 'password'],
            $this->getRequest()->getData(),
            ['totalAttemptsLimit' => 4, 'firstKeyAttemptLimit' => 3]
        );
    }

    public function loginByUrl($secret): void {
        $this->autoRender = false;
        $this->BruteForceProtection->applyProtection(
            'loginByUrl',
            ['secret'],
            ['secret' => $secret],
            ['totalAttemptsLimit' => 2]
        );
    }

    public function shortTimeWindow($secret): void {
        $this->autoRender = false;
        $this->BruteForceProtection->applyProtection(
            'loginByUrl',
            ['secret'],
            ['secret' => $secret],
            ['totalAttemptsLimit' => 1, 'timeWindow' => 4]
        );
    }
}
