<?php
// Plugins: discovery, activation, hooks and the circuit breakers.
//
// A plugin is a folder in content/plugins/ with:
//   plugin.json  {"name": "...", "version": "1.0.0", "description": "...", "author": "...", "api": 1}
//   plugin.php   runs on every request while the plugin is active, and registers what it adds:
//
//   pb_add_action('init', fn() => ...)                 run code at a moment of the request
//   pb_add_filter('head_html', fn($html) => $html.'…')  change a value (must return the same type)
//   pb_add_filter('slot:contact', fn($html) => …)      add HTML where a theme calls pb_slot('contact') (also 'slot:home')
//   pb_add_filter('head_html' | 'footer_html', …)       tags in <head> / before </body> (stylesheets, scripts)
//   pb_add_filter('sitemap_urls', fn($urls) => …)      add [path, last change] entries to sitemap.xml
//   pb_add_filter('link_targets', fn($t) => $t + ['/blog' => 'Blog'])   addresses offered in menus and buttons
//   pb_add_route('POST', '/meu-plugin/enviar', fn() => …)   a public address ('/noticias/*' matches a prefix)
//   pb_add_admin_page('mensagens', 'Mensagens', fn() => …)  a screen in the panel menu
//   pb_plugin_settings([...fields])                    a settings screen built from fields
//   pb_plugin_migrations([1 => ['CREATE TABLE …']])    the plugin's own tables
//
// Every piece of plugin code runs inside pb_run_plugin(): if it throws, returns the wrong type,
// or kills PHP with a fatal error, only that plugin is switched off and the site keeps working.
// This protects against broken plugins, not malicious ones: only install code from sources you trust
// (the official catalog, whose packages are signed, or a .zip from someone you know).

const PB_API_VERSION = 1;

function pb_plugins_dir(): string
{
    return $GLOBALS['pb_config']['plugins_dir'] ?? PB_ROOT . '/content/plugins';
}

/** Plugins on disk: slug => manifest. A plugin that can't be used gets a 'problem' message. */
function pb_plugins_available(): array
{
    $plugins = [];
    foreach (glob(pb_plugins_dir() . '/*/plugin.json') ?: [] as $file) {
        $slug = basename(dirname($file));
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', $slug)) {
            continue;
        }
        $manifest = json_decode((string) file_get_contents($file), true);
        $plugin = (is_array($manifest) ? $manifest : []) + ['name' => $slug, 'version' => '', 'description' => '', 'author' => '', 'api' => 0];
        $plugin['name'] = pb_manifest_text($plugin, 'name');
        $plugin['description'] = pb_manifest_text($plugin, 'description');
        if (!is_array($manifest) || !is_string($manifest['name'] ?? null)) {
            $plugin['problem'] = __('O arquivo plugin.json está com defeito.');
        } elseif (!is_file(dirname($file) . '/plugin.php')) {
            $plugin['problem'] = __('Falta o arquivo plugin.php.');
        } elseif ((int) $plugin['api'] !== PB_API_VERSION) {
            $plugin['problem'] = sprintf(__('Feito para outra versão do PageBrick (API %s; esta versão usa a API %d).'), (string) $plugin['api'], PB_API_VERSION);
        }
        $plugins[$slug] = $plugin;
    }
    ksort($plugins);
    return $plugins;
}

/** Saved state per plugin: ['active' => bool, 'db' => schema version, 'error' => ?string, 'error_at' => ?string]. */
function pb_plugin_states(): array
{
    return json_decode(pb_option('plugins', '{}'), true) ?: [];
}

// ponytail: read-modify-write of one option; two plugins failing in the same instant could lose one record. Move to a table if that shows up.
function pb_set_plugin_state(string $slug, array $changes): void
{
    $states = pb_plugin_states();
    $states[$slug] = array_merge($states[$slug] ?? ['active' => false, 'db' => 0, 'error' => null, 'error_at' => null], $changes);
    pb_set_option('plugins', json_encode($states, JSON_UNESCAPED_UNICODE));
}

// ------------------------------------------------------------------ safe mode

/**
 * Safe mode: no plugin runs. Turned on for one session by the recovery link (shown in the panel),
 * or for everyone with 'safe_mode' => true in config.php (for when only FTP access is left).
 */
function pb_safe_mode(): bool
{
    return !empty($GLOBALS['pb_config']['safe_mode']) || !empty($_SESSION['pb_safe_mode']);
}

