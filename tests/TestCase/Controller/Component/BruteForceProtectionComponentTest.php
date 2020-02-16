<?php
declare(strict_types=1);

namespace BruteForceProtection\Test\TestCase\Controller\Component;

use BruteForceProtection\Controller\Component\BruteForceProtectionComponent;
use Cake\Cache\Cache;
use Cake\Controller\ComponentRegistry;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use TestApp\Controller\BruteForceProtectionComponentTestController;

/**
 * App\Controller\Component\BruteForceProtectionComponent Test Case
 */
class BruteForceProtectionComponentTest extends TestCase
{
    /**
     * @var \TestApp\Controller\BruteForceProtectionComponentTestController
     */
    protected $Controller;

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        Cache::delete('BruteForceData');

        $this->Controller = new BruteForceProtectionComponentTestController(new ServerRequest());
        $this->Controller->startupProcess();
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
        unset($this->Controller);
    }

    /**
     * Test initial setup
     *
     * @return void
     */
    public function testInitialization(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    public function testLoginProtection(): void {
        $_SERVER['REMOTE_ADDR'] = '5.6.7.8';
        $this->Controller->setRequest($this->Controller->getRequest()->withParam('action', 'login'));
        $this->Controller->setRequest($this->Controller->getRequest()->withData('username', 'admin'));
        $this->Controller->setRequest($this->Controller->getRequest()->withData('password', 'pass1'));
        $event = new Event('Controller.startup', $this->Controller);
        $action = $this->Controller->getAction();
        $this->Controller->invokeAction($action, []);
        debug($this->Controller->viewBuilder()->getVars());
    }
}
