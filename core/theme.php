<?php
// Themes and the public site: loading the active theme, rendering pages, SEO tags, sitemap.
//
// A theme is a package in content/themes/{slug}/ with:
//   theme.json       {"name", "version", "description", "author", "api": 1}
//   theme.php        returns the theme's EXTRA templates, fields, settings and menus (the standard ones come from core/standard.php)
//   layout.php       the HTML around every page ($content holds the template's output)
//   templates/*.php  one file per template: home, page, services and contact are required; 404.php is optional
//   screenshot.webp  optional picture for the Temas screen
//   plugins/…        optional copies of files a plugin shows on the site, which replace the plugin's own
//                    (plugins/blog/templates/list.php, plugins/contact-form/style.css…): see pb_template_file()
//
// The front end is the theme's: the core adds nothing to the site's HTML except where the theme calls
// pb_head(), pb_footer() and pb_slot(). tests/fixtures/sites/v0.1-output makes sure updates keep it that way.
//
// Circuit breaker: if the active theme fails while showing a page, the visitor gets that page in the
// default theme instead of an error, and the panel shows what happened.

const PB_FALLBACK_THEME = 'default';

function pb_theme(): array
{
    if (!isset($GLOBALS['pb_theme'])) {
        $slug = pb_active_theme_slug();
        try {
            $GLOBALS['pb_theme'] = pb_load_theme($slug);
        } catch (Throwable $e) {
            if ($slug === PB_FALLBACK_THEME || !empty($GLOBALS['pb_theme_testing'])) {
                throw $e;
            }
            pb_theme_failed($slug, $e);
            $GLOBALS['pb_theme'] = pb_load_theme(PB_FALLBACK_THEME);
        }
    }
    return $GLOBALS['pb_theme'];
}

function pb_load_theme(string $slug): array
{
    $dir = pb_themes_dir() . "/$slug";
    if (!preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', $slug) || !is_file("$dir/theme.php") || !is_file("$dir/layout.php")) {
        throw new RuntimeException(sprintf(__('O tema "%s" está incompleto ou não existe.'), $slug));
    }
    if (is_dir("$dir/lang")) {
        pb_load_translations("$dir/lang"); // lang/en.php, lang/es.php
    }
    $own = require "$dir/theme.php";
    if (!is_array($own)) {
        throw new RuntimeException(sprintf(__('O arquivo theme.php do tema "%s" não devolveu as definições do tema.'), $slug));
    }
    // Standard content definitions (core/standard.php) plus whatever the theme adds.
    return pb_merge_theme_definitions($own) + ['dir' => $dir, 'slug' => $slug];
}

/** The active theme; an administrator previewing another theme sees that one on the site. */
function pb_active_theme_slug(): string
{
    $preview = $_SESSION['pb_preview_theme'] ?? null;
    if (!empty($GLOBALS['pb_public_request']) && is_string($preview) && ($user = pb_current_user()) && pb_has_role($user, 'admin')
        && isset(pb_themes_available()[$preview])) {
        return $preview;
    }
    return pb_option('theme', PB_FALLBACK_THEME);
}

/** Themes on disk: slug => manifest, with a 'problem' message when one can't be used. */
function pb_themes_available(): array
{
    $themes = [];
    foreach (glob(pb_themes_dir() . '/*/theme.json') ?: [] as $file) {
        $slug = basename(dirname($file));
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', $slug)) {
            continue;
        }
        $manifest = json_decode((string) file_get_contents($file), true);
        $theme = (is_array($manifest) ? $manifest : []) + ['name' => $slug, 'version' => '', 'description' => '', 'author' => '', 'api' => 0];
        $theme['name'] = pb_manifest_text($theme, 'name');
        $theme['description'] = pb_manifest_text($theme, 'description');
        if (!is_array($manifest) || !is_string($manifest['name'] ?? null)) {
            $theme['problem'] = __('O arquivo theme.json está com defeito.');
        } elseif (!is_file(dirname($file) . '/theme.php') || !is_file(dirname($file) . '/layout.php')) {
            $theme['problem'] = __('Falta o arquivo theme.php ou layout.php.');
        } elseif ((int) $theme['api'] !== PB_API_VERSION) {
            $theme['problem'] = sprintf(__('Feito para outra versão do PageBrick (API %s; esta versão usa a API %d).'), (string) $theme['api'], PB_API_VERSION);
        } else {
            foreach (PB_STANDARD_TEMPLATES as $template) {
                if (!is_file(dirname($file) . "/templates/$template.php")) {
                    $theme['problem'] = sprintf(__('Falta o modelo "%s" (templates/%s.php), que todo tema precisa ter para mostrar o conteúdo padrão.'), $template, $template);
                    break;
                }
            }
        }
        foreach (['webp', 'png', 'jpg'] as $ext) {
            if (is_file(dirname($file) . "/screenshot.$ext")) {
                $theme['screenshot'] = "screenshot.$ext";
                break;
            }
        }
        $themes[$slug] = $theme;
    }
    ksort($themes);
    return $themes;
}

