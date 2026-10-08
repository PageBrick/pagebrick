# Content API (headless)

Prefer building the front end with Next.js, Astro, Nuxt, SvelteKit or a mobile app? PageBrick also gives the site's content as JSON. Your client keeps editing in the same simple panel; your front end reads the content and does the rest.

**English** · [Português](../pt-BR/headless.md) · [Español](../es/headless.md)

## Endpoints

All public, read-only, `GET`, under `/api/v1/` of the PageBrick site:

| Address | Gives you |
|---|---|
| `/api/v1/site` | site name, language, address, the settings under **Appearance & contact**, and every menu |
| `/api/v1/pages` | the published pages, without content: `id`, `title`, `slug`, `path`, `template`, `home`, `updated_at` |
| `/api/v1/pages/{slug}` | one published page: the same plus `seo` (`title`, `description`) and `fields` |
| `/api/v1/blog?page=2&category={slug}` | with the Blog plugin on: posts (newest first, without the text), pages, total and every category |
| `/api/v1/blog/{slug}` | with the Blog plugin on: one post with its text |

Anything else under `/api/` answers `404` with `{"error": "not_found"}`.

```bash
curl https://example.com/api/v1/pages/about
```

```json
{
    "id": 2,
    "title": "About",
    "slug": "about",
    "path": "/about",
    "template": "page",
    "home": false,
    "updated_at": "2026-10-07T22:48:00+00:00",
    "seo": {"title": "About · Acme Bakery", "description": ""},
    "fields": {
        "intro": "Tell how the business started…",
        "image": {"url": "https://example.com/content/uploads/2026/10/a408f3b279821d30.webp", "thumb": "https://example.com/content/uploads/2026/10/a408f3b279821d30-thumb.webp", "alt": "Our team", "width": 1600, "height": 1067},
        "body": "<h2>Our story</h2><p>…</p>"
    }
}
```

## Field values

The fields are the same ones the panel shows: the [standard content](themes.md#content-the-standard-contract) plus whatever the active theme adds.

| Field type | In JSON |
|---|---|
| `text`, `textarea`, `email`, `tel`, `url`, `color`, `select` | the text as typed (a `select` gives the option's key) |
| `richtext` | HTML, already cleaned when it was saved |
| `image` | `{url, thumb, alt, width, height}` with full addresses, or `null` |
| `link` | a page of the site as a path (`"/about"`, `"/"` for the home page); any other link as typed (`"https://…"`) |
| `list` | an array of objects |
| `group` | an object, or `null` when the client hid that section |

Password fields are never included.

## How to use it

- **Routing:** fetch `/api/v1/pages` to build your routes (each page has its `path`), then `/api/v1/pages/{slug}` for each one. The page with `"home": true` lives at `/`.
- **Links:** a link starting with `/` is a page of the site: hand it to your router. Anything else is an outside address.
- **Page types:** `template` tells you which layout to use (`home`, `page`, `services`, `contact` or a type your theme adds).
- **Your own fields:** add them in a theme, as in [themes.md](themes.md#adding-your-own-fields-and-page-types). The theme only needs `theme.json`, `theme.php`, a `layout.php` and the four required templates (they can be minimal or redirect to your front end); the panel builds the forms from it and the API delivers them.
- **Cache:** answers can be cached for 60 seconds. For static builds, rebuild when the client publishes (for example with a scheduled build).
- **Other domains:** the API sends `Access-Control-Allow-Origin: *`, so browsers on any domain can read it. It never uses cookies.
- **Drafts:** not in the API. Only published pages appear.

## What you can count on

The shape of these answers is part of the [compatibility promise](compatibility.md): within `/api/v1/`, nothing is removed or renamed. An automated test freezes the API answers of a site built on PageBrick 1.0 and fails if any of them changes. New fields may appear; your code should ignore keys it doesn't know.

## Endpoints for plugins

A plugin can add its own endpoints under `/api/v1/{plugin}`:

```php
pb_add_route('GET', '/api/v1/offers', function () {
    pb_content_send(['offers' => myplugin_offers()]);     // JSON, CORS and cache headers; null answers 404
});
pb_add_route('GET', '/api/v1/offers/*', function (string $slug) {
    $offer = myplugin_find($slug);
    pb_content_send($offer ? ['title' => $offer['title'], 'fields' => pb_content_json(myplugin_fields(), $offer['data'])] : null);
});
```

`pb_content_json($fields, $data)` turns values stored with field definitions into the JSON described above. See [core/headless.php](../../core/headless.php) and the Blog plugin for a complete example.
