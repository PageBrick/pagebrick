# Building a theme

A theme is the whole front end of a PageBrick site: every byte of HTML, CSS and JavaScript the visitor gets. PageBrick gives you the content, already filled in by your client in a simple panel, and stays out of your way.

**English** · [Português](../pt-BR/temas.md) · [Español](../es/temas.md)

## Your freedom, and what you can count on

- **The core adds nothing to the site you didn't ask for.** No CSS, no JavaScript, no cookies, no Content-Security-Policy (the owner can ask for Google Analytics 4 and the Meta pixel in **Settings → Tracking**: until then `pb_head()` and `pb_footer()` print nothing of it, and after it the visitor is asked first, by default). SEO tags appear only where you call `pb_head()`, plugin scripts only where you call `pb_footer()`, plugin HTML only where you call `pb_slot()`. The one exception is for people signed in to the panel, never for visitors: where you call `pb_footer()` they get a small bar at the bottom of the page (the way back to the panel, quick actions and a live view). It draws itself in a shadow root, away from your CSS, and you can switch it off: `pb_add_filter('admin_bar', fn() => false);` in your `layout.php`, before `pb_footer()`.
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

The fastest start is copying `content/themes/default/` under another name and changing the name in `theme.json`. Do not edit the default theme in place: it is PageBrick's own (a site falls back to it when its theme breaks), so an update renews it (the old folder is kept in the backups):

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

Every PageBrick site has the same basic content, defined by the core in [core/standard.php](../../core/standard.php): four page types (`home`, `page`, `services`, `contact`), the settings under **Contact** and **Appearance** and two menus (`main`, `footer`). Every theme must show this content. That is why a client can switch themes without losing anything, and why your theme works with any site.

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

The settings you add show under **Contact**, the screen anyone who edits the site can open. A group about the look (colors, typography) can ask for **Settings → Appearance**, which only administrators open, with `'screen' => 'appearance'`.

**Field types:** `text`, `textarea`, `richtext` (a text editor: headings, bold, italic, lists, quotes, links, tables, dividers and pictures from the media library; the HTML is cleaned when saved), `image`, `url`, `email`, `tel`, `color`, `select` (with `'options' => ['value' => 'Label']`), `link` (a page of the site or any address), `list` (repeatable items, with `fields`), `group` (with `fields`; `'toggle' => true` lets the client hide it). Every field takes `label`, `help` and `default`.

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
| `->img($class = '', $size = 'full', $lazy = true, $sizes = '')` | `<img>` with `alt`, `width`, `height` and lazy loading; `lazy: false` for images at the top. With `sizes:` it also prints a `srcset` (see below) |
| a group: `->visible()` | `false` when the client hid that section |
| a list: `foreach`, `count()`, `->isEmpty()` | its items, each one a group |

A phone does not need a 1920-pixel picture. PageBrick keeps three copies of every photo (480, 960 and the full width, up to 1920), and `img(sizes: '…')` lets the browser take the smallest one that is sharp enough. `sizes` is the HTML attribute: say how wide the picture is on the page, for example `img(sizes: '(min-width: 900px) 560px, 94vw')`. For a picture far down the page (lazy), `sizes: 'auto'` lets the browser measure the place by itself; for one at the top, give the real width, such as `'100vw'` for a full-width banner. Without `sizes` the tag is exactly the one of older versions, so a theme changes only when its author chooses to. Photos sent before PageBrick 1.8.0 have only the thumbnail and the full one until **Media → Redo the sizes** is used.

