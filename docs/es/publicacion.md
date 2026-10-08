# Publicación: catálogo, firmas y versiones

Cómo los plugins, los temas y el propio PageBrick llegan a los sitios a través del catálogo oficial. Sobre todo para mantenedores.

[English](../en/publishing.md) · [Português](../pt-BR/publicacao.md) · **Español**

## Cómo funciona la confianza

- El catálogo es un archivo JSON público, por defecto `https://raw.githubusercontent.com/pagebrick/catalog/main/catalog.json`.
- Cada paquete del catálogo lleva una **firma Ed25519** que cubre su tipo, nombre, versión y el SHA-256 del archivo. Un sitio lo instala solo si la firma coincide con una clave en la que confía (`PB_TRUSTED_KEYS` en [core/packages.php](../../core/packages.php)), así que un archivo cambiado o una "versión más nueva" falsa se rechazan aunque alguien altere el propio catálogo.
- La **clave privada nunca va en el repositorio** ni en ninguna carpeta sincronizada. Quien la tenga puede publicar código que todos los sitios PageBrick van a instalar. Guárdala en la máquina del mantenedor, con una copia de seguridad sin conexión.
- Un `.zip` que un administrador sube en el panel no necesita firma: es su propio código, como subirlo por FTP.

## El formato del catálogo

```json
{
    "format": 1,
    "core": {
        "version": "1.0.1", "api": [1], "requires_php": "8.2",
        "url": "https://github.com/pagebrick/pagebrick/releases/download/v1.0.1/pagebrick-1.0.1.zip",
        "sha256": "…", "signature": "…"
    },
    "plugins": [
        {
            "slug": "blog", "name": "Blog", "description": "…", "author": "PageBrick",
            "version": "1.0.0", "api": 1,
            "url": "https://…/blog-1.0.0.zip", "sha256": "…", "signature": "…",
            "price": "", "homepage": ""
        }
    ],
    "themes": []
}
```

`price` y `homepage` son para paquetes de pago: en lugar de instalarlo, el panel muestra el precio y un enlace al sitio del autor; después, el comprador sube el .zip que le entrega el autor. Los sitios guardan el catálogo en caché durante 12 horas.

Un sitio puede usar otro catálogo, o confiar en otra clave, en `config.php`:

```php
'catalog_url' => 'https://example.com/catalog.json',
'trusted_keys' => ['clave pública en base64'],
```

## Comandos

Todos están en [tools/pagebrick.php](../../tools/pagebrick.php) y se ejecutan desde el repositorio (necesitan PHP con `sodium` y `zip`):

```bash
# Una sola vez: crea el par de claves. Imprime la clave pública para PB_TRUSTED_KEYS.
php tools/pagebrick.php keygen ~/.pagebrick/catalog.key

# Un plugin o tema: comprime la carpeta, hace las mismas verificaciones que hará un sitio, lo firma e imprime su entrada del catálogo.
php tools/pagebrick.php package plugin content/plugins/blog ~/.pagebrick/catalog.key https://example.com/packages

# Una versión de PageBrick: genera pagebrick-{version}.zip (lo que la gente sube a su alojamiento) y,
# con una clave y una dirección de descarga, imprime la entrada "core" firmada.
php tools/pagebrick.php release ./dist ~/.pagebrick/catalog.key https://github.com/pagebrick/pagebrick/releases/download/v1.0.1/pagebrick-1.0.1.zip
```

## Publicar una nueva versión de PageBrick

1. Cambia `PB_VERSION` en [core/bootstrap.php](../../core/bootstrap.php) y agrega la versión a [CHANGELOG.md](../../CHANGELOG.md).
2. Ejecuta todas las pruebas: `docker compose exec app vendor/bin/phpunit`. Las pruebas de compatibilidad deben pasar sin tocarlas.
3. Genera y firma: `php tools/pagebrick.php release ./dist <key> <download-url>`.
4. Crea el release de GitHub `v{version}` con `pagebrick-{version}.zip` adjunto (la dirección de descarga debe coincidir con la firmada).
5. Pon la entrada impresa en `"core"` en el repositorio del catálogo.

Los sitios ven la versión nueva en un plazo de 12 horas, o de inmediato con **Comprobar ahora** en **Configuración → Actualizaciones**.

## Publicar un plugin o tema en el catálogo oficial

1. Asegúrate de que declare `"api": 1`, funcione con el contenido predeterminado y pase la activación en un sitio recién instalado.
2. Abre un issue o un pull request en el repositorio del catálogo con un enlace al código fuente.
3. Un mantenedor lo empaqueta, lo firma con la clave del proyecto y agrega su entrada.

La firma garantiza que el archivo es el que publicaron los mantenedores. No es una revisión de código: instala solo lo que te dé confianza.
