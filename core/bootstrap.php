<?php
// Loads the PageBrick core. Every entry point (index.php, tests) starts here.

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    exit('PageBrick requer PHP 8.2 ou mais novo.');
}

const PB_VERSION = '1.0.1';

if (is_file(PB_ROOT . '/vendor/autoload.php')) {
    require PB_ROOT . '/vendor/autoload.php'; // PHPMailer
}
require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/i18n.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/fields.php';
require __DIR__ . '/media.php';
require __DIR__ . '/standard.php';
require __DIR__ . '/content.php';
require __DIR__ . '/theme.php';
require __DIR__ . '/headless.php';
require __DIR__ . '/plugins.php';
require __DIR__ . '/packages.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/install.php';
require __DIR__ . '/admin.php';
require __DIR__ . '/app.php';

// null means "not installed yet": every request goes to the installer.
$GLOBALS['pb_config'] = is_file(PB_ROOT . '/config.php') ? require PB_ROOT . '/config.php' : null;