**The cookie notice and tracking.** When the owner saves a Google Analytics 4 or Meta pixel code in Settings → Tracking, `pb_head()` and `pb_footer()` write the official snippet themselves (a theme changes nothing) and, by default, `pb_footer()` also prints a plain "Accept / Decline" notice; Google and Meta are loaded only after "Accept", and the choice is kept in the browser. Nobody signed in to the panel is counted, and nothing is while the site is not live. The notice has inline styles, like the bars, and a theme can draw its own with the `consent_html` filter (it receives the notice and the tools' names): the buttons are any element with `data-pb-consent="yes"` or `"no"`, the box is `#pb-consent` (hidden until needed, shown with `display:flex`), and `data-pb-consent="ask"` on any link shows it again. The same screen takes the Path Ranker behaviour collector: the owner pastes its snippet, PageBrick checks that it is exactly one `<script src=".../pr-collect.js" data-key="…">` (https; http only for localhost) and writes the tag again, escaped, in `pb_head()`. It uses no cookies, so it does not wait for the notice.

With `'debug' => true` in `config.php`, reading a field that doesn't exist raises a warning, so typos show up while you develop.

### Rich text: style what your client can write

A `richtext` field can hold paragraphs, `h2`/`h3`, `strong`, `em`, `u`, `s`, `a`, lists, `blockquote`, `hr`, `table` (with `thead`/`tbody`, `tr`, `th`, `td`) and `img`. The core adds no CSS, so print it inside a container of yours and style these elements there, tables and pictures included:

```css
.prose img { max-width: 100%; height: auto; }
.prose table { display: block; overflow-x: auto; border-collapse: collapse; } /* a wide table scrolls inside the text */
.prose th, .prose td { padding: .5rem .75rem; border: 1px solid #ddd; }
```

Pictures come only from the media library: PageBrick writes the `<img>` itself (with `alt`, `width`, `height` and lazy loading), so it keeps working if the site moves. Text written before PageBrick 1.3 (with `div` and `br`) is shown exactly as it was saved.

### Helpers

| Function | Use |
|---|---|
| `e($text)` | escape any text you print yourself |
| `pb_url('/path')`, `pb_absolute_url('/path')` | addresses that work when the site lives in a subfolder |
| `pb_theme_url('assets/app.js')` | a file of your theme, with `?v=` so browsers pick up changes |
| `pb_head($options)` | title, description, canonical, Open Graph and plugin tags. Leave it out and write your own if you prefer |
| `pb_footer()` | plugin scripts, the bar of people signed in to the panel and, for an administrator previewing a theme, the preview bar; and, only when the owner saved Google Analytics or Meta codes in Settings → Tracking, the cookie notice (see below) |
| `pb_slot('home')`, `pb_slot('contact')` | where plugins add HTML; call them in those two templates |
| `pb_menu_html('main', 'class')`, `pb_menu('main')` | a menu as `<ul>`, or as an array of `label`, `url`, `current` to build your own markup |
| `pb_whatsapp_url($number, $message)`, `pb_map_url($address)` | WhatsApp and map links |
| `pb_text_color_on($color)`, `pb_readable_color($color)`, `pb_contrast($a, $b)` | colors that stay readable whatever brand color the client picks |
| `pb_is_logged_in()` | show hints only the site owner sees ("fill in your phone number") |
| `pb_locale()`, `pb_date($datetime)`, `__('text')` | language and dates of the site |

The complete list of what themes and plugins may rely on is [core/api.php](../../core/api.php). Anything else, even if it starts with `pb_`, is internal and may change.

## Letting the client reorder the sections

In the page editor the client sees the page beside the form, changes it, drags sections to a new place (in the list or on the page itself) and only then clicks **Publish**; until then visitors keep seeing the published version. The ordering is up to your theme, in two steps, and a theme that does nothing keeps its own fixed order (nothing breaks):

1. List the sections in `theme.php`, under the template's `'sections'`. Each one is the name of a `group` field of that template (the standard `hero`, `about`… of the home page, or your own groups), or `'plugins'`, the place where plugins add their sections with `pb_slot('home')`.
2. Print them in the client's order in the template: `$page->order($names)` gives your names in that order (the ones the client has not placed yet come last), and `pb_section($name, $html)` returns your HTML exactly as it is for visitors, only marking the section while the client edits so it can be picked and dragged on the page.

```php
// theme.php: the sections of the home page the client may put in any order
return [
    'templates' => [
        'home' => ['sections' => ['hero', 'about', 'services', 'numbers', 'testimonials', 'plugins', 'cta']],
    ],
];
```

```php
<?php // templates/home.php
foreach ($page->order(['hero', 'about', 'services', 'numbers', 'testimonials', 'plugins', 'cta']) as $section) {
    echo pb_section($section, pb_include(__DIR__ . "/parts/home-$section.php", ['page' => $page, 'title' => $title]));
}
```

Fields that are not listed (a hero that always stays on top, say) are printed wherever you print them. The content API adds `section_order` to a page only when the client chose an order. While the client edits, the page in the preview has class `pb-editing` on `<html>`: use it to switch off what waits for a scroll to show itself (fade-ins, entrance animations), because sections are replaced there while they are being looked at.

For a theme that must also run on a PageBrick older than 1.3, check `method_exists($page, 'order')` and `function_exists('pb_section')` first.

## Letting the client add sections

Besides moving the sections you made, the client can **add** more: text, photo with text, cards, questions and answers, testimonials, call to action, numbers, photo gallery and YouTube video. In the page editor an **Add section** button, and a "+" between the sections (in the list and on the page itself), open a gallery with a drawing of each kind; the client can also duplicate a section or remove one (with **Undo**). The core keeps them with the page and prints them as **plain HTML with no CSS and no script**, with stable class names: the look is your job, in your theme.

1. Turn them on in `theme.php`, for each page type that should have them: `'blocks' => true` for every kind, or a list such as `'blocks' => ['text', 'cards']`. A theme that does not say it gets none, and nothing changes.
2. Print the page with `pb_sections($page, $names, $render)`. It goes through the sections in the order the client chose, calls your `$render($name)` for each name of yours and prints each section the client added, marking all of them while the client edits.

```php
// theme.php
return ['templates' => [
    'page' => ['sections' => ['content'], 'blocks' => true], // 'content': what the template's own fields print (title, text…)
    'home' => [
        'sections' => ['hero', 'about' => 'image_text', 'services' => 'cards', 'cta' => 'cta'],
        'blocks' => true,
    ],
]];
```

```php
<?php // templates/page.php
echo pb_sections($page, ['content'], fn($name) => pb_include(__DIR__ . '/parts/page-content.php', ['page' => $page, 'title' => $title]));
```

A name with a kind (`'about' => 'image_text'`) says that your section holds the same thing as that kind of section: the panel then offers **Duplicate** on it, and the copy is a section the client can also remove. A name without a kind (`'hero'`) cannot be copied. `'content'` is the name to give to what the template's own fields print (the title, the introduction, the text), so the client can put sections before it too.

### What each kind prints

Each one is a `<section class="pb-block pb-block-{kind}">` with an `h2` when it has a title; what is empty is left out.

| Kind | Fields | Inside |
|---|---|---|
| `text` | `title`, `text` | `.pb-block-body` |
| `image_text` | `title`, `text`, `image`, `side` (`left` or `right`) | `.pb-block-image` and `.pb-block-words`, in the order seen; class `pb-side-left` or `pb-side-right` |
| `cards` | `title`, `intro`, `items` (`title`, `text`, `image`, `link`), `link_label`, `link` | `ul.pb-cards > li.pb-card` |
| `faq` | `title`, `items` (`question`, `answer`) | `details.pb-faq-item`: the browser's own, no script |
| `testimonials` | `title`, `items` (`quote`, `name`, `role`) | `figure.pb-quote` |
| `cta` | `title`, `text`, `button_label`, `button_link` | `a.pb-button` |
| `numbers` | `title`, `items` (`value`, `label`) | `ul.pb-numbers > li.pb-number` |
| `gallery` | `title`, `items` (`image`, `caption`) | `ul.pb-gallery` with a `figure` each |
| `video` | `title`, `url`, `caption` | `figure.pb-video > iframe` from youtube-nocookie.com; only the video's id is ever taken from a YouTube address (`pb_youtube_id()`) |

### Drawing one your own way

Put a file named after the kind in `templates/blocks/` of your theme (`templates/blocks/cards.php`). It runs with `$block` (its fields, read like `$page`: `$block->title`, `$block->items`), `$type`, `$id` and `$page`. A kind without a file of yours is drawn by the core; copy one from `core/blocks/` to start.

The sections live in the page's data, so a theme that does not turn them on hides them, and switching back brings them back. The content API lists the ones on the site in `blocks` (each one `{id, type, fields}`), and `section_order` names them `block-{id}`. For a theme that must also run on a PageBrick older than 1.3, check `function_exists('pb_sections')` first.

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

The administrator imports it with **Import the theme's content** in **Settings → Themes**. Pages with the same address get the new content (their previous version stays in their history), new pages are created, menus are replaced, and settings the content doesn't mention stay as they are. To turn a page into another one (the standard Services page into Features, say), give the new page `'replaces' => 'servicos'`: it takes over that page and its translations, at the new address. If your main pages are written in one language, say so with `'locale' => 'en'`: PageBrick then refuses the content on a site whose main language is another, instead of mixing them.

## Under construction and maintenance

While the site is **Under construction** or in **Maintenance** (dashboard → Site status), visitors get a 503 answer and a short notice; people logged in see the site normally. Draw that notice yourself in `templates/closed.php`: a complete HTML document that receives `$mode`, `$title`, `$message`, `$site` and `$siteName`. If it breaks, the core's own notice is shown.

## Translations

Wrap your theme's own texts in `__()` and add `lang/en.php` and `lang/es.php` returning `['Portuguese or source text' => 'translation']`. The site's main language is chosen at installation (Portuguese, English or Spanish).

## Sites in more than one language

A site can be offered in extra languages (Settings, the gear in the side menu → Address and languages). The main language lives at the root (`/about`); the others under a prefix: `/pt-br/sobre`, `/es-es/nosotros`, `/en-us/about`. Each page gets its translations in **Pages**, menus lead to the translated pages, and the texts of Appearance & contact can be translated too. Your templates don't change: `$page`, `$site`, menus and `__()` already speak the visitor's language, and `pb_head()` adds the `hreflang` tags search engines need.

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
