# Crear un plugin

Un plugin agrega algo que el core no hace: un formulario, un blog, un widget de reservas, una integración. Los plugins son pequeños a propósito: se conectan al sitio a través de una lista corta y congelada de funciones, y un plugin que se rompe se desactiva solo mientras el sitio sigue funcionando.

[English](../en/plugins.md) · [Português](../pt-BR/plugins.md) · **Español**

Antes de escribir un plugin, revisa si el tema puede hacerlo: un nuevo tipo de página o campo es trabajo del tema ([temas.md](temas.md)). Los plugins son para comportamiento: guardar datos, responder direcciones, enviar correos, agregar pantallas al panel.

## Archivos

```
content/plugins/my-plugin/
├── plugin.json         nombre, versión, versión de la API
├── plugin.php          se ejecuta en cada solicitud mientras el plugin está activo y registra lo que agrega
├── templates/          opcional: lo que muestra en el sitio (un tema puede reemplazarlo)
├── style.css           opcional
└── lang/en.php, es.php opcional: traducciones
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

El nombre de la carpeta es el identificador del plugin (`my-plugin`: letras minúsculas, números y guiones). `"api": 1` es la API de PageBrick para la que se escribió; un plugin para otra versión de la API se rechaza en lugar de romper el sitio. `i18n` es opcional: el nombre y la descripción en otros idiomas.

## Qué puede agregar un plugin

Todo se registra en `plugin.php`:

```php
<?php
// Tablas propias, versionadas. Las migraciones solo agregan: nunca borres ni renombres lo que usaba una versión anterior.
pb_plugin_migrations([
    1 => ['CREATE TABLE ' . pb_table('myplugin_leads') . ' (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'],
]);

// Una pantalla de ajustes (Plugins → Configurar), hecha con los mismos campos que los temas.
pb_plugin_settings([
    'notify' => ['type' => 'email', 'label' => __('Send new leads to')],
]);

// HTML donde el tema llama a pb_slot('home') o pb_slot('contact').
pb_add_filter('slot:contact', fn(string $html) => $html . pb_include(__DIR__ . '/templates/form.php', []));

// Etiquetas en <head> y antes de </body>.
pb_add_filter('head_html', fn(string $html) => $html . '<link rel="stylesheet" href="' . e(pb_plugin_url('my-plugin', 'style.css')) . "\">\n");

// Direcciones públicas. '/offers/*' responde a todo lo que está bajo /offers/ y pasa el resto de la ruta.
pb_add_route('GET', '/offers', 'myplugin_offers_page');
pb_add_route('POST', '/offers/lead', 'myplugin_save_lead');

// Direcciones que se ofrecen en menús y botones, y en sitemap.xml.
pb_add_filter('link_targets', fn(array $targets) => $targets + ['/offers' => __('Offers')]);
pb_add_filter('sitemap_urls', fn(array $urls) => [...$urls, ['/offers', '2026-10-01 00:00:00']]);

// Una pantalla en el panel en /admin/p/leads (la puede abrir 'editor' o 'admin').
pb_add_admin_page('leads', __('Leads'), 'myplugin_admin', 'admin');

