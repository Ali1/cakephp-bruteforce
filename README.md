# CakePHP Brute Force Plugin

[![Framework](https://img.shields.io/badge/Framework-CakePHP%204.x-orange.svg)](http://cakephp.org)
[![license](https://img.shields.io/github/license/ali1/cakephp-bruteforce.svg?maxAge=2592000)](/blob/master/LICENSE)
[![Build Status](https://travis-ci.org/Ali1/cakephp-bruteforce.svg?branch=master)](https://travis-ci.org/Ali1/cakephp-bruteforce)
[![Coverage Status](https://coveralls.io/repos/github/Ali1/cakephp-bruteforce/badge.svg?branch=master)](https://coveralls.io/github/Ali1/cakephp-bruteforce?branch=master)

A CakePHP plugin for easy drop-in Brute Force Protection for your controller methods.

Component Wrapper for [Ali1/BruteForceShield](https://github.com/Ali1/BruteForceShield)

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

### Installation

In your CakePHP root directory: run the following command:

```
composer require ali1/cakephp-bruteforce
```

Then in your Application.php in your project root, add the following snippet:

```php
// In project_root/Application.php:
        $this->addPlugin('Bruteforce');
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
    }
````

Apply protection (`$this->Bruteforce->validate` must come before actually verifying or actioning the user submitted data)

````php
    public function login(): void
    {
        $config = new \Ali1\BruteForceShield\Configuration(); // see possible options below

        /**
         * @param string $name a unique string to store the data under (different $name for different uses of Brute
     *                          force protection within the same application.
         * @param array $data an array of data, can use $this->request->getData()
         * @param \Ali1\BruteForceShield\Configuration|null $config options
         * @param string $cache Cache to use (default: 'default'). Make sure to use one with a duration longer than your time window otherwise you will not be protected.
         * @return void
         */
        $this->Bruteforce->validate(
            'login',
            ['username' => $this->request->getData('username'), 'password' => $this->request->getData('password')],
            $config,
            'default'          
        );
        
        // the user will never get here if fails Brute Force Protection
        // a TooManyAttemptsException will be thrown
        // usual login code here
    }
````

### Configuration Options

The third argument for `validate` is the \Ali1\BruteForceShield\Configuration object.

Instructions on configuring Brute Force Protection can be found [here](https://github.com/Ali1/BruteForceShield#configuration).

### Usage

#### For a method for username / password BruteForce

```php
// UsersController.php
    public $components = ['Bruteforce.Bruteforce'];
    
    ...
    
    public function login()
    {
        // prior to actually verifying data
        $bruteConfig = new Configuration();
        $bruteConfig->setTotalAttemptsLimit(10);
        $bruteConfig->setStricterLimitOnKey('username', 7);
        $bruteConfig->addUnencryptedKey('username');

        $this->Bruteforce->validate(
            'login', // unique name for this BruteForce action
            ['username' => $this->request->getData('username'), 'password' => $this->request->getData('password')],
            $bruteConfig
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
            $bruteConfig = new Configuration();
            $bruteConfig->addUnencryptedKey('hashedid');
            $this->Bruteforce->validate(
                'publicHash',
                ['hashedid' => $hashedid],
                $bruteConfig
            );
        } catch (\Bruteforce\Exception\TooManyAttemptsException $e) {
            $this->Flash->error('Too many requests attempted. Please try again in a few minutes');
            return $this->redirect('/');
        }
        
        // then check if URL is actually valid
````

#### With user plugins (e.g. CakeDC/Users)

Although not ideal, when using plugins that you do not wish to extend or modify, you can safely place the `validate` method in AppController.php `initialize` method, since this will run prior to user verification within the plugin.

```php
// AppController.php::initialize()

        $this->loadComponent('Bruteforce.Bruteforce'); // Keep above any authentication components if running on initialize (default)
        $this->Bruteforce->validate(
            'login', // unique name for this BruteForce action
            ['username' => $this->request->getData('username'), 'password' => $this->request->getData('password')] // user entered data
        );
        // this will not affect any other action except ones containing POSTed usernames and passwords (empty challenges never get counted or blocked)
```
