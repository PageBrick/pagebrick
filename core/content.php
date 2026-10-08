<?php
// Pages (with revisions), menus and site settings.

const PB_PAGE_STATUSES = ['draft', 'published'];
const PB_REVISIONS_KEPT = 10;

const PB_TRANSLIT = [
    'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'Á' => 'a', 'À' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Ä' => 'a', 'Å' => 'a',
    'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'Ë' => 'e',
    'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'Í' => 'i', 'Ì' => 'i', 'Î' => 'i', 'Ï' => 'i',
    'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'Ó' => 'o', 'Ò' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ö' => 'o', 'Ø' => 'o',
    'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'Ú' => 'u', 'Ù' => 'u', 'Û' => 'u', 'Ü' => 'u',
    'ç' => 'c', 'Ç' => 'c', 'ñ' => 'n', 'Ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y', 'Ý' => 'y', 'ß' => 'ss', 'æ' => 'ae', 'Æ' => 'ae', 'œ' => 'oe', 'Œ' => 'oe',
];

/** "Serviços & Preços" => "servicos-precos" */
function pb_slugify(string $text): string
{
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(strtr($text, PB_TRANSLIT)));
    return trim(substr(trim($slug, '-'), 0, 80), '-');
}

// ------------------------------------------------------------------ pages

/** Fields of a template; a template the active theme doesn't have is edited (and shown) as a simple page. */
function pb_template_fields(string $template): array
{
    $templates = pb_theme()['templates'];
    return $templates[$template]['fields'] ?? $templates['page']['fields'] ?? [];
}

function pb_page_find(int $id): ?array
{
    $st = pb_db()->prepare('SELECT * FROM ' . pb_table('pages') . ' WHERE id = ?');
    $st->execute([$id]);
    return pb_page_decode($st->fetch() ?: null);
}

/** A page by its address, in $locale (default: the site's language). */
function pb_page_by_slug(string $slug, string $locale = ''): ?array
{
    $st = pb_db()->prepare('SELECT * FROM ' . pb_table('pages') . ' WHERE slug = ? AND locale = ?');
    $st->execute([$slug, $locale !== '' ? $locale : pb_site_locale()]);
    return pb_page_decode($st->fetch() ?: null);
}

function pb_page_decode(?array $page): ?array
{
    if ($page) {
        $page['id'] = (int) $page['id'];
        $page['data'] = json_decode($page['data'], true) ?: [];
        $page['translation_of'] = isset($page['translation_of']) ? (int) $page['translation_of'] : null;
    }
    return $page;
}

/** All pages, without their content (translations included: see 'locale' and 'translation_of'). */
function pb_page_list(): array
{
    return pb_db()->query('SELECT id, title, slug, locale, translation_of, template, status, updated_at FROM ' . pb_table('pages') . ' ORDER BY title')->fetchAll();
}

function pb_home_page_id(): int
{
    return (int) pb_option('home_page_id', '0');
}

/** True for the home page and its translations. */
function pb_page_is_home(array $page): bool
{
    $home = pb_home_page_id();
    return $home !== 0 && ((int) $page['id'] === $home || (int) ($page['translation_of'] ?? 0) === $home);
}

/** The page's address without the install folder: '/', '/about', '/en-us/about'. */
function pb_page_path(array $page): string
{
    return pb_locale_path($page['locale'] ?? pb_site_locale(), pb_page_is_home($page) ? '/' : '/' . $page['slug']);
}

function pb_page_url(array $page): string
{
    return pb_url(pb_page_path($page));
}

/**
 * The version of a page in $locale: the page itself, its main-language original or one of its translations.
 * Null when there is none.
 */
