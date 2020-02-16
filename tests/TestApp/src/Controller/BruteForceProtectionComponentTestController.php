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
            ['totalAttemptsLimit' => 3]
        );
	    if ($this->getRequest()->getData('username') === 'admin' && $this->getRequest()->getData('password') === 'correct') {
	        $this->set(
	            'correct',
                ($this->getRequest()->getData('username') === 'admin' && $this->getRequest()->getData('password') === 'correct')
            );
        }
    }
}
