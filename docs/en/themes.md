# Building a theme

A theme is the whole front end of a PageBrick site: every byte of HTML, CSS and JavaScript the visitor gets. PageBrick gives you the content, already filled in by your client in a simple panel, and stays out of your way.

**English** · [Português](../pt-BR/temas.md) · [Español](../es/temas.md)

## Your freedom, and what you can count on

- **The core adds nothing to the site you didn't ask for.** No CSS, no JavaScript, no cookies, no Content-Security-Policy. SEO tags appear only where you call `pb_head()`, plugin scripts only where you call `pb_footer()`, plugin HTML only where you call `pb_slot()`.
- **Any HTML, CSS and JavaScript.** Use Tailwind, Sass, Vite, Alpine, React islands, plain CSS: whatever you build goes in your theme folder. Any static file under your theme is served as is (PHP files never are).
- **Plugins don't impose their markup.** Your theme can replace any template or stylesheet a plugin shows on the site (see [Replacing a plugin's templates](#replacing-a-plugins-templates)).
- **Headers are yours too.** The core sends `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN` and `Referrer-Policy`; call `header()` in your layout to change them or add your own.
- **Or skip PHP entirely.** Build the front end with Next.js, Astro or an app and read the content as JSON from the [content API](headless.md).
- **Updates don't break your site.** Everything a theme may use is a frozen, versioned API ([compatibility.md](compatibility.md)). On every release, an automated test renders a site built on PageBrick 1.0 and fails if a single character of its HTML (or of its content API answers) changed. If an update breaks something on a real site anyway, it is undone automatically on the first visit.
- **A broken theme doesn't take the site down.** A theme is tested with every page before it is activated; if it fails later, that page is shown with the default theme and the panel tells the administrator what happened.

## Files

```
content/themes/my-theme/
├── theme.json          name, version, API version
├── theme.php           the extra fields, page types, settings and menus your theme adds
├── layout.php          the HTML around every page
├── templates/
│   ├── home.php        required
│   ├── page.php        required
│   ├── services.php    required
│   ├── contact.php     required
│   ├── 404.php         optional
│   ├── closed.php      optional: the whole page shown while the site is under construction or in maintenance
│   └── landing.php     any page type you add
├── plugins/            optional: your copies of plugin templates and stylesheets
├── assets/             anything: CSS, JS, fonts, images, your build output
├── lang/en.php, es.php optional: translations of your theme's texts
├── demo.php, demo/     optional: ready-made content (pages, photos, menus, settings)
└── screenshot.webp     optional: picture shown on the Themes screen (800×500)
```

The fastest start is copying `content/themes/default/` and changing the name in `theme.json`:

```json
{
    "name": "My Theme",
    "version": "1.0.0",
    "description": "One sentence shown on the Themes screen.",
    "author": "Your agency",
    "api": 1
}
```

`"api": 1` is the version of the PageBrick API the theme was written for. A theme for another API version is refused instead of breaking the site.

## Content: the standard contract

Every PageBrick site has the same basic content, defined by the core in [core/standard.php](../../core/standard.php): four page types (`home`, `page`, `services`, `contact`), the settings under **Appearance & contact** and two menus (`main`, `footer`). Every theme must show this content. That is why a client can switch themes without losing anything, and why your theme works with any site.

| Page type | Fields |
|---|---|
| `home` | `hero` (title, text, button_label, button_link, image) · `about` (title, text, image) · `services` (title, intro, items[title, text, image], link_label, link) · `numbers` (title, items[value, label]) · `testimonials` (title, items[quote, name, role]) · `cta` (title, text, button_label, button_link). Every section can be hidden by the client. |
| `page` | `intro`, `image`, `body` |
| `services` | `intro`, `items[title, text, image]`, `cta` (title, text, button_label, button_link) |
| `contact` | `intro`, `body` (the contact form plugin adds the form through `pb_slot('contact')`) |

| Settings | Fields |
|---|---|
| `identity` | `logo`, `icon`, `color`, `fonts` (`sobria`, `elegante` or `acolhedora`: clean, elegant or friendly), `share_image` |
| `contact` | `whatsapp`, `whatsapp_message`, `phone`, `email`, `address`, `hours` |
| `social` | `instagram`, `facebook`, `linkedin`, `youtube`, `tiktok` |
| `footer` | `text`, `credit` |

## Adding your own fields and page types

`theme.php` returns only what your theme **adds**. The panel builds the forms by itself.

```php
<?php
return [
    'templates' => [
        // A field added to the standard home page:
        'home' => ['fields' => [
            'video' => ['type' => 'url', 'label' => 'Hero video'],
        ]],
        // A new page type (templates/landing.php):
        'landing' => ['label' => 'Campaign page', 'fields' => [
            'offer' => ['type' => 'text', 'label' => 'Offer'],
            'perks' => ['type' => 'list', 'label' => 'Perks', 'item_label' => 'Perk', 'add_label' => 'Add perk', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Perk'],
                'icon' => ['type' => 'image', 'label' => 'Icon'],
            ]],
            'cta' => ['type' => 'group', 'label' => 'Button', 'toggle' => true, 'fields' => [
                'label' => ['type' => 'text', 'label' => 'Text'],
                'link' => ['type' => 'link', 'label' => 'Goes to'],
            ]],
        ]],
    ],
    'settings' => [
        'agency' => ['type' => 'group', 'label' => 'Agency', 'fields' => [
            'accent' => ['type' => 'color', 'label' => 'Accent color', 'default' => '#0a7c66'],
        ]],
    ],
    'menus' => ['top' => 'Top bar menu'],
];
```

You can add fields, page types, settings and menus; you can't remove or change the standard ones (the core keeps its version). What you add stays saved if the site switches to another theme, and comes back if it switches back.

**Field types:** `text`, `textarea`, `richtext` (a small editor; HTML cleaned when saved), `image`, `url`, `email`, `tel`, `color`, `select` (with `'options' => ['value' => 'Label']`), `link` (a page of the site or any address), `list` (repeatable items, with `fields`), `group` (with `fields`; `'toggle' => true` lets the client hide it). Every field takes `label`, `help` and `default`.

Need helper functions? Put them in a file and `require_once __DIR__ . '/functions.php';` at the top of `theme.php`.

## Layout and templates

`layout.php` is your page skeleton. It receives `$content` (the template's HTML), `$site` (the settings) and `$siteName`:

```php
<!doctype html>
<html lang="<?= e(pb_locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?= e(pb_theme_url('assets/style.css')) ?>">
<?= pb_head(['image' => $site->identity->share_image->url()]) ?>
</head>
<body>
<header>
    <a href="<?= e(pb_url('/')) ?>"><?= $siteName ?></a>
    <nav><?= pb_menu_html('main') ?></nav>
</header>
<main><?= $content ?></main>
<?= pb_footer() ?>
</body>
</html>
```

A template receives `$page` (the page's fields), `$title` and `$isHome`:

```php
<h1><?= $title ?></h1>
<?php if ($page->hero->visible()): ?>
    <section class="hero">
        <h2><?= $page->hero->title ?></h2>
        <?= $page->hero->image->img(lazy: false) ?>
        <?php if (!$page->hero->button_label->isEmpty()): ?>
            <a href="<?= e($page->hero->button_link->url()) ?>"><?= $page->hero->button_label ?></a>
        <?php endif ?>
    </section>
<?php endif ?>
<?php foreach ($page->services->items as $item): ?>
    <article><h3><?= $item->title ?></h3><p><?= $item->text ?></p></article>
<?php endforeach ?>
<?= pb_slot('home') ?>
```

### Values are safe by default

Printing a field with `<?= ?>` is always safe: text is escaped, rich text was cleaned when saved, an image becomes an `<img>` tag. Your client can't break your layout or inject scripts.

| On a field | Gives you |
|---|---|
| `<?= $page->title ?>` | safe HTML |
| `->raw()` | the stored value, unescaped: escape it yourself with `e()` |
| `->isEmpty()` | `true` when the client left it blank |
| `->url($size = 'full')` | the address of a link, page, e-mail (`mailto:`), phone (`tel:`) or image (`'thumb'` for the small one). Escape it: `e($value->url())` |
| `->img($class = '', $size = 'full', $lazy = true)` | `<img>` with `alt`, `width`, `height` and lazy loading; `lazy: false` for images at the top |
| a group: `->visible()` | `false` when the client hid that section |
| a list: `foreach`, `count()`, `->isEmpty()` | its items, each one a group |

With `'debug' => true` in `config.php`, reading a field that doesn't exist raises a warning, so typos show up while you develop.

### Helpers

| Function | Use |
|---|---|
| `e($text)` | escape any text you print yourself |
| `pb_url('/path')`, `pb_absolute_url('/path')` | addresses that work when the site lives in a subfolder |
| `pb_theme_url('assets/app.js')` | a file of your theme, with `?v=` so browsers pick up changes |
| `pb_head($options)` | title, description, canonical, Open Graph and plugin tags. Leave it out and write your own if you prefer |
| `pb_footer()` | plugin scripts and, for an administrator previewing a theme, the preview bar |
| `pb_slot('home')`, `pb_slot('contact')` | where plugins add HTML; call them in those two templates |
| `pb_menu_html('main', 'class')`, `pb_menu('main')` | a menu as `<ul>`, or as an array of `label`, `url`, `current` to build your own markup |
| `pb_whatsapp_url($number, $message)`, `pb_map_url($address)` | WhatsApp and map links |
| `pb_text_color_on($color)`, `pb_readable_color($color)`, `pb_contrast($a, $b)` | colors that stay readable whatever brand color the client picks |
| `pb_is_logged_in()` | show hints only the site owner sees ("fill in your phone number") |
| `pb_locale()`, `pb_date($datetime)`, `__('text')` | language and dates of the site |

The complete list of what themes and plugins may rely on is [core/api.php](../../core/api.php). Anything else, even if it starts with `pb_`, is internal and may change.

## Replacing a plugin's templates

Plugins render their public pages inside your layout. When their markup isn't what you want, copy the file into your theme, under `plugins/{plugin}/` with the same path, and change it:

```
content/plugins/blog/templates/list.php        →  content/themes/my-theme/plugins/blog/templates/list.php
content/plugins/contact-form/form.php          →  content/themes/my-theme/plugins/contact-form/form.php
content/plugins/contact-form/style.css         →  content/themes/my-theme/plugins/contact-form/style.css
```

- Files you don't copy keep coming from the plugin, even when your copy includes them (`pb_include(__DIR__ . '/cards.php', …)` still finds the plugin's `cards.php`).
- An empty `style.css` copy removes the plugin's styles, so you can style its markup in your own CSS.
- The panel never uses your copies: plugin screens stay as the plugin made them.
- If your copy breaks, the theme is blamed, not the plugin: the page falls back to the default theme and the plugin keeps working.
- When a plugin changes a template in a new version, your copy keeps working as it is. Compare with the new version when you update your theme.

## Ready-made content

A theme can bring its own content, so a new site looks finished the moment it is activated. Add `demo.php` returning pages, photos (files in `demo/`), menus and settings, in the same format as the core's example site ([core/standard.php](../../core/standard.php), `pb_standard_demo()`). Inside it, `page:{slug}` and `media:{key}` point to its own pages and photos.

The administrator imports it with **Import the theme's content** in **Settings → Themes**. Pages with the same address get the new content (their previous version stays in their history), new pages are created, menus are replaced, and settings the content doesn't mention stay as they are. To turn a page into another one (the standard Services page into Features, say), give the new page `'replaces' => 'servicos'`: it takes over that page and its translations, at the new address.

## Under construction and maintenance

While the site is **Under construction** or in **Maintenance** (dashboard → Site status), visitors get a 503 answer and a short notice; people logged in see the site normally. Draw that notice yourself in `templates/closed.php`: a complete HTML document that receives `$mode`, `$title`, `$message`, `$site` and `$siteName`. If it breaks, the core's own notice is shown.

## Translations

Wrap your theme's own texts in `__()` and add `lang/en.php` and `lang/es.php` returning `['Portuguese or source text' => 'translation']`. The site's main language is chosen at installation (Portuguese, English or Spanish).

## Sites in more than one language

A site can be offered in extra languages (Settings, the gear icon → Address and languages). The main language lives at the root (`/about`); the others under a prefix: `/pt-br/sobre`, `/es-es/nosotros`, `/en-us/about`. Each page gets its translations in **Pages**, menus lead to the translated pages, and the texts of Appearance & contact can be translated too. Your templates don't change: `$page`, `$site`, menus and `__()` already speak the visitor's language, and `pb_head()` adds the `hreflang` tags search engines need.

Add a language switcher with `pb_language_links()`. It returns the site's languages as `locale`, `name`, `url` and `current`, each leading to this page in that language (or to that language's home page), and an empty list on a one-language site:

```php
<?php if (count($languages = pb_language_links()) > 1): ?>
    <select onchange="location.href = this.value" aria-label="<?= e(__('Idioma')) ?>">
        <?php foreach ($languages as $language): ?>
            <option value="<?= e($language['url']) ?>"<?= $language['current'] ? ' selected' : '' ?>><?= e($language['name']) ?></option>
        <?php endforeach ?>
    </select>
<?php endif ?>
```

`pb_content_locale()` tells which language the visitor is reading, and `pb_page_translation($page, $locale)` finds a page in another language.

## Testing and shipping

1. Activate it in **Settings → Themes**. PageBrick renders every page of the site with your theme first and refuses it, with the reason, if anything fails. **Preview** shows the site with your theme only to you.
2. Zip the theme folder (`my-theme.zip` containing `my-theme/`) and upload it on another site in **Settings → Themes → Upload theme**.
3. To offer it in the official catalog, see [publishing.md](publishing.md).