function pb_page_translation(array $page, string $locale): ?array
{
    if (($page['locale'] ?? pb_site_locale()) === $locale) {
        return $page;
    }
    $original = (int) ($page['translation_of'] ?? 0) ?: (int) $page['id'];
    if ($locale === pb_site_locale()) {
        return pb_page_find($original);
    }
    $st = pb_db()->prepare('SELECT * FROM ' . pb_table('pages') . ' WHERE translation_of = ? AND locale = ?');
    $st->execute([$original, $locale]);
    return pb_page_decode($st->fetch() ?: null);
}

/** Starts the translation of a main-language page into an extra language: a draft with the original's content. */
function pb_page_translate(int $id, string $locale): int
{
    $page = pb_page_find($id) ?? throw new InvalidArgumentException(__('Página não encontrada.'));
    if ($page['translation_of'] !== null || !in_array($locale, array_slice(pb_site_locales(), 1), true)) {
        throw new InvalidArgumentException(__('Idioma inválido.'));
    }
    if ($existing = pb_page_translation($page, $locale)) {
        return (int) $existing['id'];
    }
    return pb_page_create($page['title'], $page['template'], $page['data'], 'draft', $page['slug'], $locale, $page['id']);
}

/** Resolves a link field value ('page:12' or an address) to an address; '' if the page is gone. */
function pb_link_url(string $link): string
{
    if (preg_match('/^page:(\d+)$/', $link, $m)) {
        $page = pb_page_find((int) $m[1]);
        if ($page && pb_content_locale() !== $page['locale']) {
            // On a translated page, links go to the translation when it is published.
            $translation = pb_page_translation($page, pb_content_locale());
            $page = $translation && $translation['status'] === 'published' ? $translation : $page;
        }
        return $page ? pb_page_url($page) : '';
    }
    return $link;
}

function pb_slug_taken(string $slug, string $locale, int $exceptId = 0): bool
{
    $st = pb_db()->prepare('SELECT COUNT(*) FROM ' . pb_table('pages') . ' WHERE slug = ? AND locale = ? AND id <> ?');
    $st->execute([$slug, $locale, $exceptId]);
    return (int) $st->fetchColumn() > 0;
}

function pb_validate_page_title(string $title): string
{
    $title = trim($title);
    if (!preg_match('/^.{1,200}$/su', $title)) {
        throw new InvalidArgumentException(__('Informe um título com até 200 caracteres.'));
    }
    return $title;
}

/**
 * Creates a page; the slug comes from the title and gets -2, -3... if already used.
 * A translation gives its $locale and the main-language page it translates ($translationOf).
 */
function pb_page_create(string $title, string $template, array $data = [], string $status = 'draft', string $slug = '',
    string $locale = '', ?int $translationOf = null): int
{
    $title = pb_validate_page_title($title);
    if (!isset(pb_theme()['templates'][$template])) {
        throw new InvalidArgumentException(__('Modelo de página inválido.'));
    }
    $base = pb_slugify($slug !== '' ? $slug : $title) ?: 'pagina';
    $slug = $base;
    $locale = $locale !== '' ? $locale : pb_site_locale();
    for ($n = 2; pb_slug_taken($slug, $locale); $n++) {
        $slug = "$base-$n";
    }
    pb_db()->prepare('INSERT INTO ' . pb_table('pages') . ' (title, slug, locale, translation_of, template, status, data) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$title, $slug, $locale, $translationOf, $template, in_array($status, PB_PAGE_STATUSES, true) ? $status : 'draft',
            json_encode(pb_collect_fields(pb_template_fields($template), $data), JSON_UNESCAPED_UNICODE)]);
    return (int) pb_db()->lastInsertId();
}

/**
 * Turns the panel form into a page array without saving (used by save and by preview).
 * $in: title, slug, status, seo_title, seo_description, f (the template's fields).
 */
