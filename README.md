<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/brand/svg/pagebrick-logo-horizontal-negativo.svg"><img src="docs/brand/svg/pagebrick-logo-horizontal.svg" alt="PageBrick" width="320"></picture></p>

<p align="center"><b>English</b> · <a href="README.pt-BR.md">Português</a> · <a href="README.es.md">Español</a></p>

A simple CMS for business websites. A non-technical owner installs it and publishes the company site with a ready-made layout; agencies build their own themes on an organized structure, with complete freedom on the front end and the guarantee that updates won't break the sites they deliver.

## What it does

- Step-by-step installer like WordPress's (language, server check, database, site), which creates a finished example site: Home, About, Services, Contact and Privacy policy, with photos. In Portuguese, English or Spanish.
- Pages with fields, sections the client can hide, history with "restore", drafts and preview.
- Logo, color, font style, WhatsApp, contact details and social links under **Appearance & contact**.
- Themes you can switch without losing content, with a private preview before activating.
- Contact form (messages in the panel and by e-mail, spam protection without captcha) and a blog with categories, as official plugins.
- Plugin and theme store with signed packages, .zip upload, updates with backup, "roll back" and automatic undo if a new version breaks.
- Circuit breakers: a broken plugin or theme is switched off on its own and the site stays up. Safe mode with a rescue link.
- One-click updates of PageBrick itself that check the whole site afterwards and go back on their own if anything broke.
- Sites in more than one language: the main one at the root, others under /pt-br, /es-es or /en-us, with translated pages, menus and texts.
- Light and dark panel, following the system or a switch in the header.
- Content API (JSON) for front ends built with Next.js, Astro or any other framework.
- "Under construction" and "Maintenance" switches: visitors see a notice, people logged in see the site.
- SEO basics (title and description per page, friendly addresses, sitemap.xml, robots.txt), images resized to WebP, no cookies for visitors.
- Two roles: Administrator (the agency) and Editor (the client).

## Requirements

PHP 8.2+ with `pdo_mysql` and `gd`, MySQL 5.7+ or MariaDB 10.4+, and Apache with `mod_rewrite`: any cPanel hosting.

## Installing

1. Download `pagebrick-x.y.z.zip` from [Releases](https://github.com/pagebrick/pagebrick/releases) and upload the contents of its `pagebrick/` folder to your hosting.
2. Create a MySQL database (in cPanel: "MySQL Databases").
3. Open the site's address and fill in the installer.

## For developers

- [Building a theme](docs/en/themes.md): the front end is entirely yours.
- [Building a plugin](docs/en/plugins.md)
- [Content API (headless)](docs/en/headless.md): the content as JSON, for front ends built outside PageBrick.
- [The compatibility promise](docs/en/compatibility.md): why updates don't break your sites.
- [Publishing: catalog, signatures and releases](docs/en/publishing.md)

### Local development

```bash
docker compose up -d --build
docker compose exec app composer install
```

Open http://localhost:8080. In the installer use server `db`, database `pagebrick`, user `pagebrick` and password `pagebrick`.

Test accounts used in local development: `admin@pagebrick.test` (administrator) and `editor@pagebrick.test` (editor), both with the password `pagebrick-local`.

E-mails sent by the local site are caught by Mailpit at http://localhost:8025. To use it, set server `mailpit`, port `1025` and security "None" under **Settings → Email**.

Tests:

```bash
docker compose exec app vendor/bin/phpunit
```

To start over: delete `config.php` and run `docker compose down -v`.

## License

[GPL-3.0-or-later](LICENSE). Created by [Alcateia Digital](https://alcateia.digital). Includes the [Trix](https://github.com/basecamp/trix) editor (MIT), [PHPMailer](https://github.com/PHPMailer/PHPMailer) (LGPL 2.1), the Public Sans, Fraunces and Nunito fonts (OFL) and public-domain example photos (CC0).
