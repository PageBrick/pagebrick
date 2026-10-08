# API de contenido (headless)

¿Prefieres hacer el front-end con Next.js, Astro, Nuxt, SvelteKit o una app móvil? PageBrick también entrega el contenido del sitio en JSON. Tu cliente sigue editando en el mismo panel sencillo; tu front-end lee el contenido y hace el resto.

[English](../en/headless.md) · [Português](../pt-BR/headless.md) · **Español**

## Endpoints

Todos son públicos, de solo lectura y `GET`, bajo `/api/v1/` del sitio PageBrick:

| Dirección | Te da |
|---|---|
| `/api/v1/site` | nombre del sitio, idioma, dirección, los ajustes de **Apariencia y contacto** y todos los menús |
| `/api/v1/pages` | las páginas publicadas, sin contenido: `id`, `title`, `slug`, `path`, `template`, `home`, `updated_at` |
| `/api/v1/pages/{slug}` | una página publicada: lo mismo, más `seo` (`title`, `description`) y `fields` |
| `/api/v1/blog?page=2&category={slug}` | con el plugin Blog activado: entradas (de la más reciente a la más antigua, sin el texto), número de páginas, total y todas las categorías |
| `/api/v1/blog/{slug}` | con el plugin Blog activado: una entrada con su texto |

Cualquier otra ruta bajo `/api/` responde `404` con `{"error": "not_found"}`.

```bash
curl https://example.com/api/v1/pages/nosotros
```

```json
{
    "id": 2,
    "title": "Nosotros",
    "slug": "nosotros",
    "path": "/nosotros",
    "template": "page",
    "home": false,
    "updated_at": "2026-10-07T22:48:00+00:00",
    "seo": {"title": "Nosotros · Panadería Acme", "description": ""},
    "fields": {
        "intro": "Cuenta cómo empezó el negocio…",
        "image": {"url": "https://example.com/content/uploads/2026/10/a408f3b279821d30.webp", "thumb": "https://example.com/content/uploads/2026/10/a408f3b279821d30-thumb.webp", "alt": "Nuestro equipo", "width": 1600, "height": 1067},
        "body": "<h2>Nuestra historia</h2><p>…</p>"
    }
}
```

## Valores de los campos

Los campos son los mismos que muestra el panel: el [contenido estándar](temas.md#contenido-el-contrato-estándar) más lo que agregue el tema activo.

| Tipo de campo | En JSON |
|---|---|
| `text`, `textarea`, `email`, `tel`, `url`, `color`, `select` | el texto tal como se escribió (un `select` da la clave de la opción) |
| `richtext` | HTML, ya limpio desde que se guardó |
| `image` | `{url, thumb, alt, width, height}` con direcciones completas, o `null` |
| `link` | una página del sitio como ruta (`"/nosotros"`, `"/"` para la página de inicio); cualquier otro enlace tal como se escribió (`"https://…"`) |
| `list` | un array de objetos |
| `group` | un objeto, o `null` cuando el cliente ocultó esa sección |

Los campos de contraseña nunca se incluyen.

## Cómo usarla

- **Rutas:** pide `/api/v1/pages` para armar tus rutas (cada página tiene su `path`) y luego `/api/v1/pages/{slug}` para cada una. La página con `"home": true` va en `/`.
- **Enlaces:** un enlace que empieza con `/` es una página del sitio: pásaselo a tu router. Cualquier otro es una dirección externa.
- **Tipos de página:** `template` te dice qué layout usar (`home`, `page`, `services`, `contact` o un tipo que agregue tu tema).
- **Tus propios campos:** agrégalos en un tema, como se explica en [temas.md](temas.md#agregar-tus-propios-campos-y-tipos-de-página). El tema solo necesita `theme.json`, `theme.php`, un `layout.php` y las cuatro plantillas obligatorias (pueden ser mínimas o redirigir a tu front-end); el panel arma los formularios a partir de él y la API los entrega.
- **Caché:** las respuestas pueden quedar en caché durante 60 segundos. Para builds estáticos, vuelve a generar el sitio cuando el cliente publique (por ejemplo, con un build programado).
- **Otros dominios:** la API envía `Access-Control-Allow-Origin: *`, así que los navegadores de cualquier dominio pueden leerla. Nunca usa cookies.
- **Borradores:** no están en la API. Solo aparecen las páginas publicadas.

## Con qué puedes contar

La forma de estas respuestas es parte de la [promesa de compatibilidad](compatibilidad.md): dentro de `/api/v1/`, no se quita ni se renombra nada. Una prueba automática congela las respuestas de la API de un sitio hecho con PageBrick 0.1 y falla si alguna cambia. Pueden aparecer campos nuevos; tu código debe ignorar las claves que no conozca.

## Endpoints para plugins

Un plugin puede agregar sus propios endpoints bajo `/api/v1/{plugin}`:

```php
pb_add_route('GET', '/api/v1/offers', function () {
    pb_content_send(['offers' => myplugin_offers()]);     // JSON, encabezados de CORS y de caché; null responde 404
});
pb_add_route('GET', '/api/v1/offers/*', function (string $slug) {
    $offer = myplugin_find($slug);
    pb_content_send($offer ? ['title' => $offer['title'], 'fields' => pb_content_json(myplugin_fields(), $offer['data'])] : null);
});
```

`pb_content_json($fields, $data)` convierte los valores guardados con definiciones de campos en el JSON descrito arriba. Consulta [core/headless.php](../../core/headless.php) y el plugin Blog para ver un ejemplo completo.
