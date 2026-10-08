<?php
// The content API: the site's content as JSON, for front ends built outside PageBrick (Next.js, Astro, an app…).
// Read-only and public, like the site itself: only published pages, never drafts or password fields.
//
//   GET /api/v1/site            name, language, address, settings (Appearance & contact) and menus
//   GET /api/v1/pages           the published pages, without their content
//   GET /api/v1/pages/{slug}    one published page with its fields
//
// Field values: text as typed; rich text as clean HTML; images as {url, thumb, alt, width, height} or null;
// links to pages of the site as paths ("/about"), other links as typed; a section the client hid is null.
// The shape of these answers is part of the compatibility promise (tests/fixtures/sites/v0.1-output).
// Plugins add their own endpoints under /api/v1/{plugin} with pb_add_route(), pb_content_json() and pb_content_send().

const PB_CONTENT_API = '/api/v1';

/** Answers a core endpoint of the content API; false when $path isn't one (a plugin may answer it). */
function pb_content_api(string $path): bool
{
    $rest = substr($path, strlen(PB_CONTENT_API));
    if ($rest === '/site') {
        pb_content_send(pb_content_site());
    } elseif ($rest === '/pages') {
        pb_content_send(['pages' => array_values(array_map('pb_content_page_summary', array_filter(pb_page_list(), fn($row) => $row['status'] === 'published')))]);
    } elseif (preg_match('~^/pages/([a-z0-9-]+)$~', $rest, $m)) {
        $page = pb_page_by_slug($m[1]);
        pb_content_send($page && $page['status'] === 'published' ? pb_content_page($page) : null);
    } else {
        return false;
    }
    return true;
}

/** Sends an answer of the content API: JSON any site may read (CORS), cacheable for a minute. Null answers 404. */
function pb_content_send(?array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: public, max-age=60');
    header('X-Robots-Tag: noindex');
    if ($data === null) {
        http_response_code(404);
        $data = ['error' => 'not_found'];
    }
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Values of $fields (field definitions) as plain JSON data, following the rules at the top of this file. */
function pb_content_json(array $fields, array $data): array
{
    $json = [];
    foreach ($fields as $name => $def) {
        if ($def['type'] !== 'password') {
            $json[$name] = pb_content_value($def, $data[$name] ?? null);
        }
    }
    return $json;
}

function pb_content_value(array $def, mixed $value): mixed
{
    switch ($def['type']) {
        case 'group':
            $value = is_array($value) ? $value : [];
            return ($value['_visible'] ?? true) === false ? null : pb_content_json($def['fields'], $value);
        case 'list':
            return array_map(fn($item) => pb_content_json($def['fields'], (array) $item), is_array($value) ? array_values($value) : []);
        case 'image':
            $media = $value ? pb_media_find((int) $value) : null;
            return $media ? [
                'url' => pb_content_absolute(pb_media_url($media)),
                'thumb' => pb_content_absolute(pb_media_url($media, 'thumb')),
                'alt' => $media['alt'],
                'width' => (int) $media['width'],
                'height' => (int) $media['height'],
            ] : null;
        case 'link':
            return pb_content_link(pb_link_url((string) $value));
        default:
            return (string) ($value ?? '');
    }
}

/** A link as the API gives it: pages of the site as paths from the site's root ("/about"), anything else as it is. */
function pb_content_link(string $url): string
{
    $base = pb_base_path();
    return $base !== '' && str_starts_with($url, "$base/") ? substr($url, strlen($base)) : $url;
}

/** Full address of a file of the site (images must work on another domain). */
function pb_content_absolute(string $url): string
{
    return pb_absolute_url(pb_content_link($url));
}

function pb_content_site(): array
{
    $menus = [];
    foreach (array_keys(pb_theme()['menus'] ?? []) as $location) {
        $menus[$location] = array_map(fn($item) => ['label' => $item['label'], 'link' => pb_content_link($item['url'])], pb_menu($location));
    }
    return [
        'name' => pb_option('site_title', ''),
        'locale' => pb_site_locale(),
        'url' => pb_absolute_url('/'),
        'settings' => pb_content_json(pb_settings_fields(), json_decode(pb_option('theme_settings', '{}'), true) ?: []),
        'menus' => $menus,
    ];
}

function pb_content_page_summary(array $page): array
{
    $home = (int) $page['id'] === pb_home_page_id();
    return [
        'id' => (int) $page['id'],
        'title' => $page['title'],
        'slug' => $page['slug'],
        'path' => $home ? '/' : '/' . $page['slug'],
        'template' => $page['template'],
        'home' => $home,
        'updated_at' => date(DATE_ATOM, strtotime($page['updated_at'])),
    ];
}

function pb_content_page(array $page): array
{
    $templates = pb_theme()['templates'];
    $fields = $templates[$page['template']]['fields'] ?? $templates['page']['fields'] ?? []; // same fallback as the site
    return pb_content_page_summary($page) + [
        'seo' => ['title' => pb_page_seo_title($page), 'description' => $page['seo_description']],
        'fields' => pb_content_json($fields, $page['data']),
    ];
}