function pb_page_from_input(array $page, array $in): array
{
    $page['title'] = pb_validate_page_title(is_string($in['title'] ?? null) ? $in['title'] : '');
    $page['slug'] = pb_slugify(is_string($in['slug'] ?? null) ? $in['slug'] : '') ?: (pb_slugify($page['title']) ?: 'pagina');
    $page['status'] = in_array($in['status'] ?? null, PB_PAGE_STATUSES, true) ? $in['status'] : 'draft';
    $page['seo_title'] = pb_limit(trim(is_string($in['seo_title'] ?? null) ? $in['seo_title'] : ''), 200);
    $page['seo_description'] = pb_limit(trim(is_string($in['seo_description'] ?? null) ? $in['seo_description'] : ''), 300);
    // Fields the current theme doesn't know (left by another theme) are kept, so switching back brings them back.
    $page['data'] = pb_collect_fields(pb_template_fields($page['template']), $in['f'] ?? []) + ($page['data'] ?? []);
    return $page;
}

function pb_page_save(int $id, array $in, ?int $userId): void
{
    $old = pb_page_find($id) ?? throw new InvalidArgumentException(__('Página não encontrada.'));
    $page = pb_page_from_input($old, $in);
    if (pb_slug_taken($page['slug'], $page['locale'], $id)) {
        throw new InvalidArgumentException(sprintf(__('O endereço "%s" já é usado por outra página.'), $page['slug']));
    }
    if ($id === pb_home_page_id() && $page['status'] !== 'published') {
        throw new InvalidArgumentException(__('A página inicial precisa ficar publicada.'));
    }
    pb_page_snapshot($old, $userId);
    pb_page_write($page);
}

function pb_page_write(array $page): void
{
    pb_db()->prepare('UPDATE ' . pb_table('pages') . ' SET title = ?, slug = ?, template = ?, status = ?, data = ?, seo_title = ?, seo_description = ? WHERE id = ?')
        ->execute([$page['title'], $page['slug'], $page['template'], $page['status'], json_encode($page['data'], JSON_UNESCAPED_UNICODE),
            $page['seo_title'], $page['seo_description'], $page['id']]);
}

/** Deletes a page; a main-language page takes its translations with it. */
function pb_page_delete(int $id): void
{
    if ($id === pb_home_page_id()) {
        throw new InvalidArgumentException(__('A página inicial não pode ser excluída. Escolha outra página inicial antes.'));
    }
    $st = pb_db()->prepare('SELECT id FROM ' . pb_table('pages') . ' WHERE id = ? OR translation_of = ?');
    $st->execute([$id, $id]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $pageId) {
        pb_db()->prepare('DELETE FROM ' . pb_table('page_revisions') . ' WHERE page_id = ?')->execute([$pageId]);
        pb_db()->prepare('DELETE FROM ' . pb_table('pages') . ' WHERE id = ?')->execute([$pageId]);
    }
}

function pb_set_home_page(int $id): void
{
    $page = pb_page_find($id);
    if (!$page || $page['status'] !== 'published' || $page['translation_of'] !== null) {
        throw new InvalidArgumentException(__('Só uma página publicada pode ser a página inicial.'));
    }
    pb_set_option('home_page_id', (string) $id);
}

// ------------------------------------------------------------------ revisions

/** Saves the page's current content as a revision, keeping only the newest PB_REVISIONS_KEPT. */
function pb_page_snapshot(array $page, ?int $userId): void
{
    $table = pb_table('page_revisions');
    $snapshot = array_intersect_key($page, array_flip(['title', 'data', 'seo_title', 'seo_description']));
    pb_db()->prepare("INSERT INTO $table (page_id, snapshot, user_id) VALUES (?, ?, ?)")
        ->execute([$page['id'], json_encode($snapshot, JSON_UNESCAPED_UNICODE), $userId]);

    $st = pb_db()->prepare("SELECT id FROM $table WHERE page_id = ? ORDER BY id DESC LIMIT 1 OFFSET " . (PB_REVISIONS_KEPT - 1));
    $st->execute([$page['id']]);
    if ($oldestKept = $st->fetchColumn()) {
        pb_db()->prepare("DELETE FROM $table WHERE page_id = ? AND id < ?")->execute([$page['id'], $oldestKept]);
    }
}

