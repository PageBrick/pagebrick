# Building a plugin

A plugin adds something the core doesn't do: a form, a blog, a booking widget, an integration. Plugins are deliberately small: they hook into the site through a short, frozen list of functions, and a plugin that breaks is switched off on its own while the site keeps working.

**English** · [Português](../pt-BR/plugins.md) · [Español](../es/plugins.md)

Before writing a plugin, check whether the theme can do it: a new page type or field is a theme job ([themes.md](themes.md)). Plugins are for behavior: storing data, answering addresses, sending e-mails, adding screens to the panel.

## Files

```
content/plugins/my-plugin/
├── plugin.json         name, version, API version
├── plugin.php          runs on every request while the plugin is on, and registers what it adds
├── templates/          optional: what it shows on the site (a theme can replace these)
├── style.css           optional
└── lang/en.php, es.php optional: translations
```

```json
{
    "name": "My plugin",
    "version": "1.0.0",
    "description": "One sentence shown on the Plugins screen.",
    "author": "Your agency",
    "api": 1,
    "i18n": {"pt-BR": {"name": "Meu plugin", "description": "…"}, "es": {"name": "Mi plugin", "description": "…"}}
}
```

The folder name is the plugin's identifier (`my-plugin`: lowercase letters, numbers and hyphens). `"api": 1` is the PageBrick API it was written for; a plugin for another API version is refused instead of breaking the site. `i18n` is optional: the name and description in other languages.

## What a plugin can add

Everything is registered in `plugin.php`:

```php
<?php
// Tables of its own, versioned. Migrations only ever add: never drop or rename what an older version used.
pb_plugin_migrations([
    1 => ['CREATE TABLE ' . pb_table('myplugin_leads') . ' (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'],
]);

// A settings screen (Plugins → Settings), built from the same fields as themes.
pb_plugin_settings([
    'notify' => ['type' => 'email', 'label' => __('Send new leads to')],
]);

// HTML where the theme calls pb_slot('home') or pb_slot('contact').
pb_add_filter('slot:contact', fn(string $html) => $html . pb_include(__DIR__ . '/templates/form.php', []));

// Tags in <head> and before </body>.
pb_add_filter('head_html', fn(string $html) => $html . '<link rel="stylesheet" href="' . e(pb_plugin_url('my-plugin', 'style.css')) . "\">\n");

// Public addresses. '/offers/*' answers everything under /offers/ and passes the rest of the path.
pb_add_route('GET', '/offers', 'myplugin_offers_page');
pb_add_route('POST', '/offers/lead', 'myplugin_save_lead');

// Addresses offered in menus and buttons, and in sitemap.xml.
pb_add_filter('link_targets', fn(array $targets) => $targets + ['/offers' => __('Offers')]);
pb_add_filter('sitemap_urls', fn(array $urls) => [...$urls, ['/offers', '2026-10-01 00:00:00']]);

// A screen in the panel at /admin/p/leads ('editor' or 'admin' can open it).
pb_add_admin_page('leads', __('Leads'), 'myplugin_admin', 'admin');

// Code that runs at the start of every request.
pb_add_action('init', fn() => null);
```

| Function | What it does |
|---|---|
| `pb_add_action($hook, $callback, $priority = 10)` / `pb_do_action($hook, ...$args)` | run code at a moment; plugins can fire their own actions |
| `pb_add_filter($hook, $callback, $priority = 10)` / `pb_apply_filters($hook, $value, ...$args)` | change a value; a filter must return the same type it received |
| `pb_add_route($method, $path, $handler)` | a public address; the handler echoes the answer |
| `pb_render_in_theme($file, $vars, ['title' => …, 'description' => …, 'path' => …])` | show a template of the plugin as a page of the site, inside the theme's layout, with SEO tags |
| `pb_add_admin_page($slug, $label, $handler, $role = 'editor', $placement = 'menu')` | a panel screen; the handler echoes it for GET and POST. `'settings'` as $placement puts it under the gear icon with the technical screens (API keys, connections), for administrators only |
| `pb_plugin_settings($fields)` / `pb_plugin_settings_values($slug)` | settings screen and its values (read like theme fields: `->notify->raw()`) |
| `pb_plugin_migrations([1 => [sql, …], 2 => …])` | the plugin's tables; name them with `pb_table('myplugin_…')` |
| `pb_plugin_url($slug, $path)` | address of a file of the plugin (or of the theme's copy of it) |
| `pb_db()`, `pb_table($name)`, `pb_option()`, `pb_set_option()` | the database (PDO; always use prepared statements) and small saved values |
| `pb_mail($to, $subject, $body, $replyTo = '')` | send an e-mail with the site's settings |
| `pb_json($data)`, `pb_post($name)`, `pb_query($name)` | answer JSON; read form and query values as strings |
| `pb_http($url, $headers = [], $timeout = 8, $body = null)` | a request to another service (https only): returns `['status' => 200, 'body' => '…']` whatever the status, throws when it can't connect; giving $body makes it a POST. Tests can answer instead with `$GLOBALS['pb_config']['http']` |
| `pb_sign($data)`, `pb_signature_valid($data, $signature)` | protect public forms without cookies (see below) |
| `pb_csrf_field()` | hidden field for forms in your panel screens (the core checks it on every panel POST) |

Hooks fired by the core: `init`, `head_html`, `footer_html`, `sitemap_urls`, `link_targets`, `slot:home`, `slot:contact`. The complete list of what plugins may rely on is [core/api.php](../../core/api.php). Anything else, even if it starts with `pb_`, is internal and may change.

## Rules that keep sites safe

- **Escape everything you print** with `e()`. Values read through fields (`pb_plugin_settings_values()`) are already safe when printed with `<?= ?>`.
- **Panel screens** get CSRF protection from the core; put `<?= pb_csrf_field() ?>` in every form.
- **Public forms** don't use sessions (visitors get no cookies). Sign a timestamp when you show the form and check it when it comes back:

  ```php
  // templates/form.php
  <?php $t = (string) time(); ?>
  <input type="hidden" name="t" value="<?= e($t) ?>">
  <input type="hidden" name="s" value="<?= e(pb_sign("my-plugin|$t")) ?>">

  // the POST route
  if (!pb_signature_valid('my-plugin|' . pb_post('t'), pb_post('s'))) { http_response_code(400); return; }
  ```
- **Templates the visitor sees** go through `pb_include()` or `pb_render_in_theme()`, so themes can replace them ([themes.md](themes.md#replacing-a-plugins-templates)). Keep CSS in a `style.css` loaded with `pb_plugin_url()`, so themes can replace it too.
- **Prefix** your functions, tables and options with your plugin's name.

## Circuit breakers

Every piece of plugin code runs protected. If it throws, returns the wrong type from a filter, or kills PHP with a fatal error, only that plugin is switched off, the error is recorded and the panel shows it. A plugin is also tested when it is activated. Within an hour of an update, a plugin that breaks gets its previous version back instead of being switched off.

If the panel itself becomes unreachable, the **rescue link** shown on the Plugins screen (or `'safe_mode' => true` in `config.php`) opens the site with every plugin off.

This protects against broken plugins, not malicious ones: install code only from sources you trust.

## Testing and shipping

1. Put the folder in `content/plugins/` and activate it in **Settings → Plugins**.
2. Zip the folder (`my-plugin.zip` containing `my-plugin/`) to upload it on another site in **Settings → Plugins → Upload plugin**.
3. To offer it in the official catalog, see [publishing.md](publishing.md).

The official plugins in `content/plugins/` (contact form and blog) are complete examples.