/** Saved state per theme: ['error' => ?string, 'error_at' => ?string, 'rollback_until' => ?int]. */
function pb_theme_states(): array
{
    return json_decode(pb_option('themes', '{}'), true) ?: [];
}

function pb_set_theme_state(string $slug, array $changes): void
{
    $states = pb_theme_states();
    $states[$slug] = array_merge($states[$slug] ?? ['error' => null, 'error_at' => null, 'rollback_until' => null], $changes);
    pb_set_option('themes', json_encode($states, JSON_UNESCAPED_UNICODE));
}

/** Records a theme failure. Right after an update, the previous version comes back automatically. */
function pb_theme_failed(string $slug, Throwable $e): void
{
    $message = pb_limit($e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')', 500);
    error_log("PageBrick: theme '$slug' failed: $message");
    $state = pb_theme_states()[$slug] ?? [];
    if (($state['rollback_until'] ?? 0) > time()) {
        try {
            pb_restore_package('theme', $slug);
            $message = sprintf(__('A versão nova deu erro e a anterior foi restaurada: %s'), $message);
        } catch (Throwable $restoreError) {
            error_log('PageBrick: theme rollback failed: ' . $restoreError->getMessage());
        }
    }
    pb_set_theme_state($slug, ['error' => $message, 'error_at' => date('Y-m-d H:i:s'), 'rollback_until' => null]);
}

/** True when the error came from code inside the theme's folder (and not from a plugin template, say). */
function pb_error_in_theme(Throwable $e, string $dir): bool
{
    $dir = str_replace('\\', '/', $dir) . '/';
    foreach (array_merge([$e->getFile()], array_column($e->getTrace(), 'file')) as $file) {
        if (str_starts_with(str_replace('\\', '/', (string) $file), $dir)) {
            return true;
        }
    }
    return false;
}

/**
 * Tries the theme with every page of the site (output thrown away) before it goes live.
 * Returns null when all is well, or [what failed, why].
 */
function pb_test_theme(string $slug): ?array
{
    $saved = [$GLOBALS['pb_theme'] ?? null, $GLOBALS['pb_current_page'] ?? null];
    $GLOBALS['pb_theme_testing'] = true;
    try {
        $GLOBALS['pb_theme'] = pb_load_theme($slug);
        foreach (pb_page_list() as $row) {
            try {
                pb_render_page(pb_page_find((int) $row['id']));
            } catch (Throwable $e) {
                return [$row['title'], $e->getMessage()];
            }
        }
        pb_render_not_found();
        return null;
    } catch (Throwable $e) {
        return [__('o tema'), $e->getMessage()];
    } finally {
        unset($GLOBALS['pb_theme_testing']);
        [$GLOBALS['pb_theme'], $GLOBALS['pb_current_page']] = $saved;
        if ($saved[0] === null) {
            unset($GLOBALS['pb_theme']);
        }
    }
}

/** Installs the catalog's newer version of a theme; if it breaks within the next hour, the current one comes back. */
function pb_update_theme(string $slug): array
{
    $result = pb_install_from_catalog('theme', $slug);
    pb_set_theme_state($slug, ['rollback_until' => time() + PB_ROLLBACK_WINDOW, 'error' => null]);
    return $result;
}

function pb_activate_theme(string $slug): void
{
    $theme = pb_themes_available()[$slug] ?? throw new InvalidArgumentException(__('Tema não encontrado.'));
    if (isset($theme['problem'])) {
        throw new InvalidArgumentException($theme['problem']);
    }
    if ($failure = pb_test_theme($slug)) {
        throw new InvalidArgumentException(sprintf(__('O tema não foi ativado: deu erro ao mostrar "%s" (%s).'), ...$failure));
    }
    pb_set_option('theme', $slug);
    pb_set_theme_state($slug, ['error' => null, 'error_at' => null]);
    unset($GLOBALS['pb_theme']);
}

