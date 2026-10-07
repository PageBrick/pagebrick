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

function pb_set_option(string $name, string $value): void
{
    pb_db()->prepare('INSERT INTO ' . pb_table('options') . ' (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?')
        ->execute([$name, $value, $value]);
}