function pb_recovery_key(): string
{
    $key = pb_option('recovery_key');
    if ($key === null) {
        $key = bin2hex(random_bytes(16));
        pb_set_option('recovery_key', $key);
    }
    return $key;
}

function pb_recovery_url(): string
{
    return pb_absolute_url('/admin/login?socorro=' . pb_recovery_key());
}

// ------------------------------------------------------------------ loading and circuit breakers

function pb_load_plugins(): void
{
    if (pb_safe_mode()) {
        return;
    }
    foreach (pb_plugin_states() as $slug => $state) {
        if (!empty($state['active'])) {
            pb_boot_plugin((string) $slug, $state);
        }
    }
    pb_do_action('init');
}

/** Loads one plugin and brings its tables up to date. False when it failed (it is then switched off). */
function pb_boot_plugin(string $slug, array $state): bool
{
    $file = pb_plugins_dir() . "/$slug/plugin.php";
    if (!is_file($file)) {
        pb_plugin_failed($slug, __('A pasta do plugin sumiu ou está incompleta.'));
        return false;
    }
    return pb_run_plugin($slug, function () use ($slug, $state, $file) {
        if (is_dir(dirname($file) . '/lang')) {
            pb_load_translations(dirname($file) . '/lang'); // lang/en.php, lang/es.php
        }
        require $file; // plugin.php only registers things; functions belong in a file it loads with require_once
        $current = (int) ($state['db'] ?? 0);
        foreach ($GLOBALS['pb_plugin_migrations'][$slug] ?? [] as $version => $statements) {
            if ($version > $current) {
                foreach ($statements as $sql) {
                    pb_db()->exec($sql);
                }
                pb_set_plugin_state($slug, ['db' => $version]);
            }
        }
        return true;
    }, false);
}

/** Runs plugin code behind a circuit breaker: on any error the plugin is switched off and $fallback is returned. */
function pb_run_plugin(string $slug, callable $code, mixed $fallback = null): mixed
{
    if (isset($GLOBALS['pb_failed_plugins'][$slug])) {
        return $fallback;
    }
    $previous = $GLOBALS['pb_running_plugin'] ?? null;
    $GLOBALS['pb_running_plugin'] = $slug;
    try {
        return $code();
    } catch (Throwable $e) {
        if (isset($GLOBALS['pb_theme']) && str_starts_with(str_replace('\\', '/', $e->getFile()), str_replace('\\', '/', $GLOBALS['pb_theme']['dir']) . '/')) {
            throw $e; // the theme's copy of a plugin template broke, not the plugin: the theme's circuit breaker handles it
        }
        pb_plugin_failed($slug, $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
        return $fallback;
    } finally {
        $GLOBALS['pb_running_plugin'] = $previous;
    }
}

/** Switches a plugin off. Within an hour of an update, the previous version is put back instead and stays on. */
function pb_plugin_failed(string $slug, string $message): void
{
    $GLOBALS['pb_failed_plugins'][$slug] = true;
    error_log("PageBrick: plugin '$slug' failed: $message");
    if ((pb_plugin_states()[$slug]['rollback_until'] ?? 0) > time()) {
        try {
            pb_restore_package('plugin', $slug);
            pb_set_plugin_state($slug, ['active' => true, 'rollback_until' => null, 'error_at' => date('Y-m-d H:i:s'),
                'error' => pb_limit(sprintf(__('A versão nova deu erro e a anterior foi restaurada: %s'), $message), 500)]);
            return;
        } catch (Throwable $e) {
            error_log('PageBrick: plugin rollback failed: ' . $e->getMessage());
        }
    }
    pb_set_plugin_state($slug, ['active' => false, 'rollback_until' => null, 'error' => pb_limit($message, 500), 'error_at' => date('Y-m-d H:i:s')]);
}

/** Installs the catalog's newer version; if it breaks within the next hour, the current one comes back by itself. */
function pb_update_plugin(string $slug): array
{
    $result = pb_install_from_catalog('plugin', $slug);
    pb_set_plugin_state($slug, ['rollback_until' => time() + PB_ROLLBACK_WINDOW, 'error' => null]);
    return $result;
}

/** Shutdown function: a fatal error (which PHP can't catch) inside a plugin switches it off before the next request. */
function pb_plugins_shutdown(): void
{
    pb_plugin_handle_fatal(error_get_last());
}

/** Returns the slug of the plugin switched off, if the error came from one. */
function pb_plugin_handle_fatal(?array $error): ?string
{
    if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return null;
    }
    $slug = $GLOBALS['pb_running_plugin'] ?? null;
    $dir = str_replace('\\', '/', pb_plugins_dir()) . '/';
    $file = str_replace('\\', '/', (string) $error['file']);
    if ($slug === null && str_starts_with($file, $dir)) {
        $slug = explode('/', substr($file, strlen($dir)))[0];
    }
    if ($slug === null || ($GLOBALS['pb_config'] ?? null) === null) {
        return null;
    }
    pb_plugin_failed($slug, $error['message'] . ' (' . basename($file) . ':' . $error['line'] . ')');
    if (!headers_sent()) {
        http_response_code(503);
    }
    echo '<p>' . e(sprintf(__('Um plugin com defeito ("%s") foi desligado automaticamente. Recarregue a página.'), $slug)) . '</p>';
    return $slug;
}

