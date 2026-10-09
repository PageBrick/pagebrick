# Changelog

Versions follow [semantic versioning](https://semver.org/). Within API version 1, nothing in [core/api.php](core/api.php) is removed or changed ([docs/en/compatibility.md](docs/en/compatibility.md)). Any change to the HTML the core prints for themes is listed under **HTML changes**.

## 1.0.5

- E-mails now leave from `no-reply@yourdomain` unless a sender is set in Settings → E-mail.
- Plugins have their own icon in the top bar (a plug), apart from the gear: their screens are listed there, for whoever each plugin allows. Editors can use plugin screens and plugin settings; only administrators install, switch on and off, update and delete plugins ("Manage plugins" in that same menu).
- Each person's preferences are kept with their account and follow them to any browser: the light/dark choice (new) and the panel's language. A new person starts in the site's language. What a browser remembers is never handed to the next person who signs in on it (whoever chose dark before 1.0.5 chooses once more).
- For plugins (API, only additions): `pb_http()` makes a request to another service (https only, with headers, an optional POST body, a size limit, and a way for tests to answer instead of the network).

## 1.0.4

- Sending a theme or plugin (.zip), updating one or installing one from the catalog shows each step as it happens, like PageBrick's own updates: sending or downloading, checking the signature or the package, keeping a copy, installing and, when it's the one in use, opening every page with it. If a page breaks, the previous version is put back right there and the screen says why.
- Backups made in the same second are told apart by version, so "Roll back" always takes the newest.

## 1.0.3

- The panel's language is independent of the site's: each account has its own (set when it is made, changed with the new globe in the top bar or in My account), and "the same as the site" is gone. Accounts that never chose one keep the language they saw. The sign-in screen follows the browser's language.
- Importing a theme's content written in another language offers "Import and make English the site's language": one click, and the panel stays as it is.
- Updating PageBrick on the Updates screen shows each step as it really happens (download, signature, copy, swap, every page opened), like the demo on pagebrick.org.
- Automatic updates also hand the page over first on LiteSpeed servers (as on PHP-FPM), so no visitor waits for the check or the update. Settings → Updates says whether this server makes a visitor wait.

## 1.0.2

- Password fields have an eye: open to show the password, closed to hide it again (sign-in, account, users, e-mail, installer).
- Changing the site's main language no longer changes the panel's: whoever never picked a panel language keeps the one they had.
- Ready-made content can say which language it is written in (`'locale' => 'en'`); importing it on a site with another main language is refused with a clear message, instead of putting English texts where the Portuguese ones belong.
- Themes that can't be deleted say why: the active one (activate another first) and the default one (the safety net).

## 1.0.1

- The dashboard announces new versions, and the gear icon gets a dot. Settings → Updates chooses how PageBrick updates: manual, automatic for fixes only (1.0.x, the default) or automatic for everything. An automatic update has the same backup, page check and undo as the button, never retries a version that was undone, and e-mails the administrators. It runs after a request, at most once an hour.
- Settings move behind a gear icon (administrators only): Address and languages, Themes, Plugins, Updates, E-mail, Users.
- The site's official address can be changed (to https:// after installing without a certificate).
- The main language can change after installing: ready-made content finds the pages at their old addresses (/sobre on a site switched to English) and moves them, instead of making copies.

## 1.0.0

First public version.

- Step-by-step installer (language, server check, database with readable errors, site), in Portuguese, English or Spanish, with a finished example site.
- Pages with fields, hideable sections, history, drafts and preview; media library with WebP; menus; SEO basics.
- "Forgot my password" on the sign-in screen: an e-mailed link that works once, for one hour (only its hash is stored), with the same answer whether the e-mail has an account or not.
- Sites in more than one language: extra languages under /pt-br, /es-es or /en-us, page translations, translated menus and Appearance & contact texts, `hreflang` tags, and `pb_language_links()` for themes.
- Light/dark switch in the panel header (remembered per browser; follows the system until used).
- Default theme; themes as packages with preview, test before activation and circuit breaker.
- Plugins with hooks, slots, routes, panel screens, settings and their own tables; circuit breakers and safe mode.
- Official plugins: contact form and blog with categories.
- Signed catalog, .zip upload, backups, roll back, automatic undo of broken updates, and core updates that check the whole site.
- Themes can replace any template or stylesheet a plugin shows on the site, and bring ready-made content (`demo.php`) with translations, which can also turn a standard page into another one (`'replaces'`).
- "Under construction" and "Maintenance" switches on the dashboard; themes can draw the notice in `templates/closed.php`.
- Content API (JSON) for headless front ends: `/api/v1/site`, `/api/v1/pages`, `/api/v1/pages/{slug}` and, with the Blog, `/api/v1/blog`.
- Public API version 1 frozen in `tests/fixtures/api-v1.json`.
