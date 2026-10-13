# The compatibility promise

Agencies build real sites for real clients on PageBrick. Clicking **Update** in the panel must never break one of them. This page explains what is promised, how it is enforced, and what happens on an update.

**English** · [Português](../pt-BR/compatibilidade.md) · [Español](../es/compatibilidad.md)

## What theme and plugin developers can count on

Within one API version (`"api": 1` in `theme.json` / `plugin.json`):

1. **Nothing in the public API is removed or changed.** The public API is the list in [core/api.php](../../core/api.php): functions, the field classes (`PbGroup`, `PbList`, `PbValue`), constants and hooks. No function disappears, no parameter is renamed, reordered or made required, no return type changes, no hook stops being fired. New things may be added.
2. **The standard content stays.** The page types, fields, settings and menus of [core/standard.php](../../core/standard.php) are part of the API: none is removed or changes type.
3. **The HTML your site delivers doesn't change.** What the core prints for you (`pb_head()`, menus, images, rich text, escaping) stays byte for byte the same, and so do the answers of the content API (`/api/v1/`).
4. **Your files are never touched.** An update replaces only `core/`, `vendor/` and `index.php`. Everything under `content/` (themes, plugins, uploads), `config.php` and `.htaccess` stay as they are.
5. **The database only grows.** Migrations add tables and columns; they never drop or rename what an older version used.

Anything not listed in `core/api.php`, even a function starting with `pb_`, is internal and may change. The panel's look and wording may change too.

## How it is enforced

Every release must pass these automated tests ([tests/CompatibilityTest.php](../../tests/CompatibilityTest.php)):

| Guarantee | Test |
|---|---|
| The API keeps its promise | `core/api.php` is frozen in `tests/fixtures/api-v1.json`; the test fails if anything frozen is missing or changed |
| Sites built on 1.0 keep working | `tests/fixtures/sites/v1.0` is an agency site built on 1.0 (its own theme with extra fields and page types, its own plugin with tables, routes, hooks and a panel screen, plus an official plugin). It is never edited, and every version must run it |
| Their HTML and API answers don't change | `tests/fixtures/sites/v1.0-output` holds the exact pages and content API answers of that site; one changed character fails the test |
| Updates the site isn't ready for are refused | see below |
| Updates that break the site are undone | a broken plugin, a broken theme, a fatal error during the check and a version that can't even start are all simulated |

## What happens when someone clicks Update

1. **Before:** the panel checks the new version against the site. If the server's PHP is too old, or an active plugin or the theme was made for an API version the new release doesn't support, the update is refused and the panel says which one and what to do.
2. **The package:** downloaded from the official catalog and checked against the project's Ed25519 signature, so a tampered or substituted file is refused.
3. **Backup:** the current `core/`, `vendor/` and `index.php` are zipped into `content/backups/` (not reachable from the web).
4. **Swap:** each folder is swapped in one step, so visitors never see half an update.
5. **Check:** the first request on the new version renders every published page, the 404 page and the sitemap behind the scenes. If anything fails, or a plugin got switched off, the previous version comes back with plugins and theme exactly as they were, and the panel explains what broke.
6. **Safety net:** if the new version can't even start (a PHP fatal error in the core), `index.php` restores the backup on its own.
7. **Later:** **Roll back to version x.y** stays available in **Settings → Updates**.

## If an API version 2 ever comes

It would exist only for a change that can't be made by adding. A release that supports both declares `"api": [1, 2]` in the catalog and sites update normally. A release that drops version 1 is refused by every site that still has a version 1 theme or active plugin, with a message saying which one to update first.

## For core contributors

- Adding to the public API: add it to `core/api.php`, then run `php tools/pagebrick.php api-snapshot`. The command refuses to freeze a version that breaks the current promise.
- Never edit `tests/fixtures/sites/v1.0` or `v1.0-output`. If a change makes them fail, the change is what must be fixed. To cover features of a later version, add a new frozen site (`sites/v1.1/`) next to it.
- Migrations only add. Never `DROP` or `RENAME` something an earlier version used.
- If a change to the HTML the core prints is truly needed (a security fix, say), it is a visible change for every site: note it in [CHANGELOG.md](../../CHANGELOG.md) under "HTML changes", then regenerate the snapshot by deleting the affected file in `v1.0-output` and running the tests.
