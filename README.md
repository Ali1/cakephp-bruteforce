# CakePHP Brute Force Protection Plugin

[![Framework](https://img.shields.io/badge/Framework-CakePHP%203.x-orange.svg)](http://cakephp.org)
[![license](https://img.shields.io/github/license/LeWestopher/cakephp-monga.svg?maxAge=2592000)](https://github.com/LeWestopher/cakephp-monga/blob/master/LICENSE)
[![Github All Releases](https://img.shields.io/packagist/dt/ali1/cakephp-brute-force-protection.svg?maxAge=2592000)](https://packagist.org/packages/ali1/cakephp-brute-force-protection)
[![Travis](https://img.shields.io/travis/ali1/cakephp-brute-force-protection.svg?maxAge=2592000)](https://travis-ci.org/ali1/cakephp-brute-force-protection)
[![Coverage Status](https://coveralls.io/repos/github/ali1/cakephp-brute-force-protection/badge.svg)](https://coveralls.io/github/ali1/cakephp-brute-force-protection)

A CakePHP plugin for dropping in Brute Force Protection to your controllers and methods. 

### Features
* IP address-based protection
* Uses cache to store attempts so no database installation necessary
* Logs blocked attempts (uses CakePHP Logs)
* Does not count re-attempts with same challenge details (e.g. if a user tries the same username/password combination a few times)
* Can block multiple attempts at the same username earlier than the normal limit (to give users a chance to enter the correct username if they have been trying with the wrong one)
* Can be applied in AppController::initialize for simpler set up when authentication plugins are used

### Requirements

* Composer
* CakePHP 4.0+
* PHP 7.2+

### Installation

In your CakePHP root directory: run the following command:

```
composer require ali1/cakephp-brute-force-protection
```

Then in your Application.php in your project root, add the following snippet:

```php
// In project_root/Application.php:
        $this->addPlugin('BruteForceProtection.BruteForceProtection');
```

or you can use the following shell command to enable to plugin in your bootstrap.php automatically:

```
bin/cake plugin load BruteForceProtection
```

### Basic Use

Load the component:
````php
// in AppController.php or any controller


// Either:
    public $components = ['BruteForceProtection.BruteForceProtection'];

// or:
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('BruteForceProtection.BruteForceProtection');
    }
````

Apply protection (`$this->BruteForceProtection->applyProtection` must come before actually verifying or actioning the user submitted data)

````php
    public function login(): void
    {
        $config = [];
        /**
         * @param string $name a unique string to store the data under (different $name for different uses of Brute
     *                          force protection within the same application.
         * @param array $keyNames an array of key names in the data that you intend to interrogate
         * @param array $data an array of data, can use $this->request->getData()
         * @param array $config options
         * @return void
         */
        $this->BruteForceProtection->applyProtection(
            'login',
            ['username', 'password'],
            $this->requst->getData(),
            $config,            
        );
        
        // the user will never get here if fails Brute Force Protection
        // usual login code here
    }
````

### Configuration Options

The fourth argument for `applyProtection` is the $config array argument.

|Configuration Key|Default Value|Details|
|---|---|---|
|timeWindow|300|Time in seconds until Brute Force Protection resets|
|totalAttemptsLimit|8|Number of attempts before user is blocked|
|firstKeyAttemptLimit|null|Integer if you further want to limit the number of attempts with the same first key (e.g. username) - see below for example|
|unencryptedKeyNames|[]|keysName for which the data will be stored unencrypted in cache (i.e. usernames)|
|flash|Login attempts have been blocked for a few minutes. Please try again later.|null for no Flash message|
|redirectUrl|null|null to redirect to self otherwise use a URL to go there once blocked|


### Usage

#### For a method for username / password BruteForce

```php
// UsersController.php
    public $components = ['BruteForceProtection.BruteForceProtection'];
    
    ...
    
    public function login()
    {
        // prior to actually verifying data
        $this->BruteForceProtection->applyProtection(
            'login', // unique name for this BruteForce action
            ['username', 'password'], // keys interrogated
            $this->request->getData(), // user entered data
            [
                'totalAttemptsLimit' => 10,
                'firstKeyAttemptLimit' => 7, // 7 attempts if same username, but then allow another 3 if user tries
                                            //different username
                'unencryptedKeyNames' => ['username'] // when storing users history, which is needed to ignore duplicate challenges, 
                                                        // not all data needs to be encrypted. Useful for monitoring/debugging.
            ], // options, see below
        );
        // login code
    }
```

#### Prevent URL based brute force

Non-form data can also be Brute Forced

````php
    /**
     * @param string|null $hashedid
     *
     * @return void
     */
    public function publicAuthUrl(?string $hashedid = null): void
    {
        if (!$hashedid) {
            // error or redirect
        }

        $this->BruteForceProtection->applyProtection(
            'publicHash',
            ['hashedid'],
            ['hashedid' => $hashedid],
            ['totalAttemptsLimit' => 5, 'unencryptedKeyNames' => ['hashedid'], 'redirectUrl' => '/'],
        );
        
        // then check if URL is actually valid
````

#### With user plugins (e.g. CakeDC/Users)

Although not ideal, when using plugins that you do not wish to extend, you can safely place the `applyProtection` method in AppController.php `initialize` method, since this will run prior to user verification within the plugin.

```php
// AppController.php::initialize()

        $this->loadComponent('BruteForceProtection.BruteForceProtection'); // Keep above any authentication components if running on initialize (default)
        $this->BruteForceProtection->applyProtection(
            'login', // unique name for this BruteForce action
            ['username', 'password'], // keys interrogated
            $this->request->getData(), // user entered data
            ['totalAttemptsLimit' => 10, 'firstKeyAttemptLimit' => 7, 'unencryptedKeyNames' => ['username']], // options, see below
        );
        // this will not affect any other action except ones containing the username and password data points in $this->request->getData()
```