function pb_activate_plugin(string $slug): void
{
    $plugin = pb_plugins_available()[$slug] ?? throw new InvalidArgumentException(__('Plugin não encontrado.'));
    if (isset($plugin['problem'])) {
        throw new InvalidArgumentException($plugin['problem']);
    }
    if (!empty(pb_plugin_states()[$slug]['active'])) {
        return; // already on (and already loaded in this request)
    }
    // Test run: the plugin is loaded right now. If it fails, the breaker records why and it stays off.
    unset($GLOBALS['pb_failed_plugins'][$slug]);
    if (!pb_boot_plugin($slug, pb_plugin_states()[$slug] ?? [])) {
        throw new InvalidArgumentException(sprintf(__('O plugin não foi ativado porque deu erro: %s'), pb_plugin_states()[$slug]['error'] ?? ''));
    }
    pb_set_plugin_state($slug, ['active' => true, 'error' => null, 'error_at' => null]);
}

function pb_deactivate_plugin(string $slug): void
{
    pb_set_plugin_state($slug, ['active' => false]);
}

/** Clears the error notice; for a plugin whose folder is gone, forgets it altogether. */
function pb_dismiss_plugin_error(string $slug): void
{
    if (isset(pb_plugins_available()[$slug])) {
        pb_set_plugin_state($slug, ['error' => null, 'error_at' => null]);
        return;
    }
    $states = pb_plugin_states();
    unset($states[$slug]);
    pb_set_option('plugins', json_encode($states, JSON_UNESCAPED_UNICODE));
}

/** Plugins on disk plus the ones that have a saved state but whose folder was deleted. */
function pb_plugins_for_panel(): array
{
    $plugins = pb_plugins_available();
    foreach (array_keys(pb_plugin_states()) as $slug) {
        $plugins[$slug] ??= ['name' => (string) $slug, 'version' => '', 'description' => '', 'author' => '', 'api' => 0,
            'missing' => true, 'problem' => __('A pasta deste plugin não existe mais.')];
    }
    return $plugins;
}

// ------------------------------------------------------------------ hooks

function pb_add_action(string $hook, callable $callback, int $priority = 10): void
{
    $GLOBALS['pb_hooks'][$hook][$priority][] = [$callback, $GLOBALS['pb_running_plugin'] ?? null];
}

function pb_add_filter(string $hook, callable $callback, int $priority = 10): void
{
    pb_add_action($hook, $callback, $priority);
}

function pb_do_action(string $hook, mixed ...$args): void
{
    foreach (pb_hook_callbacks($hook) as [$callback, $owner]) {
        $owner === null ? $callback(...$args) : pb_run_plugin($owner, fn() => $callback(...$args));
    }
}

/** Passes $value through every filter. A plugin filter that fails or returns another type is ignored and switched off. */
function pb_apply_filters(string $hook, mixed $value, mixed ...$args): mixed
{
    foreach (pb_hook_callbacks($hook) as [$callback, $owner]) {
        if ($owner === null) {
            $value = $callback($value, ...$args);
            continue;
        }
        $value = pb_run_plugin($owner, function () use ($callback, $value, $args, $hook) {
            $new = $callback($value, ...$args);
            if (get_debug_type($new) !== get_debug_type($value)) {
                throw new UnexpectedValueException(sprintf('filter "%s" returned %s instead of %s', $hook, get_debug_type($new), get_debug_type($value)));
            }
            return $new;
        }, $value);
    }
    return $value;
}

function pb_hook_callbacks(string $hook): array
{
    $byPriority = $GLOBALS['pb_hooks'][$hook] ?? [];
    ksort($byPriority);
    return $byPriority ? array_merge(...array_values($byPriority)) : [];
}

