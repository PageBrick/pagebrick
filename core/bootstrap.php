<?php
// Loads the PageBrick core. Every entry point (index.php, tests) starts here.

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    exit('PageBrick requer PHP 8.2 ou mais novo.');
}

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/install.php';
require __DIR__ . '/admin.php';
require __DIR__ . '/app.php';

// null means "not installed yet": every request goes to the installer.
$GLOBALS['pb_config'] = is_file(PB_ROOT . '/config.php') ? require PB_ROOT . '/config.php' : null;
