# CakePHP Brute Force Protection Plugin

[![Framework](https://img.shields.io/badge/Framework-CakePHP%203.x-orange.svg)](http://cakephp.org)
[![license](https://img.shields.io/github/license/LeWestopher/cakephp-monga.svg?maxAge=2592000)](https://github.com/LeWestopher/cakephp-monga/blob/master/LICENSE)
[![Github All Releases](https://img.shields.io/packagist/dt/ali1/cakephp-brute-force-protection.svg?maxAge=2592000)](https://packagist.org/packages/ali1/cakephp-brute-force-protection)
[![Travis](https://img.shields.io/travis/ali1/cakephp-brute-force-protection.svg?maxAge=2592000)](https://travis-ci.org/ali1/cakephp-brute-force-protection)
[![Coverage Status](https://coveralls.io/repos/github/ali1/cakephp-brute-force-protection/badge.svg)](https://coveralls.io/github/ali1/cakephp-brute-force-protection)

A CakePHP plugin for dropping in Brute Force Protection to your controllers and methods. 

### Features
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
        $this->addPlugin('BruteForceProtection');
```

or you can use the following shell command to enable to plugin in your bootstrap.php automatically:

```
bin/cake plugin load BruteForceProtection
```

### Functions

````php
    /**
     * @param array $config
     * @param array $keyNames the key names in the data whose combinations will be checked
     * @param array $data can use $this->request->getData() or any other array, or for BruteForce of single
     *                              value, you can enter a string alone
     * @param array $config options
     * @return void
     */
    public function applyProtection(string $name, array $keyNames, array $data, array $config = [])
````

### Configuration Options

The fourth argument for `applyProtection` is the $config argument.

````php
    public $_defaultConfig = [
        'timeWindow' => 300, // 5 minutes
        'totalAttemptsLimit' => 8,
        'firstKeyAttemptLimit' => null, // use integer smaller than totalAttemptsLimit to make tighter restrictions on
        //                                  repeated tries on first key (i.e. 5 tries with a single username, but then
        //                                  can try a few more times if realises the username was wrong
        'unencryptedKeyNames' => [], // keysName for which the data will be stored unencrypted in cache (i.e. usernames)
        'flash' => 'Login attempts have been blocked for a few minutes. Please try again later.', // null for no Flash
        'redirectUrl' => null, // redirect to self
    ];
````

### Usage

#### For a method for username / password BruteForce

```php
// UsersController.php
    public $components = ['BruteForceProtection'];
    
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

#### Other uses

Designed to work with other data but no examples available.
