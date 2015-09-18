<?php
$config = [
    'sqlite' => [
        'dsn' => 'sqlite::memory:',
    ],
    'mysql' => [
        'dsn' => 'mysql:host=localhost;dbname=test',
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8',
    ],
    'mssql' => [
        'dsn' => 'sqlsrv:Server=localhost;Database=test',
        'username' => '',
        'password' => '',
    ],
    'pgsql' => [
        'dsn' => 'pgsql:host=localhost;dbname=test;port=5432;',
        'username' => 'postgres',
        'password' => 'postgres',
    ],
];

if (is_file(__DIR__ . '/config.local.php')) {
    include(__DIR__ . '/config.local.php');
}

return $config;