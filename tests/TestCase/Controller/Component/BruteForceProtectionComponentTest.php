<?php
declare(strict_types=1);

namespace BruteForceProtection\Test\TestCase\Controller\Component;

use BruteForceProtection\Controller\Component\BruteForceProtectionComponent;
use Cake\Cache\Cache;
use Cake\Controller\ComponentRegistry;
use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use TestApp\Controller\BruteForceProtectionComponentTestController;
use TestApp\Controller\FlashComponentTestController;

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

        $this->Controller = new BruteForceProtectionComponentTestController(new ServerRequest());
        $this->Controller->startupProcess();

        Cache::delete('BruteForceData');
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

    public test
}
