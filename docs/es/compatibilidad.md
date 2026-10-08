# La promesa de compatibilidad

Las agencias hacen sitios reales para clientes reales con PageBrick. Hacer clic en **Actualizar** en el panel nunca debe romper ninguno de ellos. Esta página explica qué se promete, cómo se garantiza y qué pasa en una actualización.

[English](../en/compatibility.md) · [Português](../pt-BR/compatibilidade.md) · **Español**

## Con qué pueden contar los desarrolladores de temas y plugins

Dentro de una misma versión de la API (`"api": 1` en `theme.json` / `plugin.json`):

1. **Nada de la API pública se quita ni se cambia.** La API pública es la lista de [core/api.php](../../core/api.php): funciones, las clases de campo (`PbGroup`, `PbList`, `PbValue`), constantes y hooks. Ninguna función desaparece, ningún parámetro se renombra, se reordena ni se vuelve obligatorio, ningún tipo de retorno cambia y ningún hook deja de dispararse. Se pueden agregar cosas nuevas.
2. **El contenido estándar se mantiene.** Los tipos de página, campos, ajustes y menús de [core/standard.php](../../core/standard.php) son parte de la API: ninguno se quita ni cambia de tipo.
3. **El HTML que entrega tu sitio no cambia.** Lo que el core imprime por ti (`pb_head()`, menús, imágenes, texto enriquecido, escape) se mantiene idéntico, byte por byte.
4. **Tus archivos nunca se tocan.** Una actualización reemplaza solo `core/`, `vendor/` e `index.php`. Todo lo que está en `content/` (temas, plugins, archivos subidos), `config.php` y `.htaccess` queda como está.
5. **La base de datos solo crece.** Las migraciones agregan tablas y columnas; nunca borran ni renombran lo que usaba una versión anterior.

Todo lo que no figure en `core/api.php`, aunque sea una función que empiece con `pb_`, es interno y puede cambiar. El aspecto y los textos del panel también pueden cambiar.

## Cómo se garantiza

Cada versión debe pasar estas pruebas automáticas ([tests/CompatibilityTest.php](../../tests/CompatibilityTest.php)):

| Garantía | Prueba |
|---|---|
| La API cumple su promesa | `core/api.php` está congelado en `tests/fixtures/api-v1.json`; la prueba falla si algo congelado falta o cambió |
| Los sitios hechos con 1.0 siguen funcionando | `tests/fixtures/sites/v1.0` es un sitio de agencia hecho con 1.0 (su propio tema con campos y tipos de página extra, su propio plugin con tablas, rutas, hooks y una pantalla en el panel, más un plugin oficial). Nunca se edita, y toda versión debe ejecutarlo |
| Su HTML y las respuestas de la API no cambian | `tests/fixtures/sites/v1.0-output` guarda las páginas y las respuestas de la API de contenido exactas de ese sitio; un solo carácter distinto hace fallar la prueba |
| Se rechazan las actualizaciones para las que el sitio no está listo | ver más abajo |
| Se deshacen las actualizaciones que rompen el sitio | se simulan un plugin roto, un tema roto, un error fatal durante la revisión y una versión que ni siquiera arranca |

## Qué pasa cuando alguien hace clic en Actualizar

1. **Antes:** el panel compara la versión nueva con el sitio. Si el PHP del servidor es demasiado antiguo, o si un plugin activo o el tema se hicieron para una versión de la API que la nueva versión no admite, la actualización se rechaza y el panel dice cuál es y qué hacer.
2. **El paquete:** se descarga del catálogo oficial y se verifica con la firma Ed25519 del proyecto, así que un archivo alterado o sustituido se rechaza.
3. **Copia de seguridad:** los `core/`, `vendor/` e `index.php` actuales se comprimen en `content/backups/` (no accesible desde la web).
4. **Cambio:** cada carpeta se reemplaza en un solo paso, así los visitantes nunca ven una actualización a medias.
5. **Revisión:** la primera solicitud en la versión nueva genera, por detrás, todas las páginas publicadas, la página 404 y el sitemap. Si algo falla, o si se desactivó un plugin, vuelve la versión anterior con los plugins y el tema exactamente como estaban, y el panel explica qué se rompió.
6. **Red de seguridad:** si la versión nueva ni siquiera arranca (un error fatal de PHP en el core), `index.php` restaura la copia de seguridad por sí solo.
7. **Después:** **Volver a la versión x.y** sigue disponible en **Sistema → Actualizaciones**.

## Si algún día llega una versión 2 de la API

Solo existiría para un cambio que no se pueda hacer agregando. Una versión que admita las dos declara `"api": [1, 2]` en el catálogo y los sitios se actualizan con normalidad. Una versión que deje de admitir la 1 será rechazada por todo sitio que todavía tenga un tema o un plugin activo de la versión 1, con un mensaje que indica cuál actualizar primero.

## Para quienes contribuyen al core

- Para agregar algo a la API pública: agrégalo a `core/api.php` y luego ejecuta `php tools/pagebrick.php api-snapshot`. El comando se niega a congelar una versión que rompa la promesa actual.
- Nunca edites `tests/fixtures/sites/v1.0` ni `v1.0-output`. Si un cambio los hace fallar, lo que hay que corregir es el cambio. Para cubrir funciones de una versión posterior, agrega un nuevo sitio congelado (`sites/v1.1/`) al lado.
- Las migraciones solo agregan. Nunca hagas `DROP` ni `RENAME` de algo que usaba una versión anterior.
- Si de verdad hace falta cambiar el HTML que imprime el core (una corrección de seguridad, por ejemplo), es un cambio visible para todos los sitios: anótalo en [CHANGELOG.md](../../CHANGELOG.md) en la sección "HTML changes" (cambios en el HTML) y luego regenera la instantánea borrando el archivo afectado en `v1.0-output` y ejecutando las pruebas.
