<?php
define('PB_ROOT', dirname(__DIR__));
require PB_ROOT . '/core/bootstrap.php';

/** Test database. Defaults match docker-compose.yml; CI overrides them with env vars. */
function pb_test_db(): array
{
    return [
        'host' => getenv('PB_TEST_DB_HOST') ?: 'db',
        'name' => getenv('PB_TEST_DB_NAME') ?: 'pagebrick_test',
        'user' => getenv('PB_TEST_DB_USER') ?: 'root',
        'pass' => getenv('PB_TEST_DB_PASS') ?: 'root',
        'prefix' => 'pb_',
    ];
}

/** Recreates an empty test database and forgets any connection: each test starts from zero. */
function pb_test_fresh_db(): void
{
    $c = pb_test_db();
    $server = new PDO("mysql:host={$c['host']}", $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $server->exec("DROP DATABASE IF EXISTS `{$c['name']}`");
    $server->exec("CREATE DATABASE `{$c['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $GLOBALS['pb_config'] = null;
    unset($GLOBALS['pb_db']);
    $_SESSION = [];
}
