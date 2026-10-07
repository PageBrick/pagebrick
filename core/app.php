<?php
// Request handling: session, security headers and routing between installer, admin and site.

function pb_handle_request(): void
{
    set_exception_handler('pb_handle_error');
    pb_start_session();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    // Every form carries a CSRF token, so this one check protects all of them.
    if ($method === 'POST' && !pb_csrf_valid($_POST['_csrf'] ?? null)) {
        http_response_code(400);
        exit(e(__('Este formulário expirou. Volte, recarregue a página e tente de novo.')));
    }

    $path = pb_request_path();
    if ($GLOBALS['pb_config'] === null) {
        pb_install_page($method);
    } elseif ($path === '/admin' || str_starts_with($path, '/admin/')) {
        pb_admin($method, $path);
    } else {
        pb_site($path);
    }
}

function pb_start_session(): void
{
    ini_set('session.use_strict_mode', '1');
    session_name('pagebrick');
    session_set_cookie_params([
        'path' => pb_base_path() ?: '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['SERVER_PORT'] ?? '') === '443',
    ]);
    session_start();
}

// ponytail: placeholder until themes arrive in stage 2.
function pb_site(string $path): void
{
    if ($path !== '/') {
        http_response_code(404);
    }
    pb_render('message', [
        'title' => pb_option('site_title', 'PageBrick'),
        'message' => __('Este site roda com PageBrick. As páginas chegam em breve.'),
    ]);
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
