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
* CakePHP 3.7+
* PHP 5.4+

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

### Usage

#### For a method for username / password BruteForce

```php
// UsersController.php
    public $components = ['BruteForceProtection'];
    
    ...
    
    public function login()
    {
        $this->BruteForceProtection->applyProtection([
            'name' => 'login',
            'firstKeyAttemptLimit' => 5, // 5 attempts maximum per username
            'totalAttemptsLimit' => 10,  // but 10 if trying different usernames
            'keyNames' => ['username', 'password'],
            'security' => 'firstKeyUnsecure', // in logs, store the raw username, but not the password
        ]);
        // login login
    }
```

#### With CakeDC/users Plugin

Although not ideal, when using plugins that you do not wish to extend, you can safely place the `applyProtection` method in AppController.php.

```php
// AppController.php::initialize()

        $this->loadComponent('BruteForceProtection.BruteForceProtection'); // Keep above any authentication components if running on initialize (default)
        $this->BruteForceProtection->applyProtection([
            'name' => 'login',
            'firstKeyAttemptLimit' => 5, // 5 attempts maximum per username
            'totalAttemptsLimit' => 10,  // but 10 if trying different usernames
            'keyNames' => ['username', 'password'],
            'security' => 'firstKeyUnsecure',
        ]);

```

#### Other uses

Designed to work with other data but no examples available.
