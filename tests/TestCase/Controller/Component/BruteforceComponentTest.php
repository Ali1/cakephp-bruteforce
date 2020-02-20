<?php
declare(strict_types = 1);

namespace TestCase\Controller\Component;

use Bruteforce\Exception\TooManyAttemptsException;
use Cake\Cache\Cache;
use Cake\Event\Event;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use TestApp\Controller\BruteforceComponentTestController;

/**
 * App\Controller\Component\BruteForceProtectionComponent Test Case
 */
class BruteforceComponentTest extends TestCase {

	/**
	 * @var \TestApp\Controller\BruteforceComponentTestController
	 */
	protected $Controller;

	/**
	 * setUp method
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		Cache::delete('BruteForceData');

		$this->Controller = new BruteforceComponentTestController(new ServerRequest());
		$this->Controller->startupProcess();
	}

	/**
	 * tearDown method
	 *
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();
		unset($this->Controller);
	}

	/**
	 * @throws \Exception
	 *
	 * @return void
	 */
	public function testLogin(): void {
		$this->loginTries('login');
	}

	/**
	 * @throws \Exception
	 *
	 * @return void
	 */
	public function testLoginAddExtraKeys(): void {
		$this->loginTries('loginAddExtraKeys', false);
	}

	/**
	 * @throws \Exception
	 *
	 * @return void
	 */
	public function testLoginEncrypted(): void {
		$this->loginTries('loginEncrypted');
	}

	/**
	 * @throws \Exception
	 *
	 * @return void
	 */
	public function testLoginUnencrypted(): void {
		$this->loginTries('loginUnencrypted');
	}

	/**
	 * @param string $actionName
	 *
	 * @param bool $tryRepeat
	 *
	 * @throws \Exception
	 * @return void
	 */
	public function loginTries($actionName, $tryRepeat = true): void {
		$_SERVER['REMOTE_ADDR'] = random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(0, 255);
		new Event('Controller.startup', $this->Controller);
		$this->Controller->setRequest($this->Controller->getRequest()->withParam('action', $actionName));
		$action = $this->Controller->getAction();
		$this->Controller->setRequest($this->Controller->getRequest()->withData('username', 'admin'));

		$allowsAttempts = false;
		try {
			$this->Controller->setRequest($this->Controller->getRequest()->withData('password', 'first'));
			$this->Controller->invokeAction($action, []);
			for ($i = 1; $i <= 3; $i++) {
				$this->Controller->setRequest($this->Controller->getRequest()->withData('password', (string)mt_rand()));
				$this->Controller->invokeAction($action, []);
			}
			$allowsAttempts = true;
		} catch (TooManyAttemptsException $e) {
		}
		$this->assertTrue($allowsAttempts);

		$disallowsAttemptsOverLimit = false;
		try {
			$this->Controller->setRequest($this->Controller->getRequest()->withData('password', (string)mt_rand()));
			$this->Controller->invokeAction($action, []);
		} catch (TooManyAttemptsException $e) {
			$disallowsAttemptsOverLimit = true;
		}

		$this->assertTrue($disallowsAttemptsOverLimit);

		$allowsExtraUsernameAttempt = false;
		try {
			$this->Controller->setRequest($this->Controller->getRequest()->withData('username', 'admin2'));
			$this->Controller->setRequest($this->Controller->getRequest()->withData('password', (string)mt_rand()));
			$this->Controller->invokeAction($action, []);
			$allowsExtraUsernameAttempt = true;
		} catch (TooManyAttemptsException $e) {
		}

		$this->assertTrue($allowsExtraUsernameAttempt);

		$disallowsAnyUsernameAttemptsOverLimit = false;
		try {
			$this->Controller->setRequest($this->Controller->getRequest()->withData('username', 'admin3'));
			$this->Controller->setRequest($this->Controller->getRequest()->withData('password', (string)mt_rand()));
			$this->Controller->invokeAction($action, []);
		} catch (TooManyAttemptsException $e) {
			$disallowsAnyUsernameAttemptsOverLimit = true;
		}
		$this->assertTrue($disallowsAnyUsernameAttemptsOverLimit);

		if ($tryRepeat) {
			$allowsRepeatCombination = false;
			try {
				$this->Controller->setRequest($this->Controller->getRequest()->withData('username', 'admin'));
				$this->Controller->setRequest($this->Controller->getRequest()->withData('password', 'first'));
				$this->Controller->invokeAction($action, []);
				$allowsRepeatCombination = true;
			}
			catch (TooManyAttemptsException $e) {
			}
			$this->assertTrue($allowsRepeatCombination);
		}
	}

	/**
	 * @throws \Exception
	 * @return void
	 */
	public function testSingleKey(): void {
		$ip = $_SERVER['REMOTE_ADDR'] = random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(0, 255);
		$this->Controller->setRequest($this->Controller->getRequest()->withParam('action', 'loginByUrl'));
		$action = $this->Controller->getAction();
		new Event('Controller.startup', $this->Controller);
		$allowsAttempts = false;
		try {
			for ($i = 1; $i <= 3; $i++) {
				$this->Controller->invokeAction($action, [(string)mt_rand()]);
			}
			$allowsAttempts = true;
		} catch (TooManyAttemptsException $e) {
		}
		$this->assertTrue($allowsAttempts);

		$disallowsAttemptsOverLimit = false;
		try {
			$this->Controller->invokeAction($action, [(string)mt_rand()]);
		} catch (TooManyAttemptsException $e) {
			$disallowsAttemptsOverLimit = true;
		}
		$this->assertTrue($disallowsAttemptsOverLimit);
	}

	/**
	 * @throws \Exception
	 *
	 * @return void
	 */
	public function testShortTimeWindow(): void {
		$ip = $_SERVER['REMOTE_ADDR'] = random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(0, 255) . '.' . random_int(0, 255);
		$this->Controller->setRequest($this->Controller->getRequest()->withParam('action', 'shortTimeWindow'));
		$action = $this->Controller->getAction();
		new Event('Controller.startup', $this->Controller);
		$allowsAttempts = false;
		try {
			for ($i = 1; $i <= 2; $i++) {
				$this->Controller->invokeAction($action, [(string)mt_rand()]);
			}
			$allowsAttempts = true;
		} catch (TooManyAttemptsException $e) {
		}
		$this->assertTrue($allowsAttempts);

		$disallowsAttemptsOverLimit = false;
		try {
			$this->Controller->invokeAction($action, [(string)mt_rand()]);
		} catch (TooManyAttemptsException $e) {
			$disallowsAttemptsOverLimit = true;
		}
		$this->assertTrue($disallowsAttemptsOverLimit);

		sleep(5);
		$allowsAttemptAfterTimeWindow = false;
		try {
			$this->Controller->invokeAction($action, [(string)mt_rand()]);
			$allowsAttemptAfterTimeWindow = true;
		} catch (TooManyAttemptsException $e) {
		}
		$this->assertTrue($allowsAttemptAfterTimeWindow);
	}

	/**
	 * @return void
	 */
	public function testEmptyChallenge(): void {
		$this->Controller->setRequest($this->Controller->getRequest()->withParam('action', 'login'));
		$action = $this->Controller->getAction();
		for ($i = 1; $i <= 30; $i++) {
			$allowsUnlimitedTriesWhenEmpty = false;
			try {
				$this->Controller->invokeAction($action, [(string)mt_rand()]);
				$allowsUnlimitedTriesWhenEmpty = true;
			}
			catch (TooManyAttemptsException $e) {
			}
			$this->assertTrue($allowsUnlimitedTriesWhenEmpty);
		}
	}

}
