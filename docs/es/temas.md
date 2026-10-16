# Crear un tema

Un tema es todo el front-end de un sitio PageBrick: cada byte de HTML, CSS y JavaScript que recibe el visitante. PageBrick te entrega el contenido, ya completado por tu cliente en un panel sencillo, y no se mete en tu camino.

[English](../en/themes.md) · [Português](../pt-BR/temas.md) · **Español**

## Tu libertad, y con qué puedes contar

- **El core no agrega nada al sitio que no hayas pedido.** Nada de CSS, JavaScript, cookies ni Content-Security-Policy. Las etiquetas de SEO aparecen solo donde llamas a `pb_head()`, los scripts de los plugins solo donde llamas a `pb_footer()` y el HTML de los plugins solo donde llamas a `pb_slot()`. La única excepción es para quien ha iniciado sesión en el panel, nunca para los visitantes: donde llamas a `pb_footer()` esas personas reciben una barra pequeña al pie de la página (el camino de vuelta al panel, acciones rápidas y la vista en vivo). Se dibuja dentro de un shadow root, lejos de tu CSS, y se puede apagar: `pb_add_filter('admin_bar', fn() => false);` en tu `layout.php`, antes de `pb_footer()`.
- **Cualquier HTML, CSS y JavaScript.** Usa Tailwind, Sass, Vite, Alpine, islas de React, CSS puro: todo lo que construyas va en la carpeta de tu tema. Cualquier archivo estático de tu tema se sirve tal cual (los archivos PHP nunca).
- **Los plugins no imponen su HTML.** Tu tema puede reemplazar cualquier plantilla u hoja de estilos que un plugin muestre en el sitio (consulta [Reemplazar las plantillas de un plugin](#reemplazar-las-plantillas-de-un-plugin)).
- **Los encabezados también son tuyos.** El core envía `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN` y `Referrer-Policy`; llama a `header()` en tu layout para cambiarlos o agregar los tuyos.
- **O prescinde de PHP.** Haz el front-end con Next.js, Astro o una app y lee el contenido en JSON desde la [API de contenido](headless.md).
- **Las actualizaciones no rompen tu sitio.** Todo lo que un tema puede usar es una API congelada y versionada ([compatibilidad.md](compatibilidad.md)). En cada versión, una prueba automática genera un sitio hecho con PageBrick 1.0 y falla si cambió un solo carácter de su HTML (o de las respuestas de la API de contenido). Si aun así una actualización rompe algo en un sitio real, se deshace automáticamente en la primera visita.
- **Un tema roto no tumba el sitio.** Antes de activar un tema, se prueba con todas las páginas; si falla más adelante, esa página se muestra con el tema predeterminado y el panel le explica al administrador qué pasó.

## Archivos

```
content/themes/my-theme/
├── theme.json          nombre, versión, versión de la API
├── theme.php           los campos extra, tipos de página, ajustes y menús que agrega tu tema
├── layout.php          el HTML que rodea cada página
├── templates/
│   ├── home.php        obligatorio
│   ├── page.php        obligatorio
│   ├── services.php    obligatorio
│   ├── contact.php     obligatorio
│   ├── 404.php         opcional
│   ├── closed.php      opcional: la página completa que se muestra mientras el sitio está en construcción o en mantenimiento
│   └── landing.php     cualquier tipo de página que agregues
├── plugins/            opcional: tus copias de plantillas y hojas de estilos de plugins
├── assets/             lo que quieras: CSS, JS, fuentes, imágenes, el resultado de tu build
├── lang/en.php, es.php opcional: traducciones de los textos de tu tema
├── demo.php, demo/     opcional: contenido listo (páginas, fotos, menús, configuración)
└── screenshot.webp     opcional: imagen que aparece en la pantalla de Temas (800×500)
```

La forma más rápida de empezar es copiar `content/themes/default/` con otro nombre y cambiar el nombre en `theme.json`. No edites el tema estándar en su sitio: es del propio PageBrick (el sitio vuelve a él cuando tu tema falla), así que una actualización lo renueva (la carpeta antigua queda guardada en los backups):

```json
{
    "name": "Mi tema",
    "version": "1.0.0",
    "description": "Una frase que aparece en la pantalla de Temas.",
    "author": "Tu agencia",
    "api": 1
}
```

`"api": 1` es la versión de la API de PageBrick para la que se escribió el tema. Un tema para otra versión de la API se rechaza en lugar de romper el sitio.

## Contenido: el contrato estándar

Todo sitio PageBrick tiene el mismo contenido básico, definido por el core en [core/standard.php](../../core/standard.php): cuatro tipos de página (`home`, `page`, `services`, `contact`), los ajustes de **Contacto** y **Apariencia** y dos menús (`main`, `footer`). Todo tema debe mostrar este contenido. Por eso un cliente puede cambiar de tema sin perder nada, y por eso tu tema funciona con cualquier sitio.

| Tipo de página | Campos |
|---|---|
| `home` | `hero` (title, text, button_label, button_link, image) · `about` (title, text, image) · `services` (title, intro, items[title, text, image], link_label, link) · `numbers` (title, items[value, label]) · `testimonials` (title, items[quote, name, role]) · `cta` (title, text, button_label, button_link). El cliente puede ocultar cualquier sección. |
| `page` | `intro`, `image`, `body` |
| `services` | `intro`, `items[title, text, image]`, `cta` (title, text, button_label, button_link) |
| `contact` | `intro`, `body` (el plugin de formulario de contacto agrega el formulario a través de `pb_slot('contact')`) |

| Ajustes | Campos |
|---|---|
| `identity` | `logo`, `icon`, `color`, `fonts` (`sobria`, `elegante` o `acolhedora`: sobria, elegante o acogedora), `share_image` |
| `contact` | `whatsapp`, `whatsapp_message`, `phone`, `email`, `address`, `hours` |
| `social` | `instagram`, `facebook`, `linkedin`, `youtube`, `tiktok` |
| `footer` | `text`, `credit` |

## Agregar tus propios campos y tipos de página

`theme.php` devuelve solo lo que tu tema **agrega**. El panel arma los formularios solo.

```php
<?php
return [
    'templates' => [
        // Un campo agregado a la página de inicio estándar:
        'home' => ['fields' => [
            'video' => ['type' => 'url', 'label' => 'Video de portada'],
        ]],
        // Un nuevo tipo de página (templates/landing.php):
        'landing' => ['label' => 'Página de campaña', 'fields' => [
            'offer' => ['type' => 'text', 'label' => 'Oferta'],
            'perks' => ['type' => 'list', 'label' => 'Beneficios', 'item_label' => 'Beneficio', 'add_label' => 'Agregar beneficio', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Beneficio'],
                'icon' => ['type' => 'image', 'label' => 'Ícono'],
            ]],
            'cta' => ['type' => 'group', 'label' => 'Botón', 'toggle' => true, 'fields' => [
                'label' => ['type' => 'text', 'label' => 'Texto'],
                'link' => ['type' => 'link', 'label' => 'Lleva a'],
            ]],
        ]],
    ],
    'settings' => [
        'agency' => ['type' => 'group', 'label' => 'Agencia', 'fields' => [
            'accent' => ['type' => 'color', 'label' => 'Color de acento', 'default' => '#0a7c66'],
        ]],
    ],
    'menus' => ['top' => 'Menú de la barra superior'],
];
```

Puedes agregar campos, tipos de página, ajustes y menús; no puedes quitar ni cambiar los estándar (el core conserva su versión). Lo que agregues queda guardado si el sitio cambia a otro tema, y vuelve si regresa al tuyo.

Los ajustes que agregues aparecen en **Contacto**, la pantalla que abre cualquier persona que edita el sitio. Un grupo sobre el aspecto (colores, tipografía) puede pedir **Configuración → Apariencia**, que solo abren los administradores, con `'screen' => 'appearance'`.

**Tipos de campo:** `text`, `textarea`, `richtext` (un editor de texto: títulos, negrita, cursiva, listas, citas, enlaces, tablas, divisores e imágenes de la biblioteca de medios; el HTML se limpia al guardar), `image`, `url`, `email`, `tel`, `color`, `select` (con `'options' => ['value' => 'Label']`), `link` (una página del sitio o cualquier dirección), `list` (elementos repetibles, con `fields`), `group` (con `fields`; `'toggle' => true` permite que el cliente lo oculte). Todo campo acepta `label`, `help` y `default`.

¿Necesitas funciones auxiliares? Ponlas en un archivo y agrega `require_once __DIR__ . '/functions.php';` al inicio de `theme.php`.

## Layout y plantillas

`layout.php` es el esqueleto de tu página. Recibe `$content` (el HTML de la plantilla), `$site` (los ajustes) y `$siteName`:

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

Una plantilla recibe `$page` (los campos de la página), `$title` y `$isHome`:

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

### Los valores son seguros por defecto

Imprimir un campo con `<?= ?>` siempre es seguro: el texto se escapa, el texto enriquecido se limpió al guardarse y una imagen se convierte en una etiqueta `<img>`. Tu cliente no puede romper tu layout ni inyectar scripts.

| En un campo | Te da |
|---|---|
| `<?= $page->title ?>` | HTML seguro |
| `->raw()` | el valor guardado, sin escapar: escápalo tú con `e()` |
| `->isEmpty()` | `true` cuando el cliente lo dejó en blanco |
| `->url($size = 'full')` | la dirección de un enlace, página, correo electrónico (`mailto:`), teléfono (`tel:`) o imagen (`'thumb'` para la pequeña). Escápala: `e($value->url())` |
| `->img($class = '', $size = 'full', $lazy = true, $sizes = '')` | `<img>` con `alt`, `width`, `height` y carga diferida; `lazy: false` para imágenes en la parte superior. Con `sizes:` imprime también un `srcset` (ver abajo) |
| un grupo: `->visible()` | `false` cuando el cliente ocultó esa sección |
| una lista: `foreach`, `count()`, `->isEmpty()` | sus elementos, cada uno es un grupo |

Un móvil no necesita una foto de 1920 píxeles. PageBrick guarda tres copias de cada foto (480, 960 y el ancho completo, hasta 1920), y `img(sizes: '…')` deja que el navegador tome la más pequeña que aún se vea nítida. `sizes` es el atributo del HTML: indica el ancho que ocupa la foto en la página, por ejemplo `img(sizes: '(min-width: 900px) 560px, 94vw')`. Para una foto más abajo (lazy), `sizes: 'auto'` deja que el navegador mida el lugar por sí mismo; para una en la parte superior, indica el ancho real, como `'100vw'` en un banner de lado a lado. Sin `sizes` la etiqueta es exactamente la de las versiones anteriores, así que un tema solo cambia cuando su autor quiere. Las fotos subidas antes de PageBrick 1.8.0 solo tienen la miniatura y la completa hasta que se use **Medios → Rehacer los tamaños**.

Con `'debug' => true` en `config.php`, leer un campo que no existe genera una advertencia, así los errores de tipeo aparecen mientras desarrollas.

### Texto enriquecido: da estilo a lo que tu cliente puede escribir

Un campo `richtext` puede tener párrafos, `h2`/`h3`, `strong`, `em`, `u`, `s`, `a`, listas, `blockquote`, `hr`, `table` (con `thead`/`tbody`, `tr`, `th`, `td`) e `img`. El núcleo no añade CSS: imprime el campo dentro de un contenedor tuyo y da estilo a esos elementos allí, tablas e imágenes incluidas:

```css
.prose img { max-width: 100%; height: auto; }
.prose table { display: block; overflow-x: auto; border-collapse: collapse; } /* una tabla ancha se desplaza dentro del texto */
.prose th, .prose td { padding: .5rem .75rem; border: 1px solid #ddd; }
```

Las imágenes vienen solo de la biblioteca de medios: PageBrick escribe el `<img>` (con `alt`, `width`, `height` y carga diferida), así que sigue funcionando si el sitio se mueve. Un texto escrito antes de PageBrick 1.3 (con `div` y `br`) se muestra exactamente como se guardó.

### Funciones auxiliares

| Función | Uso |
|---|---|
| `e($text)` | escapar cualquier texto que imprimas tú |
| `pb_url('/path')`, `pb_absolute_url('/path')` | direcciones que funcionan cuando el sitio está en una subcarpeta |
| `pb_theme_url('assets/app.js')` | un archivo de tu tema, con `?v=` para que los navegadores detecten los cambios |
| `pb_head($options)` | título, descripción, canonical, Open Graph y etiquetas de los plugins. Si prefieres, no lo uses y escribe las tuyas |
| `pb_footer()` | scripts de los plugins, la barra de quien ha iniciado sesión en el panel y, para un administrador que está viendo la vista previa de un tema, la barra de vista previa |
| `pb_slot('home')`, `pb_slot('contact')` | donde los plugins agregan HTML; llámalas en esas dos plantillas |
| `pb_menu_html('main', 'class')`, `pb_menu('main')` | un menú como `<ul>`, o como un array de `label`, `url`, `current` para armar tu propio HTML |
| `pb_whatsapp_url($number, $message)`, `pb_map_url($address)` | enlaces de WhatsApp y de mapa |
| `pb_text_color_on($color)`, `pb_readable_color($color)`, `pb_contrast($a, $b)` | colores que siguen siendo legibles sea cual sea el color de marca que elija el cliente |
| `pb_is_logged_in()` | mostrar avisos que solo ve el dueño del sitio ("completa tu número de teléfono") |
| `pb_locale()`, `pb_date($datetime)`, `__('text')` | idioma y fechas del sitio |

La lista completa de lo que los temas y plugins pueden usar está en [core/api.php](../../core/api.php). Todo lo demás, aunque empiece con `pb_`, es interno y puede cambiar.

## Dejar que el cliente reordene las secciones

En el editor de páginas el cliente ve la página junto al formulario, cambia lo que quiera, arrastra secciones a otro lugar (en la lista o en la propia página) y solo después hace clic en **Publicar**; hasta entonces los visitantes siguen viendo la versión publicada. Que el orden funcione depende de tu tema, en dos pasos, y un tema que no hace nada mantiene su orden fijo (nada se rompe):

1. Lista las secciones en `theme.php`, en `'sections'` de la plantilla. Cada una es el nombre de un campo `group` de esa plantilla (los estándar `hero`, `about`… de la página de inicio, o grupos tuyos), o `'plugins'`, el lugar donde los plugins añaden sus secciones con `pb_slot('home')`.
2. Imprime las secciones en el orden del cliente en la plantilla: `$page->order($nombres)` devuelve tus nombres en ese orden (los que el cliente aún no colocó van al final), y `pb_section($nombre, $html)` devuelve tu HTML exactamente como está para los visitantes, solo marcando la sección mientras el cliente edita, para poder elegirla y arrastrarla en la página.

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

Los campos que no están en la lista (una cabecera que siempre queda arriba, por ejemplo) se imprimen donde tú los imprimas. La API de contenido añade `section_order` a una página solo cuando el cliente eligió un orden. Mientras el cliente edita, la página de la vista previa tiene la clase `pb-editing` en `<html>`: úsala para apagar lo que espera un desplazamiento para mostrarse (fundidos, animaciones de entrada), porque las secciones se reemplazan allí mientras se miran.

Para un tema que también deba funcionar en un PageBrick anterior a la 1.3, comprueba antes `method_exists($page, 'order')` y `function_exists('pb_section')`.

## Dejar que el cliente añada secciones

Además de mover las secciones que hiciste, el cliente puede **añadir** otras: texto, foto con texto, tarjetas, preguntas y respuestas, testimonios, llamada a la acción, números, galería de fotos y vídeo de YouTube. En el editor de la página, un botón **Añadir sección** y un "+" entre las secciones (en la lista y en la propia página) abren una galería con un dibujo de cada tipo; el cliente también puede duplicar una sección o quitar una (con **Deshacer**). El núcleo lo guarda junto con la página y lo imprime como **HTML simple, sin CSS y sin script**, con nombres de clase estables: el aspecto es trabajo tuyo, en tu tema.

1. Actívalo en `theme.php`, en cada tipo de página que deba tenerlo: `'blocks' => true` para todos los tipos, o una lista como `'blocks' => ['text', 'cards']`. Un tema que no dice nada no recibe ninguno, y nada cambia.
2. Imprime la página con `pb_sections($page, $names, $render)`. Recorre las secciones en el orden que eligió el cliente, llama a tu `$render($name)` para cada nombre tuyo e imprime cada sección que el cliente añadió, marcándolas todas mientras el cliente edita.

```php
// theme.php
return ['templates' => [
    'page' => ['sections' => ['content'], 'blocks' => true], // 'content': lo que imprimen los campos de la propia plantilla (título, texto…)
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

Un nombre con un tipo (`'about' => 'image_text'`) dice que tu sección guarda lo mismo que ese tipo de sección: el panel pasa a ofrecer **Duplicar** en ella, y la copia es una sección que el cliente también puede quitar. Un nombre sin tipo (`'hero'`) no se puede copiar. `'content'` es el nombre para lo que imprimen los campos de la propia plantilla (el título, la introducción, el texto), para que el cliente pueda poner secciones antes de él también.

### Qué imprime cada tipo

Cada uno es un `<section class="pb-block pb-block-{tipo}">` con un `h2` cuando tiene título; lo que está vacío se omite.

| Tipo | Campos | Dentro |
|---|---|---|
| `text` | `title`, `text` | `.pb-block-body` |
| `image_text` | `title`, `text`, `image`, `side` (`left` o `right`) | `.pb-block-image` y `.pb-block-words`, en el orden en que se ven; clase `pb-side-left` o `pb-side-right` |
| `cards` | `title`, `intro`, `items` (`title`, `text`, `image`, `link`), `link_label`, `link` | `ul.pb-cards > li.pb-card` |
| `faq` | `title`, `items` (`question`, `answer`) | `details.pb-faq-item`: el del propio navegador, sin script |
| `testimonials` | `title`, `items` (`quote`, `name`, `role`) | `figure.pb-quote` |
| `cta` | `title`, `text`, `button_label`, `button_link` | `a.pb-button` |
| `numbers` | `title`, `items` (`value`, `label`) | `ul.pb-numbers > li.pb-number` |
| `gallery` | `title`, `items` (`image`, `caption`) | `ul.pb-gallery`, con un `figure` en cada uno |
| `video` | `title`, `url`, `caption` | `figure.pb-video > iframe` de youtube-nocookie.com; de una dirección de YouTube solo se usa el código del vídeo (`pb_youtube_id()`) |

### Dibujar uno a tu manera

Pon un archivo con el nombre del tipo en `templates/blocks/` de tu tema (`templates/blocks/cards.php`). Se ejecuta con `$block` (los campos, que se leen como `$page`: `$block->title`, `$block->items`), `$type`, `$id` y `$page`. Un tipo sin archivo tuyo lo dibuja el núcleo; copia uno de `core/blocks/` para empezar.

Las secciones viven en los datos de la página, así que un tema que no las activa las oculta, y volver al tema las trae de vuelta. La API de contenido lista las que están en el sitio en `blocks` (cada una `{id, type, fields}`), y `section_order` las nombra `block-{id}`. Para un tema que también deba funcionar en un PageBrick anterior a la 1.3, comprueba antes `function_exists('pb_sections')`.

## Reemplazar las plantillas de un plugin

Los plugins muestran sus páginas públicas dentro de tu layout. Cuando su HTML no es lo que quieres, copia el archivo a tu tema, en `plugins/{plugin}/` con la misma ruta, y modifícalo:

```
content/plugins/blog/templates/list.php        →  content/themes/my-theme/plugins/blog/templates/list.php
content/plugins/contact-form/form.php          →  content/themes/my-theme/plugins/contact-form/form.php
content/plugins/contact-form/style.css         →  content/themes/my-theme/plugins/contact-form/style.css
```

- Los archivos que no copies se siguen tomando del plugin, incluso cuando tu copia los incluye (`pb_include(__DIR__ . '/cards.php', …)` sigue encontrando el `cards.php` del plugin).
- Una copia vacía de `style.css` quita los estilos del plugin, para que puedas darle estilo a su HTML en tu propio CSS.
- El panel nunca usa tus copias: las pantallas del plugin quedan como el plugin las hizo.
- Si tu copia se rompe, la culpa es del tema, no del plugin: la página vuelve al tema predeterminado y el plugin sigue funcionando.
- Cuando un plugin cambia una plantilla en una versión nueva, tu copia sigue funcionando tal como está. Compárala con la versión nueva cuando actualices tu tema.

## Contenido listo

Un tema puede traer su propio contenido, para que un sitio nuevo se vea terminado apenas se activa el tema. Crea un `demo.php` que devuelva páginas, fotos (archivos en `demo/`), menús y configuración, con el mismo formato del sitio de ejemplo del core ([core/standard.php](../../core/standard.php), `pb_standard_demo()`). Dentro de él, `page:{slug}` y `media:{clave}` apuntan a sus propias páginas y fotos.

El administrador lo importa con **Importar el contenido del tema** en **Configuración → Temas**. Las páginas con la misma dirección reciben el contenido nuevo (la versión anterior queda en el historial), se crean las páginas nuevas, se reemplazan los menús y la configuración que el contenido no menciona queda como está. Para convertir una página en otra (la página estándar Servicios en Funciones, por ejemplo), dale a la página nueva `'replaces' => 'servicos'`: toma esa página y sus traducciones, en la dirección nueva. Si las páginas principales están escritas en un idioma, indícalo con `'locale' => 'en'`: PageBrick rechaza el contenido en un sitio con otro idioma principal, en lugar de mezclarlos.

## En construcción y en mantenimiento

Mientras el sitio está **En construcción** o **En mantenimiento** (Panel → Estado del sitio), los visitantes reciben una respuesta 503 y un aviso corto; quien inició sesión ve el sitio normalmente. Dibuja ese aviso en `templates/closed.php`: un documento HTML completo que recibe `$mode`, `$title`, `$message`, `$site` y `$siteName`. Si falla, se muestra el aviso del propio core.

## Sitios en más de un idioma

Un sitio puede ofrecerse en idiomas extra (Configuración, el engranaje del menú lateral → Dirección e idiomas). El idioma principal vive en la raíz (`/nosotros`); los demás, con prefijo: `/en-us/about`, `/pt-br/sobre`, `/es-es/nosotros`. Cada página recibe sus traducciones en **Páginas**, los menús llevan a las páginas traducidas y los textos de Apariencia y contacto también se pueden traducir. Tus plantillas no cambian: `$page`, `$site`, los menús y `__()` ya hablan el idioma del visitante, y `pb_head()` agrega las etiquetas `hreflang` que necesitan los buscadores.

Para un selector de idioma, usa `pb_language_links()`. Devuelve los idiomas del sitio con `locale`, `name`, `url` y `current`, cada uno llevando a esta página en ese idioma (o a su página de inicio), y una lista vacía en un sitio de un solo idioma:

```php
<?php if (count($languages = pb_language_links()) > 1): ?>
    <select onchange="location.href = this.value" aria-label="<?= e(__('Idioma')) ?>">
        <?php foreach ($languages as $language): ?>
            <option value="<?= e($language['url']) ?>"<?= $language['current'] ? ' selected' : '' ?>><?= e($language['name']) ?></option>
        <?php endforeach ?>
    </select>
<?php endif ?>
```

`pb_content_locale()` indica en qué idioma lee el visitante, y `pb_page_translation($page, $locale)` encuentra una página en otro idioma.

## Traducciones

Envuelve los textos propios de tu tema en `__()` y agrega `lang/en.php` y `lang/es.php`, que devuelven `['texto en portugués u original' => 'traducción']`. El idioma del sitio se elige en la instalación (portugués, inglés o español) y el visitante ve el sitio en ese idioma.

## Probar y entregar

1. Actívalo en **Configuración → Temas**. PageBrick primero genera todas las páginas del sitio con tu tema y lo rechaza, indicando el motivo, si algo falla. **Vista previa** muestra el sitio con tu tema solo a ti.
2. Comprime la carpeta del tema (`my-theme.zip` con `my-theme/` adentro) y súbela en otro sitio en **Configuración → Temas → Subir tema (.zip)**.
3. Para ofrecerlo en el catálogo oficial, consulta [publicacion.md](publicacion.md).
