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

}
