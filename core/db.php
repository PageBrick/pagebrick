<?php
// Database access, schema migrations and site options.

function pb_db_connect(array $db): PDO
{
    return new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function pb_db(): PDO
{
    return $GLOBALS['pb_db'] ??= pb_db_connect($GLOBALS['pb_config']['db']);
}

/** Table name with the installation's prefix, e.g. 'users' => 'pb_users'. */
function pb_table(string $name): string
{
    return $GLOBALS['pb_config']['db']['prefix'] . $name;
}

/**
 * Schema changes, in order. Never edit a released migration: add a new number.
 * Core updates run the pending ones (see pb_migrate).
 */
function pb_migrations(): array
{
    $table = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        1 => [
            'CREATE TABLE ' . pb_table('options') . " (
                name VARCHAR(190) PRIMARY KEY,
                value MEDIUMTEXT NOT NULL
            ) $table",
            'CREATE TABLE ' . pb_table('users') . " (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(190) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(20) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) $table",
            'CREATE TABLE ' . pb_table('login_attempts') . " (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(45) NOT NULL,
                email VARCHAR(190) NOT NULL,
                attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY ip_email (ip, email, attempted_at),
                KEY attempted_at (attempted_at)
            ) $table",
        ],
        2 => [
            'CREATE TABLE ' . pb_table('pages') . " (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(200) NOT NULL,
                slug VARCHAR(190) NOT NULL,
                locale VARCHAR(10) NOT NULL DEFAULT 'pt-BR',
                template VARCHAR(60) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                data MEDIUMTEXT NOT NULL,
                seo_title VARCHAR(200) NOT NULL DEFAULT '',
                seo_description VARCHAR(300) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY locale_slug (locale, slug)
            ) $table",
            'CREATE TABLE ' . pb_table('page_revisions') . " (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                page_id INT UNSIGNED NOT NULL,
                snapshot MEDIUMTEXT NOT NULL,
                user_id INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY page_id (page_id)
            ) $table",
            'CREATE TABLE ' . pb_table('media') . " (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                path VARCHAR(255) NOT NULL,
                thumb_path VARCHAR(255) NOT NULL DEFAULT '',
                original_name VARCHAR(255) NOT NULL,
                mime VARCHAR(100) NOT NULL,
                width INT UNSIGNED NOT NULL DEFAULT 0,
                height INT UNSIGNED NOT NULL DEFAULT 0,
                size INT UNSIGNED NOT NULL DEFAULT 0,
                alt VARCHAR(255) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) $table",
        ],
        // Only ADD in migrations (tables, columns with defaults): an update that is undone leaves the database
        // ahead of the code, and the previous version must keep working with it.
        3 => [
            'ALTER TABLE ' . pb_table('users') . ' ADD COLUMN locale VARCHAR(10) NULL',
        ],
        // Translations: a page in an extra language points to the page it translates (null: the main language).
        4 => [
            'ALTER TABLE ' . pb_table('pages') . ' ADD COLUMN translation_of INT UNSIGNED NULL',
        ],
        // "Forgot my password": one live link per user, kept only as a hash.
        5 => [
            'CREATE TABLE ' . pb_table('password_resets') . " (
                user_id INT UNSIGNED PRIMARY KEY,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                KEY token_hash (token_hash)
            ) $table",
        ],
        // The panel's language no longer follows the site's: accounts that never chose one keep the one they see now.
        6 => [
            'UPDATE ' . pb_table('users') . " SET locale = COALESCE((SELECT value FROM " . pb_table('options') . " WHERE name = 'locale'), 'pt-BR')
                WHERE locale IS NULL OR locale = ''",
        ],
    ];
}

/** Schema version of this database; 0 when PageBrick isn't installed in it. */
function pb_installed_version(): int
{
    try {
        return (int) pb_option('db_version');
    } catch (PDOException) {
        return 0; // options table doesn't exist yet
    }
}

function pb_migrate(): void
{
    $current = pb_installed_version();
    foreach (pb_migrations() as $version => $statements) {
        if ($version <= $current) {
            continue;
        }
        // MySQL commits DDL implicitly, so each migration is recorded right after it runs.
        foreach ($statements as $sql) {
            pb_db()->exec($sql);
        }
        pb_set_option('db_version', (string) $version);
    }
}

function pb_option(string $name, ?string $default = null): ?string
{
    $st = pb_db()->prepare('SELECT value FROM ' . pb_table('options') . ' WHERE name = ?');
    $st->execute([$name]);
    $value = $st->fetchColumn();
    return $value === false ? $default : $value;
}

function pb_delete_option(string $name): void
{
    pb_db()->prepare('DELETE FROM ' . pb_table('options') . ' WHERE name = ?')->execute([$name]);
}

function pb_set_option(string $name, string $value): void
{
    pb_db()->prepare('INSERT INTO ' . pb_table('options') . ' (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?')
        ->execute([$name, $value, $value]);
}
