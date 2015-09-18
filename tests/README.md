# Yii2 Materialized Path Behavior unit tests

## How to run the test

Make sure you have PHPUnit installed and that you installed all composer dependencies (run `composer update` in the repo base directory).

Run PHPUnit in the yii repo base directory.

```
phpunit
```

You can run tests for specific groups only:

```
phpunit --group=sqlite,mysql
```

You can get a list of available groups via `phpunit --list-groups`.

## test configurations

PHPUnit configuration is in `phpunit.xml.dist` in repository root folder.
You can create your own phpunit.xml to override dist config.

Database and other backend system configuration can be found in `tests/data/config.php`
adjust them to your needs to allow testing databases and caching in your environment.
You can override configuration values by creating a `config.local.php` file
and manipulate the `$config` variable.
For example to change MySQL username and password your `config.local.php` should
contain the following:

```php
<?php
$config['mysql']['username'] = 'username';
$config['mysql']['password'] = 'password';
```