function pb_page_revisions(int $pageId): array
{
    $st = pb_db()->prepare('SELECT r.id, r.created_at, u.name AS user_name FROM ' . pb_table('page_revisions') . ' r LEFT JOIN '
        . pb_table('users') . ' u ON u.id = r.user_id WHERE r.page_id = ? ORDER BY r.id DESC');
    $st->execute([$pageId]);
    return $st->fetchAll();
}

/** Brings back a revision's content (title, fields, SEO). Address and status stay as they are. Returns the page id. */
function pb_page_restore(int $revisionId, ?int $userId): int
{
    $st = pb_db()->prepare('SELECT * FROM ' . pb_table('page_revisions') . ' WHERE id = ?');
    $st->execute([$revisionId]);
    $revision = $st->fetch() ?: throw new InvalidArgumentException(__('Versão não encontrada.'));
    $page = pb_page_find((int) $revision['page_id']) ?? throw new InvalidArgumentException(__('Página não encontrada.'));

    pb_page_snapshot($page, $userId);
    $snapshot = json_decode($revision['snapshot'], true) ?: [];
    $page['data'] = pb_collect_fields(pb_template_fields($page['template']), $snapshot['data'] ?? []) + ($snapshot['data'] ?? []);
    pb_page_write(array_merge($page, array_intersect_key($snapshot, array_flip(['title', 'seo_title', 'seo_description']))));
    return $page['id'];
}

// ------------------------------------------------------------------ site settings and menus

/** Field definitions for the "Aparência e contato" screen, declared by the theme. */
function pb_settings_fields(): array
{
    return pb_theme()['settings'] ?? [];
}

/** The site settings as safe values: <?= $site->phone ?> In an extra language, its translated texts win. */
function pb_settings(): PbGroup
{
    $data = json_decode(pb_option('theme_settings', '{}'), true) ?: [];
    if (pb_content_locale() !== pb_site_locale()) {
        $data = array_replace_recursive($data, pb_without_blanks(pb_settings_translation(pb_content_locale())));
    }
    return new PbGroup(pb_settings_fields(), $data);
}

/** The texts of "Aparência e contato" translated into an extra language (only what was filled in). */
function pb_settings_translation(string $locale): array
{
    return json_decode(pb_option("theme_settings@$locale", '{}'), true) ?: [];
}

function pb_save_settings_translation(string $locale, mixed $input): void
{
    if (!in_array($locale, array_slice(pb_site_locales(), 1), true)) {
        throw new InvalidArgumentException(__('Idioma inválido.'));
    }
    pb_set_option("theme_settings@$locale", json_encode(pb_collect_fields(pb_text_fields(pb_settings_fields()), $input), JSON_UNESCAPED_UNICODE));
}

/** Only the fields that hold text (text, textarea, rich text), keeping the groups around them: what gets translated. */
function pb_text_fields(array $fields): array
{
    $texts = [];
    foreach ($fields as $name => $def) {
        if ($def['type'] === 'group') {
            if ($inner = pb_text_fields($def['fields'])) {
                $texts[$name] = ['fields' => $inner] + array_diff_key($def, ['toggle' => true]);
            }
        } elseif (in_array($def['type'], ['text', 'textarea', 'richtext'], true)) {
            $texts[$name] = $def;
        }
    }
    return $texts;
}

/** Drops empty texts, so a translation left blank shows the main language's text. */
function pb_without_blanks(array $data): array
{
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $data[$key] = pb_without_blanks($value);
        } elseif ($value === '' || $value === null) {
            unset($data[$key]);
        }
    }
    return $data;
}

function pb_save_settings(mixed $input): void
{
    $saved = json_decode(pb_option('theme_settings', '{}'), true) ?: [];
    // As with pages, settings of other themes stay saved.
    pb_set_option('theme_settings', json_encode(pb_collect_fields(pb_settings_fields(), $input) + $saved, JSON_UNESCAPED_UNICODE));
}

