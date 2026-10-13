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

/** Config for tests that need an installed site: debug on (templates warn about unknown fields), e-mails kept in memory. */
function pb_test_config(array $extra = []): array
{
    return $extra + ['db' => pb_test_db(), 'debug' => true, 'uploads_dir' => sys_get_temp_dir() . '/pb-test-uploads', 'mail' => 'memory'];
}

/** What the next request would see: hooks, routes and screens are registered again by the active plugins. */
function pb_test_new_request(): void
{
    foreach (['pb_hooks', 'pb_routes', 'pb_admin_pages', 'pb_plugin_settings', 'pb_plugin_migrations', 'pb_failed_plugins',
                 'pb_running_plugin', 'pb_current_page', 'pb_page_title', 'pb_sent_mail', 'pbcf_used', 'pbcf_state',
                 'pb_theme', 'pb_public_request'] as $global) {
        unset($GLOBALS[$global]);
    }
}

function pb_test_copy_dir(string $from, string $to): void
{
    mkdir($to, 0777, true);
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($items as $item) {
        $target = $to . '/' . substr($item->getPathname(), strlen($from) + 1);
        $item->isDir() ? mkdir($target) : copy($item->getPathname(), $target);
    }
}

/** Recreates an empty test database and forgets any connection: each test starts from zero. */
function pb_test_fresh_db(): void
{
    $c = pb_test_db();
    $server = new PDO("mysql:host={$c['host']}", $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $server->exec("DROP DATABASE IF EXISTS `{$c['name']}`");
    $server->exec("CREATE DATABASE `{$c['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $GLOBALS['pb_config'] = null;
    unset($GLOBALS['pb_db'], $GLOBALS['pb_theme']);
    pb_test_new_request();
    $_SESSION = [];
    $_GET = [];
    $_POST = [];
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['REQUEST_URI'] = '/';
}
