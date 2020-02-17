# CakePHP Brute Force Plugin

[![Framework](https://img.shields.io/badge/Framework-CakePHP%204.x-orange.svg)](http://cakephp.org)
[![license](https://img.shields.io/github/license/LeWestopher/cakephp-monga.svg?maxAge=2592000)](/blob/master/LICENSE)
[![Github All Releases](https://img.shields.io/packagist/dt/ali1/cakephp-brute-force-protection.svg?maxAge=2592000)](https://packagist.org/packages/ali1/cakephp-bruteforce)
[![Travis](https://img.shields.io/travis/ali1/cakephp-brute-force-protection.svg?maxAge=2592000)](https://travis-ci.org/ali1/cakephp-bruteforce)
[![Coverage Status](https://coveralls.io/repos/github/ali1/cakephp-brute-force-protection/badge.svg)](https://coveralls.io/github/ali1/cakephp-bruteforce)

A CakePHP plugin for easy drop-in Brute Force Protection for your controller methods. 

### Features
* IP address-based protection
* Uses the Cache class to store attempts so no database installation necessary
* Logs blocked attempts (uses CakePHP Logs)
* Does not count re-attempts with same challenge details (e.g. if a user tries the same username/password combination a few times)
* Can block multiple attempts at the same username earlier than the normal limit (to give users a chance to enter the correct username if they have been trying with the wrong one)
* Can be applied in AppController::initialize for simpler set up when authentication plugins are used
* Throws catchable exception which can optionally be caught

### Requirements

* Composer
* CakePHP 4.0+
* PHP 7.2+
* Cache

### Installation

In your CakePHP root directory: run the following command:

```
composer require ali1/cakephp-bruteforce
```

Then in your Application.php in your project root, add the following snippet:

```php
// In project_root/Application.php:
        $this->addPlugin('Bruteforce.Bruteforce');
```

or you can use the following shell command to enable to plugin in your bootstrap.php automatically:

```
bin/cake plugin load Bruteforce
```

### Basic Use

Load the component:
````php
// in AppController.php or any controller

    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Bruteforce.Bruteforce');

        // or with configuration
        $this->loadComponent('Bruteforce.Bruteforce', ['cacheName' => 'Bruteforce']);
    }
````

Apply protection (`$this->Bruteforce->applyProtection` must come before actually verifying or actioning the user submitted data)

````php
    public function login(): void
    {
        $config = []; // see possible options below

        /**
         * @param string $name a unique string to store the data under (different $name for different uses of Brute
     *                          force protection within the same application.
         * @param array $keyNames an array of key names in the data that you intend to interrogate
         * @param array $data an array of data, can use $this->request->getData()
         * @param array $config options
         * @return void
         */
        $this->Bruteforce->applyProtection(
            'login',
            ['username', 'password'],
            $this->requst->getData(),
            $config,            
        );
        
        // the user will never get here if fails Brute Force Protection
        // a TooManyAttemptsException will be thrown
        // usual login code here
    }
````

### Configuration Options

The fourth argument for `applyProtection` is the $config array argument.

|Configuration Key|Default Value|Details|
|---|---|---|
|cacheName|default|The CakePHP Cache configuration to use. Make sure to use one with a duration longer than your time window otherwise you will not be protected.|
|timeWindow|300|Time in seconds until Brute Force Protection resets|
|totalAttemptsLimit|8|Number of attempts before user is blocked|
|unencryptedKeyNames|[]|keysName for which the data will be stored unencrypted in cache (i.e. usernames)|
|firstKeyAttemptLimit|null|Integer if you further want to limit the number of attempts with the same first key (good for username and password - set this to 5 and totalAttemptsLimit to 10 to allow 5 attempts, and then another 5 if user tries a different username)|


### Usage

#### For a method for username / password BruteForce

```php
// UsersController.php
    public $components = ['Bruteforce.Bruteforce'];
    
    ...
    
    public function login()
    {
        // prior to actually verifying data
        $this->Bruteforce->applyProtection(
            'login', // unique name for this BruteForce action
            ['username', 'password'], // keys interrogated
            $this->request->getData() // user entered data
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
    public function publicAuthUrl(string $hashedid): void
    {
        try {
            $this->Bruteforce->applyProtection(
                'publicHash',
                ['hashedid'],
                ['hashedid' => $hashedid],
                [
                    'unencryptedKeyNames' => ['hashedid'] // so that you can see what has been attempted in logs/cache
                ]
            );
        } catch (\Bruteforce\Exception\TooManyAttemptsException $e) {
            $this->Flash->error('Too many requests attempted. Please try again in a few minutes');
            return $this->redirect('/');
        }
        
        // then check if URL is actually valid
````

#### With user plugins (e.g. CakeDC/Users)

Although not ideal, when using plugins that you do not wish to extend, you can safely place the `applyProtection` method in AppController.php `initialize` method, since this will run prior to user verification within the plugin.

```php
// AppController.php::initialize()

        $this->loadComponent('Bruteforce.Bruteforce'); // Keep above any authentication components if running on initialize (default)
        $this->Bruteforce->applyProtection(
            'login', // unique name for this BruteForce action
            ['username', 'password'], // keys interrogated
            $this->request->getData(), // user entered data
            ['totalAttemptsLimit' => 10, 'firstKeyAttemptLimit' => 7, 'unencryptedKeyNames' => ['username']], // options, see below
        );
        // this will not affect any other action except ones containing the username and password data points in $this->request->getData()
```
