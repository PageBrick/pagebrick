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

function pb_is_https(): bool
{
    return ($_SERVER['HTTPS'] ?? '') === 'on' || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

/** Full address (https://site.com/path). Uses the address saved at install, not the request's Host header. */
function pb_absolute_url(string $path = ''): string
{
    $origin = pb_option('site_url') ?? ((pb_is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . pb_base_path());
    return rtrim($origin, '/') . '/' . ltrim($path, '/');
}

/**
 * Checks the site's official address (Appearance & contact) and returns it without the final slash.
 * Throws with a message the user can read. Only absolute links use it, so a typo never locks anyone out.
 */
function pb_validate_site_url(string $url): string
{
    $url = rtrim(trim($url), '/');
    $parts = parse_url($url) ?: [];
    if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
        || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user'])) {
        throw new InvalidArgumentException(__('Informe o endereço completo do site, começando com https:// ou http://.'));
    }
    return $url;
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

function pb_redirect(string $path, int $status = 302): never
{
    header('Location: ' . pb_url($path), true, $status);
    exit;
}

/** Reads a POST field as a string; anything else (arrays, missing) becomes ''. */
function pb_post(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
}

/** Reads a query string parameter as a string. */
function pb_query(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? $value : '';
}

function pb_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * A request to another service, for plugins that connect to one (an API, a feed). Returns ['status' => 200, 'body' => '…']
 * whatever the status is, so the plugin decides what a 403 means; throws RuntimeException when it can't connect or the
 * answer is over 2 MB, and InvalidArgumentException for an address that isn't https:// or a header with a line break.
 * $headers is ['Name' => 'value']; giving $body makes it a POST. Tests can set $GLOBALS['pb_config']['http'] to a
 * function ($url, $headers, $timeout, $body): array that answers instead of the network.
 */
function pb_http(string $url, array $headers = [], int $timeout = 8, ?string $body = null): array
{
    if (!preg_match('~^https://~i', $url) && empty($GLOBALS['pb_config']['allow_insecure_urls'])) {
        throw new InvalidArgumentException(__('Endereço inseguro: só https é aceito.'));
    }
    $lines = [];
    foreach ($headers as $name => $value) {
        if (!preg_match('/^[A-Za-z0-9-]+$/', (string) $name) || preg_match('/[\r\n]/', (string) $value)) {
            throw new InvalidArgumentException(__('Cabeçalho inválido.'));
        }
        $lines[] = "$name: $value";
    }
    if (is_callable($GLOBALS['pb_config']['http'] ?? null)) {
        return ($GLOBALS['pb_config']['http'])($url, $headers, $timeout, $body);
    }
    $context = stream_context_create(['http' => ['method' => $body === null ? 'GET' : 'POST', 'header' => implode("\r\n", $lines), 'content' => $body ?? '',
        'timeout' => max(1, min($timeout, 60)), 'ignore_errors' => true, 'follow_location' => 1, 'max_redirects' => 3, 'user_agent' => 'PageBrick/' . PB_VERSION]]);
    $max = 2 * 1024 * 1024;
    $answer = @file_get_contents($url, false, $context, 0, $max + 1);
    // With redirects the status lines pile up: the last one is the answer.
    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $m)) {
            $status = (int) $m[1];
        }
    }
    if ($answer === false || $status === 0) {
        throw new RuntimeException(sprintf(__('Não consegui conectar a %s.'), (string) parse_url($url, PHP_URL_HOST)));
    }
    if (strlen($answer) > $max) {
        throw new RuntimeException(__('A resposta é grande demais.'));
    }
    return ['status' => $status, 'body' => $answer];
}

/** Cuts text to $max characters without breaking UTF-8. */
function pb_limit(string $text, int $max): string
{
    return preg_match('/^.{0,' . $max . '}/su', $text, $m) ? $m[0] : '';
}

/** Signs a value with the site's secret, so it can travel through a form and be trusted when it comes back. */
function pb_sign(string $value): string
{
    $secret = pb_option('secret');
    if ($secret === null) {
        $secret = bin2hex(random_bytes(32));
        pb_set_option('secret', $secret);
    }
    return hash_hmac('sha256', $value, $secret);
}

function pb_signature_valid(string $value, mixed $signature): bool
{
    return is_string($signature) && hash_equals(pb_sign($value), $signature);
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
    $vars['content'] = pb_include(PB_ROOT . "/core/views/$view.php", $vars);
    echo pb_include(PB_ROOT . '/core/views/layout.php', $vars);
}

/** Runs a PHP template with $vars as local variables and returns its output. */
function pb_include(string $file, array $vars): string
{
    $file = pb_template_file($file);
    extract($vars, EXTR_SKIP);
    ob_start();
    try {
        require $file;
        return ob_get_clean();
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
}