/** Address of a file inside the active theme, e.g. pb_theme_url('assets/style.css'). */
function pb_theme_url(string $path): string
{
    $relative = substr(str_replace('\\', '/', pb_theme()['dir']), strlen(str_replace('\\', '/', PB_ROOT)) + 1);
    $file = pb_theme()['dir'] . '/' . $path;
    return pb_url("$relative/$path") . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/** Renders a theme template inside the theme layout. A template the theme doesn't have falls back to "page". */
function pb_theme_render(string $template, array $vars = []): string
{
    return pb_render_with_breaker(function (array $theme) use ($template, $vars) {
        $file = $theme['dir'] . "/templates/$template.php";
        return [is_file($file) ? $file : $theme['dir'] . '/templates/page.php', $vars];
    });
}

/** Renders any template file (a plugin's, for instance) inside the active theme's layout. */
function pb_theme_render_file(string $file, array $vars = []): string
{
    return pb_render_with_breaker(fn() => [$file, $vars]);
}

/**
 * Renders inside the theme layout; if the theme itself breaks, renders the same thing with the default theme.
 * $prepare(theme) returns [template file, variables] for that theme, so a fallback gets its own field definitions.
 */
function pb_render_with_breaker(callable $prepare): string
{
    $theme = pb_theme();
    try {
        return pb_render_in_layout($theme, ...$prepare($theme));
    } catch (Throwable $e) {
        if ($theme['slug'] === PB_FALLBACK_THEME || !empty($GLOBALS['pb_theme_testing']) || !pb_error_in_theme($e, $theme['dir'])) {
            throw $e;
        }
        pb_theme_failed($theme['slug'], $e);
        $GLOBALS['pb_theme'] = $fallback = pb_load_theme(PB_FALLBACK_THEME);
        return pb_render_in_layout($fallback, ...$prepare($fallback));
    }
}

function pb_render_in_layout(array $theme, string $file, array $vars): string
{
    $vars += [
        'site' => pb_settings(),
        'siteName' => new PbValue(['type' => 'text'], pb_option('site_title', '')),
    ];
    $vars['content'] = pb_include($file, $vars);
    return pb_include($theme['dir'] . '/layout.php', $vars);
}

/**
 * For plugins: shows their own template as a page of the site, with title and description for Google.
 * $seo: ['title' => ..., 'description' => ..., 'path' => '/noticias/minha-noticia']
 */
function pb_render_in_theme(string $file, array $vars, array $seo): string
{
    $GLOBALS['pb_current_page'] = [
        'id' => 0, 'title' => $seo['title'], 'seo_title' => '', 'seo_description' => $seo['description'] ?? '',
        'slug' => ltrim($seo['path'], '/'), 'status' => 'published', 'preview' => false, 'locale' => pb_site_locale(),
    ];
    return pb_theme_render_file($file, $vars + ['title' => new PbValue(['type' => 'text'], $seo['title'])]);
}

/** A place in a template where plugins can add HTML: <?= pb_slot('contact') ?>. */
function pb_slot(string $name, array $context = []): string
{
    return pb_apply_filters("slot:$name", '', $context);
}

/** Call it right before </body> in the theme layout: plugins add their scripts here. */
function pb_footer(): string
{
    return pb_preview_bar() . pb_apply_filters('footer_html', '');
}

/** The bar an administrator sees on the site while previewing a theme that isn't active yet. */
function pb_preview_bar(): string
{
    $slug = pb_theme()['slug'] ?? '';
    if (($_SESSION['pb_preview_theme'] ?? null) !== $slug || $slug === pb_option('theme', PB_FALLBACK_THEME)) {
        return '';
    }
    $name = pb_themes_available()[$slug]['name'] ?? $slug;
    $form = fn(string $action, string $label) => '<form method="post" action="' . e(pb_url('/admin/themes')) . '" style="display:inline;margin:0">'
        . pb_csrf_field() . '<input type="hidden" name="theme" value="' . e($slug) . '">'
        . '<button name="action" value="' . $action . '" style="margin-left:1rem;padding:.4rem .8rem;border:1px solid #fff;border-radius:4px;background:' . ($action === 'activate' ? '#d24e2b' : 'transparent') . ';color:#fff;font:inherit;cursor:pointer">' . e($label) . '</button></form>';
    return '<div role="status" style="position:fixed;inset:auto 0 0 0;z-index:9999;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:.5rem;padding:.75rem 1rem;background:#171923;color:#fff;font:15px/1.4 system-ui,sans-serif">'
        . e(sprintf(__('Pré-visualizando o tema "%s". Só você está vendo.'), $name))
        . $form('activate', __('Ativar este tema')) . $form('end-preview', __('Sair da pré-visualização')) . '</div>';
}

function pb_render_page(array $page, bool $preview = false): string
{
    $GLOBALS['pb_current_page'] = $page + ['preview' => $preview];
    return pb_render_with_breaker(function (array $theme) use ($page) {
        // A page made with a template this theme doesn't have is shown as a simple page; its content stays saved.
        $template = isset($theme['templates'][$page['template']]) ? $page['template'] : 'page';
        return [$theme['dir'] . "/templates/$template.php", [
            'page' => new PbGroup($theme['templates'][$template]['fields'] ?? [], $page['data']),
            'title' => new PbValue(['type' => 'text'], $page['title']),
            'isHome' => $page['id'] === pb_home_page_id(),
        ]];
    });
}

/** The page's title for Google and social networks: the one typed in SEO, or "Page · Site" ("Site" on the home page). */
function pb_page_seo_title(array $page): string
{
    $siteName = pb_option('site_title', '');
    if ($page['seo_title'] !== '') {
        return $page['seo_title'];
    }
    return (int) $page['id'] !== 0 && (int) $page['id'] === pb_home_page_id() ? $siteName : "{$page['title']} · $siteName";
}

/**
 * Tags for <head>: title, description, canonical address and social sharing. Call it in the theme layout.
 * $options['image']: address of the sharing image (as returned by ->url()).
 */
function pb_head(array $options = []): string
{
    $page = $GLOBALS['pb_current_page'] ?? null;
    $siteName = pb_option('site_title', '');
    if (!$page) {
        $title = $GLOBALS['pb_page_title'] ?? $siteName;
        return '<title>' . e($title) . '</title>' . "\n" . '<meta name="robots" content="noindex">' . "\n";
    }
    $isHome = $page['id'] !== 0 && $page['id'] === pb_home_page_id();
    $title = pb_page_seo_title($page);
    $url = pb_absolute_url($isHome ? '/' : '/' . $page['slug']);

    $tags = ['<title>' . e($title) . '</title>'];
    if ($page['seo_description'] !== '') {
        $tags[] = '<meta name="description" content="' . e($page['seo_description']) . '">';
        $tags[] = '<meta property="og:description" content="' . e($page['seo_description']) . '">';
    }
    $tags[] = '<link rel="canonical" href="' . e($url) . '">';
    $tags[] = '<meta property="og:type" content="website">';
    $tags[] = '<meta property="og:title" content="' . e($title) . '">';
    $tags[] = '<meta property="og:url" content="' . e($url) . '">';
    $tags[] = '<meta property="og:site_name" content="' . e($siteName) . '">';
    $tags[] = '<meta property="og:locale" content="' . e(pb_og_locale($page['locale'])) . '">';
    if (!empty($options['image'])) {
        $tags[] = '<meta property="og:image" content="' . e(pb_absolute_url(substr($options['image'], strlen(pb_base_path())))) . '">';
    }
    if ($page['preview'] || $page['status'] !== 'published') {
        $tags[] = '<meta name="robots" content="noindex">';
    }
    return implode("\n", $tags) . "\n" . pb_apply_filters('head_html', '');
}

// ------------------------------------------------------------------ helpers for themes

/** True when someone is logged in to the panel: themes use it to show hints only the owner sees. */
function pb_is_logged_in(): bool
{
    return pb_current_user() !== null;
}

/**
 * wa.me link for a number typed any way; '' when empty. Portuguese sites may leave out the country code
 * ("(19) 99999-9999" is taken as Brazil); in the other languages the number must start with it ("+1 555…").
 */
function pb_whatsapp_url(string $number, string $message = ''): string
{
    $digits = ltrim(preg_replace('/\D/', '', $number), '0'); // "019..." long-distance prefix
    if ($digits === '') {
        return '';
    }
    if (strlen($digits) <= 11 && pb_locale() === 'pt-BR') { // the site's language on the site
        $digits = "55$digits"; // no country code: assume Brazil
    }
    return "https://wa.me/$digits" . ($message !== '' ? '?text=' . rawurlencode($message) : '');
}

/** Google Maps search for an address. */
function pb_map_url(string $address): string
{
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(preg_replace('/\s+/', ' ', trim($address)));
}

function pb_luminance(string $hex): float
{
    $channels = array_map(function ($c) {
        $c = hexdec($c) / 255;
        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }, str_split(ltrim($hex, '#'), 2));
    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function pb_contrast(string $a, string $b): float
{
    [$light, $dark] = [max(pb_luminance($a), pb_luminance($b)), min(pb_luminance($a), pb_luminance($b))];
    return ($light + 0.05) / ($dark + 0.05);
}

/** Text color that reads well on top of $background: near-black or white. */
function pb_text_color_on(string $background): string
{
    return pb_contrast($background, '#171923') >= pb_contrast($background, '#ffffff') ? '#171923' : '#ffffff';
}

/** $color darkened until it is readable as text on $background (WCAG AA, 4.5:1). */
function pb_readable_color(string $color, string $background = '#ffffff'): string
{
    $rgb = array_map('hexdec', str_split(ltrim($color, '#'), 2));
    for ($i = 0; $i < 20 && pb_contrast(vsprintf('#%02x%02x%02x', $rgb), $background) < 4.5; $i++) {
        $rgb = array_map(fn($c) => (int) round($c * 0.88), $rgb);
    }
    return vsprintf('#%02x%02x%02x', $rgb);
}

// ------------------------------------------------------------------ public routes

function pb_public(string $method, string $path): void
{
    $GLOBALS['pb_public_request'] = true; // theme preview applies to the site only, never to the panel
    $isApi = str_starts_with($path, '/api/');
    if ($isApi && $method === 'GET' && str_starts_with($path, PB_CONTENT_API . '/') && pb_content_api($path)) {
        return; // the core's endpoints come first; plugins add theirs under /api/v1/{plugin}
    }
    if ($route = pb_match_route($method, $path)) {
        [$handler, $owner, $args] = $route;
        if ($owner === null) {
            $handler(...$args);
            return;
        }
        // The plugin's answer is buffered: if it breaks halfway, nothing half-done reaches the visitor.
        ob_start();
        $ok = pb_run_plugin($owner, fn() => $handler(...$args) ?? true, false);
        $output = ob_get_clean();
        if ($ok === false) {
            http_response_code(503);
            if ($isApi) {
                header('Content-Type: application/json; charset=utf-8');
                echo '{"error": "unavailable"}';
                return;
            }
            pb_render('message', ['title' => pb_option('site_title', ''), 'message' => __('Este recurso está temporariamente indisponível. Tente de novo mais tarde.')]);
            return;
        }
        echo $output;
        return;
    }
    if ($isApi) {
        pb_content_send(null); // an address of the API nobody answers: JSON, not a web page
        return;
    }
    if ($method !== 'GET' && $method !== 'HEAD') {
        http_response_code(405);
        return;
    }
    if ($path === '/sitemap.xml') {
        header('Content-Type: application/xml; charset=utf-8');
        echo pb_sitemap_xml();
        return;
    }
    if ($path === '/robots.txt') {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nAllow: /\nSitemap: " . pb_absolute_url('/sitemap.xml') . "\n";
        return;
    }

    $homeId = pb_home_page_id();
    $page = $path === '/' ? ($homeId ? pb_page_find($homeId) : null) : pb_page_by_slug(substr($path, 1));
    if ($page && $page['status'] === 'published') {
        if ($path !== '/' && $page['id'] === $homeId) {
            pb_redirect('/', 301); // one address per page
        }
        echo pb_render_page($page);
        return;
    }

    http_response_code(404);
    if ($path === '/' && !$homeId) {
        pb_render('message', ['title' => pb_option('site_title', 'PageBrick'), 'message' => __('Nenhuma página publicada ainda.')]);
        return;
    }
    echo pb_render_not_found();
}

function pb_render_not_found(): string
{
    $GLOBALS['pb_current_page'] = null;
    $GLOBALS['pb_page_title'] = __('Página não encontrada');
    if (is_file(pb_theme()['dir'] . '/templates/404.php')) {
        return pb_theme_render('404');
    }
    return pb_include(PB_ROOT . '/core/views/layout.php', [
        'title' => __('Página não encontrada'),
        'content' => pb_include(PB_ROOT . '/core/views/message.php', ['title' => __('Página não encontrada'), 'message' => __('Este endereço não existe.')]),
    ]);
}

function pb_sitemap_xml(): string
{
    $rows = pb_db()->query('SELECT id, slug, updated_at FROM ' . pb_table('pages') . " WHERE status = 'published' ORDER BY id")->fetchAll();
    $home = pb_home_page_id();
    $urls = array_map(fn($row) => [(int) $row['id'] === $home ? '/' : '/' . $row['slug'], $row['updated_at']], $rows);
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    // Plugins add their addresses as [path, last change], e.g. ['/noticias/x', '2026-10-07 10:00:00'].
    foreach (pb_apply_filters('sitemap_urls', $urls) as $url) {
        if (is_array($url) && is_string($url[0] ?? null)) {
            $xml .= '  <url><loc>' . e(pb_absolute_url($url[0])) . '</loc><lastmod>' . e(substr((string) ($url[1] ?? ''), 0, 10)) . "</lastmod></url>\n";
        }
    }
    return $xml . "</urlset>\n";
}
