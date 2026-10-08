<?php
// Request handling: session, security headers, plugins and routing between installer, admin and site.

function pb_handle_request(): void
{
    set_exception_handler('pb_handle_error');
    register_shutdown_function('pb_plugins_shutdown');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = pb_request_path();
    $installed = $GLOBALS['pb_config'] !== null;
    $isAdmin = $path === '/admin' || str_starts_with($path, '/admin/');

    // Visitors get no cookie at all; the panel needs one, and a logged-in owner keeps theirs while browsing the site.
    if (!$installed || $isAdmin || isset($_COOKIE['pagebrick'])) {
        pb_start_session();
    }
    if ($method === 'POST' && !$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        // PHP throws the whole form away when it passes post_max_size.
        http_response_code(413);
        exit(e(sprintf(__('O envio passou do limite da hospedagem (%s). Envie arquivos menores.'), ini_get('post_max_size'))));
    }
    // Every panel form carries a CSRF token, so this one check protects all of them.
    // Public forms added by plugins protect themselves (see the contact form's signed token).
    if ($method === 'POST' && (!$installed || $isAdmin) && !pb_csrf_valid($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        exit(e(__('Este formulário expirou. Volte, recarregue a página e tente de novo.')));
    }

    if (!$installed) {
        pb_set_locale(pb_install_locale());
        pb_install_page($method);
        return;
    }
    // New core files (an update, or files replaced by FTP) bring their schema changes along.
    // ponytail: two simultaneous first requests could both migrate; add a lock if that ever shows up in logs.
    if (pb_installed_version() < array_key_last(pb_migrations())) {
        pb_migrate();
    }
    register_shutdown_function('pb_auto_update_after_response'); // looks for updates now and then, after the answer
    // The panel speaks its user's language (before signing in, the browser's); the site always speaks the site's.
    $user = $isAdmin ? pb_current_user() : null;
    pb_set_locale(!empty($user['locale']) ? $user['locale'] : ($isAdmin ? pb_browser_locale(pb_site_locale()) : pb_site_locale()));
    if ($isAdmin && isset($_GET['socorro']) && hash_equals(pb_recovery_key(), pb_query('socorro'))) {
        $_SESSION['pb_safe_mode'] = true; // recovery link: this session runs without plugins
    }
    // First request after a PageBrick update: note how plugins and themes were, so a failed check can put them back.
    $statesBefore = pb_pending_core_update() !== null && !pb_safe_mode()
        ? ['plugins' => pb_option('plugins', '{}'), 'themes' => pb_option('themes', '{}')] : null;
    pb_load_plugins();
    if ($statesBefore !== null && pb_verify_core_update($statesBefore) !== null) {
        // The previous version is back on disk, but this request still runs the new code: start over.
        if ($method === 'GET') {
            pb_redirect($path, 303);
        }
        http_response_code(503);
        exit(e(__('O site foi atualizado e voltou para a versão anterior por segurança. Envie de novo, por favor.')));
    }

    $isAdmin ? pb_admin($method, $path) : pb_public($method, $path);
}

function pb_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    session_name('pagebrick');
    session_set_cookie_params([
        'path' => pb_base_path() ?: '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => pb_is_https(),
    ]);
    session_start();
}

function pb_handle_error(Throwable $e): void
{
    error_log((string) $e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo ($GLOBALS['pb_config']['debug'] ?? false)
        ? '<pre>' . e((string) $e) . '</pre>'
        : e(__('Algo deu errado. O erro foi registrado no log do servidor.'));
}