// ------------------------------------------------------------------ what plugins can add

/** A public address answered by the plugin. The handler echoes the response; '/prefix/*' passes the rest of the path. */
function pb_add_route(string $method, string $path, callable $handler): void
{
    $GLOBALS['pb_routes'][strtoupper($method) . ' ' . $path] = [$handler, $GLOBALS['pb_running_plugin'] ?? null];
}

/** Finds the plugin route for a request: [handler, owner, args] or null. */
function pb_match_route(string $method, string $path): ?array
{
    $method = $method === 'HEAD' ? 'GET' : $method; // HEAD is GET without the body (monitors, crawlers)
    $routes = $GLOBALS['pb_routes'] ?? [];
    if (isset($routes["$method $path"])) {
        return [...$routes["$method $path"], []];
    }
    foreach ($routes as $key => [$handler, $owner]) {
        [$routeMethod, $routePath] = explode(' ', $key, 2);
        if ($routeMethod === $method && str_ends_with($routePath, '/*') && str_starts_with($path, substr($routePath, 0, -1))) {
            return [$handler, $owner, [substr($path, strlen($routePath) - 1)]];
        }
    }
    return null;
}

/** A screen in the panel at /admin/p/{slug}, listed in the menu. The handler echoes the screen (GET and POST). */
function pb_add_admin_page(string $slug, string $label, callable $handler, string $role = 'editor'): void
{
    $GLOBALS['pb_admin_pages'][$slug] = ['label' => $label, 'handler' => $handler, 'role' => $role, 'owner' => $GLOBALS['pb_running_plugin'] ?? null];
}

/** Fields for the plugin's settings screen, same types as theme fields. Read the values with pb_plugin_settings_values(). */
function pb_plugin_settings(array $fields): void
{
    $GLOBALS['pb_plugin_settings'][$GLOBALS['pb_running_plugin'] ?? ''] = $fields;
}

function pb_plugin_settings_values(string $slug): PbGroup
{
    return new PbGroup($GLOBALS['pb_plugin_settings'][$slug] ?? [], json_decode(pb_option("plugin_settings_$slug", '{}'), true) ?: []);
}

function pb_save_plugin_settings(string $slug, mixed $input): void
{
    pb_set_option("plugin_settings_$slug", json_encode(pb_collect_fields($GLOBALS['pb_plugin_settings'][$slug] ?? [], $input), JSON_UNESCAPED_UNICODE));
}

/** The plugin's own tables, versioned like the core's. Name them with pb_table('pluginname_...'). */
function pb_plugin_migrations(array $migrations): void
{
    $GLOBALS['pb_plugin_migrations'][$GLOBALS['pb_running_plugin'] ?? ''] = $migrations;
}

/** Address of a file inside a plugin folder, e.g. pb_plugin_url('contact-form', 'style.css'). */
function pb_plugin_url(string $slug, string $path): string
{
    $file = pb_template_file(pb_plugins_dir() . "/$slug/$path");
    if ($file !== pb_plugins_dir() . "/$slug/$path") {
        return pb_url(pb_site_path($file, 'content/themes/' . pb_theme()['slug'] . "/plugins/$slug/$path")) . '?v=' . filemtime($file);
    }
    return pb_url("content/plugins/$slug/$path") . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/**
 * The front end belongs to the theme: it can replace any file a plugin shows on the site (a template, its style.css)
 * by putting its own copy at themes/{theme}/plugins/{plugin}/{same path}, e.g. plugins/blog/templates/list.php.
 * Files the theme didn't copy keep coming from the plugin. Never in the panel: themes don't change it.
 */
function pb_template_file(string $file): string
{
    if (empty($GLOBALS['pb_public_request']) && empty($GLOBALS['pb_theme_testing'])) {
        return $file;
    }
    $normalized = str_replace('\\', '/', $file);
    $plugins = str_replace('\\', '/', pb_plugins_dir()) . '/';
    $overrides = str_replace('\\', '/', pb_theme()['dir']) . '/plugins/';
    if (str_starts_with($normalized, $plugins)) {
        $override = $overrides . substr($normalized, strlen($plugins));
        return is_file($override) ? $override : $file;
    }
    if (str_starts_with($normalized, $overrides) && !is_file($file)) {
        return $plugins . substr($normalized, strlen($overrides)); // a copied template including one the theme didn't copy
    }
    return $file;
}