/** Field definition of a menu: a list of label + link. */
function pb_menu_def(string $label): array
{
    return [
        'type' => 'list',
        'label' => $label,
        'item_label' => __('Item do menu'),
        'add_label' => __('Adicionar item'),
        'fields' => [
            'label' => ['type' => 'text', 'label' => __('Texto'), 'help' => __('Em branco: usa o título da página.')],
            'link' => ['type' => 'link', 'label' => __('Leva para')],
        ],
    ];
}

function pb_save_menu(string $location, mixed $items): void
{
    pb_set_option("menu_$location", json_encode(pb_collect_value(pb_menu_def(''), $items), JSON_UNESCAPED_UNICODE));
}

function pb_menu_items_raw(string $location): array
{
    return json_decode(pb_option("menu_$location", '[]'), true) ?: [];
}

/**
 * A menu's items as [label, url, current], skipping links to missing or unpublished pages.
 * Values are raw: escape them, or use pb_menu_html().
 */
function pb_menu(string $location): array
{
    $current = pb_request_path();
    $items = [];
    foreach (pb_menu_items_raw($location) as $item) {
        $label = $item['label'] ?? '';
        $url = $item['link'] ?? '';
        if (preg_match('/^page:(\d+)$/', $url, $m)) {
            $page = pb_page_find((int) $m[1]);
            if (!$page || $page['status'] !== 'published') {
                continue;
            }
            // In an extra language, the menu leads to the published translation (and shows its title).
            $translation = pb_content_locale() !== $page['locale'] ? pb_page_translation($page, pb_content_locale()) : null;
            $page = $translation && $translation['status'] === 'published' ? $translation : $page;
            $label = $label !== '' ? $label : $page['title'];
            $url = pb_page_url($page);
        }
        if ($label === '' || $url === '') {
            continue;
        }
        $internal = str_starts_with($url, '/') && !str_starts_with($url, '//');
        $items[] = ['label' => $label, 'url' => $url, 'current' => $internal && rtrim(parse_url($url, PHP_URL_PATH) ?? '', '/') === rtrim(pb_url($current), '/')];
    }
    return $items;
}

function pb_menu_html(string $location, string $class = 'menu'): string
{
    $html = '';
    foreach (pb_menu($location) as $item) {
        $html .= '<li><a href="' . e($item['url']) . '"' . ($item['current'] ? ' aria-current="page"' : '') . '>' . e($item['label']) . '</a></li>';
    }
    return $html === '' ? '' : '<ul class="' . e($class) . '">' . $html . '</ul>';
}

// ------------------------------------------------------------------ demo content

/**
 * Fills a fresh site with the standard example content (photos, pages, home, menus, settings; see pb_standard_demo),
 * so a non-technical owner starts from a finished site and only replaces texts and photos.
 */
function pb_seed_demo(): void
{
    pb_import_content(pb_standard_demo(), PB_ROOT . '/core/demo');
}

/**
 * Fills the site with ready-made content: $content has 'media' (files in $mediaDir), 'pages', 'menus' and 'settings',
 * in the format of pb_standard_demo(). Inside it, 'page:{slug}' and 'media:{key}' point to its own pages and photos.
 * A page whose address already exists receives the new content; what it had before stays in its history.
 * Translations: a page may carry 'translations' => [locale => [title, slug, data, seo_title, seo_description]], and
 * 'settings_translations' => [locale => texts] translates "Aparência e contato". Their languages get switched on.
 */
