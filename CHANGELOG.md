# Changelog

Versions follow [semantic versioning](https://semver.org/). Within API version 1, nothing in [core/api.php](core/api.php) is removed or changed ([docs/en/compatibility.md](docs/en/compatibility.md)). Any change to the HTML the core prints for themes is listed under **HTML changes**.

## 0.1.0

First public version.

- Web installer in Portuguese, English or Spanish, with a finished example site.
- Pages with fields, hideable sections, history, drafts and preview; media library with WebP; menus; SEO basics.
- Default theme; themes as packages with preview, test before activation and circuit breaker.
- Plugins with hooks, slots, routes, panel screens, settings and their own tables; circuit breakers and safe mode.
- Official plugins: contact form and blog with categories.
- Signed catalog, .zip upload, backups, roll back, automatic undo of broken updates, and core updates that check the whole site.
- Themes can replace any template or stylesheet a plugin shows on the site.
- Content API (JSON) for headless front ends: `/api/v1/site`, `/api/v1/pages`, `/api/v1/pages/{slug}` and, with the Blog, `/api/v1/blog`.
- Public API version 1 frozen in `tests/fixtures/api-v1.json`.