// Código que se ejecuta al inicio de cada solicitud.
pb_add_action('init', fn() => null);
```

| Función | Qué hace |
|---|---|
| `pb_add_action($hook, $callback, $priority = 10)` / `pb_do_action($hook, ...$args)` | ejecutar código en un momento dado; los plugins pueden disparar sus propias acciones |
| `pb_add_filter($hook, $callback, $priority = 10)` / `pb_apply_filters($hook, $value, ...$args)` | cambiar un valor; un filtro debe devolver el mismo tipo que recibió |
| `pb_add_route($method, $path, $handler)` | una dirección pública; el handler imprime la respuesta con echo |
| `pb_render_in_theme($file, $vars, ['title' => …, 'description' => …, 'path' => …])` | mostrar una plantilla del plugin como una página del sitio, dentro del layout del tema, con etiquetas de SEO |
| `pb_add_admin_page($slug, $label, $handler, $role = 'editor')` | una pantalla del panel; el handler la imprime con echo para GET y POST |
| `pb_plugin_settings($fields)` / `pb_plugin_settings_values($slug)` | pantalla de ajustes y sus valores (se leen como los campos de un tema: `->notify->raw()`) |
| `pb_plugin_migrations([1 => [sql, …], 2 => …])` | las tablas del plugin; nómbralas con `pb_table('myplugin_…')` |
| `pb_plugin_url($slug, $path)` | dirección de un archivo del plugin (o de la copia que tiene el tema) |
| `pb_db()`, `pb_table($name)`, `pb_option()`, `pb_set_option()` | la base de datos (PDO; usa siempre consultas preparadas) y pequeños valores guardados |
| `pb_mail($to, $subject, $body, $replyTo = '')` | enviar un correo con la configuración del sitio |
| `pb_json($data)`, `pb_post($name)`, `pb_query($name)` | responder JSON; leer valores de formularios y de la query string como cadenas |
| `pb_sign($data)`, `pb_signature_valid($data, $signature)` | proteger formularios públicos sin cookies (ver más abajo) |
| `pb_csrf_field()` | campo oculto para los formularios de tus pantallas del panel (el core lo verifica en cada POST del panel) |

Hooks (ganchos) que dispara el core: `init`, `head_html`, `footer_html`, `sitemap_urls`, `link_targets`, `slot:home`, `slot:contact`. La lista completa de lo que los plugins pueden usar está en [core/api.php](../../core/api.php). Todo lo demás, aunque empiece con `pb_`, es interno y puede cambiar.

## Reglas que mantienen los sitios seguros

- **Escapa todo lo que imprimas** con `e()`. Los valores leídos a través de campos (`pb_plugin_settings_values()`) ya son seguros cuando se imprimen con `<?= ?>`.
- **Las pantallas del panel** reciben protección CSRF del core; pon `<?= pb_csrf_field() ?>` en cada formulario.
- **Los formularios públicos** no usan sesiones (los visitantes no reciben cookies). Firma una marca de tiempo al mostrar el formulario y verifícala cuando vuelve:

  ```php
  // templates/form.php
  <?php $t = (string) time(); ?>
  <input type="hidden" name="t" value="<?= e($t) ?>">
  <input type="hidden" name="s" value="<?= e(pb_sign("my-plugin|$t")) ?>">

  // la ruta POST
  if (!pb_signature_valid('my-plugin|' . pb_post('t'), pb_post('s'))) { http_response_code(400); return; }
  ```
- **Las plantillas que ve el visitante** pasan por `pb_include()` o `pb_render_in_theme()`, para que los temas puedan reemplazarlas ([temas.md](temas.md#reemplazar-las-plantillas-de-un-plugin)). Deja el CSS en un `style.css` cargado con `pb_plugin_url()`, para que los temas también puedan reemplazarlo.
- **Usa un prefijo** con el nombre de tu plugin en tus funciones, tablas y opciones.

## Cortacircuitos (protección automática)

Todo el código de un plugin se ejecuta protegido. Si lanza una excepción, si un filtro devuelve un tipo incorrecto o si PHP se cae con un error fatal, solo se desactiva ese plugin, el error queda registrado y el panel lo muestra. Un plugin también se prueba al activarlo. Si un plugin se rompe dentro de la hora siguiente a una actualización, vuelve a su versión anterior en lugar de desactivarse.

Si el panel mismo deja de abrirse, el **Enlace de rescate** que aparece en la pantalla de Plugins (o `'safe_mode' => true` en `config.php`) abre el sitio con todos los plugins desactivados.

Esto protege contra plugins rotos, no contra plugins maliciosos: instala código solo de fuentes en las que confíes.

## Probar y entregar

1. Pon la carpeta en `content/plugins/` y actívalo en **Configuración → Plugins**.
2. Comprime la carpeta (`my-plugin.zip` con `my-plugin/` adentro) para subirla en otro sitio en **Configuración → Plugins → Subir plugin (.zip)**.
3. Para ofrecerlo en el catálogo oficial, consulta [publicacion.md](publicacion.md).

Los plugins oficiales de `content/plugins/` (formulario de contacto y blog) son ejemplos completos.
