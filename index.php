<?php
// PageBrick front controller: every request comes through here (see .htaccess).
define('PB_ROOT', __DIR__);

// Safety net for updates. If a PageBrick version installed in the last hour can't even start (a fatal error
// in core/ or vendor/), put the previous version back from the backup the update made, and ask for a reload.
// Written without any core function on purpose: the core is what failed.
register_shutdown_function(function () {
    $error = error_get_last();
    $marker = PB_ROOT . '/content/backups/update-pending.json';
    if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true) || !is_file($marker)) {
        return;
    }
    $file = str_replace('\\', '/', $error['file']);
    $root = str_replace('\\', '/', PB_ROOT);
    if (!str_starts_with($file, "$root/core/") && !str_starts_with($file, "$root/vendor/") && $file !== "$root/index.php") {
        return; // a plugin or a theme: their own circuit breakers handle it
    }
    $pending = json_decode((string) file_get_contents($marker), true);
    $backup = realpath((string) ($pending['backup'] ?? ''));
    if (($pending['until'] ?? 0) < time() || !$backup || !str_starts_with(str_replace('\\', '/', $backup), "$root/content/backups/")) {
        return;
    }
    $zip = new ZipArchive();
    if ($zip->open($backup) !== true) {
        return;
    }
    $tmp = PB_ROOT . '/.restore-' . bin2hex(random_bytes(4));
    $zip->extractTo($tmp);
    $zip->close();
    foreach (['core', 'vendor'] as $folder) {
        if (is_dir("$tmp/pagebrick/$folder")) {
            if (is_dir(PB_ROOT . "/$folder")) {
                rename(PB_ROOT . "/$folder", "$tmp/broken-$folder");
            }
            rename("$tmp/pagebrick/$folder", PB_ROOT . "/$folder");
        }
    }
    copy("$tmp/pagebrick/index.php", PB_ROOT . '/index.php');
    unlink($marker);
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($tmp);
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    echo '<p>A atualização do PageBrick deu erro e a versão anterior foi restaurada automaticamente. Recarregue a página.</p>';
});

require PB_ROOT . '/core/bootstrap.php';
pb_handle_request();
