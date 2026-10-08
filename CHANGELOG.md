# Changelog

Versions follow [semantic versioning](https://semver.org/). Within API version 1, nothing in [core/api.php](core/api.php) is removed or changed ([docs/en/compatibility.md](docs/en/compatibility.md)). Any change to the HTML the core prints for themes is listed under **HTML changes**.

## 1.1.0 (in development)

- Sites in more than one language: extra languages under /pt-br, /es-es or /en-us, page translations, translated menus and Appearance & contact texts, `hreflang` tags, and `pb_language_links()` for themes. Ready-made content can bring translations.
- Ready-made content can turn a page into another one (`'replaces'`): the standard Services page becomes Features at its new address, translations included.
- Light/dark switch in the panel header (remembered per browser; follows the system until used).

## 1.0.0

First public version.

- Step-by-step installer like WordPress's (language, server check, database with readable errors, site), in Portuguese, English or Spanish, with a finished example site.
- Pages with fields, hideable sections, history, drafts and preview; media library with WebP; menus; SEO basics.
- Default theme; themes as packages with preview, test before activation and circuit breaker.
- Plugins with hooks, slots, routes, panel screens, settings and their own tables; circuit breakers and safe mode.
- Official plugins: contact form and blog with categories.
- Signed catalog, .zip upload, backups, roll back, automatic undo of broken updates, and core updates that check the whole site.
- Themes can replace any template or stylesheet a plugin shows on the site, and bring ready-made content (`demo.php`).
- "Under construction" and "Maintenance" switches on the dashboard; themes can draw the notice in `templates/closed.php`.
- Content API (JSON) for headless front ends: `/api/v1/site`, `/api/v1/pages`, `/api/v1/pages/{slug}` and, with the Blog, `/api/v1/blog`.
- Public API version 1 frozen in `tests/fixtures/api-v1.json`.
