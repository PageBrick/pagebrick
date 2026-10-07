<?php
// Small helpers shared by the core, themes and plugins.

/** Escapes text for HTML output. Use it on everything that didn't come from your own code. */
function e(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Translates an interface text. Source texts are in Portuguese; translations are loaded into $GLOBALS['pb_translations']. */
function __(string $text): string
{
    return $GLOBALS['pb_translations'][$text] ?? $text;
}

/** Folder PageBrick is installed in, relative to the domain ('' at the root, '/site' in a subfolder). */
function pb_base_path(): string
{
    return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
}

function pb_url(string $path = ''): string
{
    return pb_base_path() . '/' . ltrim($path, '/');
}

/** Current path without the install folder and query string, e.g. '/admin/users'. */
function pb_request_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = pb_base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    return '/' . trim($path, '/');
}

function pb_redirect(string $path): never
{
    header('Location: ' . pb_url($path));
    exit;
}

/** Reads a POST field as a string; anything else (arrays, missing) becomes ''. */
function pb_post(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
}

function pb_csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function pb_csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(pb_csrf_token()) . '">';
}

function pb_csrf_valid(mixed $token): bool
{
    return is_string($token) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/** Queues a message for the next page ('ok' or 'error'). */
function pb_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function pb_take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/** Renders core/views/$view.php inside the admin layout. */
function pb_render(string $view, array $vars = []): void
{
    $vars['content'] = pb_capture($view, $vars);
    echo pb_capture('layout', $vars);
}

function pb_capture(string $view, array $vars): string
{
    extract($vars, EXTR_SKIP);
    ob_start();
    require PB_ROOT . "/core/views/$view.php";
    return ob_get_clean();
}
