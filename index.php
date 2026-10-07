<?php
// PageBrick front controller: every request comes through here (see .htaccess).
define('PB_ROOT', __DIR__);
require PB_ROOT . '/core/bootstrap.php';
pb_handle_request();