function pb_import_content(array $content, string $mediaDir, ?int $userId = null): void
{
    $media = [];
    foreach ($content['media'] ?? [] as $key => $photo) {
        try {
            $media[$key] = pb_media_store("$mediaDir/{$photo['file']}", $photo['file']);
            pb_media_set_alt($media[$key], $photo['alt'] ?? '');
        } catch (Throwable $e) {
            error_log("PageBrick: photo {$photo['file']} skipped: {$e->getMessage()}"); // e.g. uploads folder not writable
        }
    }
    $ids = [];
    $pages = $content['pages'] ?? [];
    foreach ($pages as $p) {
        // The address in the site's language (sobre → about); the original one stays the key for "page:" links.
        $existing = pb_page_by_slug(pb_slugify(__($p['slug'])));
        if ($existing) {
            pb_page_snapshot($existing, $userId);
            $ids[$p['slug']] = $existing['id'];
        } else {
            $ids[$p['slug']] = pb_page_create($p['title'], $p['template'], [], 'published', __($p['slug']));
        }
    }
    // Content is saved after every page exists, so links between pages resolve.
    $resolve = function (mixed $value) use (&$resolve, $ids, $media): mixed {
        if (is_array($value)) {
            return array_map($resolve, $value);
        }
        if (is_string($value) && preg_match('/^media:([a-z0-9-]+)$/', $value, $m)) {
            return (string) ($media[$m[1]] ?? '');
        }
        return is_string($value) && preg_match('/^page:([a-z0-9-]+)$/', $value, $m) && isset($ids[$m[1]]) ? 'page:' . $ids[$m[1]] : $value;
    };
    // Languages the content is translated into (other than the site's main one) are switched on.
    $languages = array_keys(($content['settings_translations'] ?? []) + array_merge(...array_map(fn($p) => $p['translations'] ?? [], $pages ?: [[]])));
    pb_set_site_locales(array_merge(pb_site_locales(), array_filter($languages, fn($l) => isset(PB_LOCALES[$l]))));
    $write = function (array $page, array $p) use ($resolve) {
        $page['title'] = pb_validate_page_title($p['title']);
        $page['status'] = 'published';
        $page['data'] = pb_collect_fields(pb_template_fields($page['template']), $resolve($p['data'] ?? []));
        $page['seo_title'] = $p['seo_title'] ?? '';
        $page['seo_description'] = $p['seo_description'] ?? '';
        pb_page_write($page);
    };
    foreach ($pages as $p) {
        $page = pb_page_find($ids[$p['slug']]);
        $page['template'] = isset(pb_theme()['templates'][$p['template']]) ? $p['template'] : 'page';
        $write($page, $p);
        if (!empty($p['home'])) {
            pb_set_home_page($page['id']);
        }
        foreach ($p['translations'] ?? [] as $locale => $t) {
            if (!in_array($locale, array_slice(pb_site_locales(), 1), true)) {
                continue; // the site's main language is the original itself
            }
            $translation = pb_page_translation($page, $locale);
            if ($translation) {
                pb_page_snapshot($translation, $userId);
            } else {
                $translation = pb_page_find(pb_page_create($t['title'], $page['template'], [], 'published', $t['slug'] ?? $t['title'], $locale, $page['id']));
            }
            $translation['template'] = $page['template'];
            $write($translation, $t);
        }
    }
    foreach ($content['menus'] ?? [] as $location => $items) {
        pb_save_menu($location, $resolve($items));
    }
    if (isset($content['settings'])) {
        // What the content doesn't set (the phone the owner already typed, say) stays as it is.
        pb_save_settings(array_replace_recursive(json_decode(pb_option('theme_settings', '{}'), true) ?: [], $resolve($content['settings'])));
    }
    foreach ($content['settings_translations'] ?? [] as $locale => $texts) {
        if (in_array($locale, array_slice(pb_site_locales(), 1), true)) {
            pb_save_settings_translation($locale, array_replace_recursive(pb_settings_translation($locale), $resolve($texts)));
        }
    }
}

/** The active theme's ready-made content (demo.php, photos in demo/), or null when it has none. */
function pb_theme_demo(): ?array
{
    $file = pb_theme()['dir'] . '/demo.php';
    $demo = is_file($file) ? require $file : null;
    return is_array($demo) ? $demo : null;
}