<p align="center"><img src="docs/brand/svg/pagebrick-logo-horizontal.svg" alt="PageBrick" width="320"></p>

<p align="center"><a href="README.md">English</a> · <a href="README.pt-BR.md">Português</a> · <b>Español</b></p>

Un CMS sencillo para sitios web de empresas. Un dueño sin conocimientos técnicos lo instala y publica el sitio de su empresa con un diseño listo; las agencias crean sus propios temas sobre una estructura ordenada, con total libertad en el front-end y la garantía de que las actualizaciones no romperán los sitios que entregan.

## Qué hace

- Instalador web que crea un sitio de ejemplo terminado: Inicio, Nosotros, Servicios, Contacto y Política de privacidad, con fotos. En portugués, inglés o español.
- Páginas con campos, secciones que el cliente puede ocultar, historial con "restaurar", borradores y vista previa.
- Logo, color, estilo de fuente, WhatsApp, datos de contacto y redes sociales en **Apariencia y contacto**.
- Temas que puedes cambiar sin perder contenido, con vista previa privada antes de activarlos.
- Formulario de contacto (mensajes en el panel y por correo electrónico, protección contra spam sin captcha) y un blog con categorías, como plugins oficiales.
- Tienda de plugins y temas con paquetes firmados, subida de .zip, actualizaciones con copia de seguridad, "volver a una versión anterior" y deshacer automático si una versión nueva falla.
- Cortacircuitos (protección automática): un plugin o tema roto se desactiva solo y el sitio sigue en línea. Modo seguro con enlace de rescate.
- Actualizaciones de PageBrick en un clic, que después revisan todo el sitio y vuelven atrás solas si algo se rompió.
- API de contenido (JSON) para front-ends hechos con Next.js, Astro o cualquier otro framework.
- SEO básico (título y descripción por página, direcciones amigables, sitemap.xml, robots.txt), imágenes redimensionadas a WebP, sin cookies para los visitantes.
- Dos roles: Administrador (la agencia) y Editor (el cliente).

## Requisitos

PHP 8.2+ con `pdo_mysql` y `gd`, MySQL 5.7+ o MariaDB 10.4+, y Apache con `mod_rewrite`: cualquier alojamiento (hosting) con cPanel.

## Instalación

1. Descarga `pagebrick-x.y.z.zip` desde [Releases](https://github.com/pagebrick/pagebrick/releases) y sube el contenido de su carpeta `pagebrick/` a tu alojamiento.
2. Crea una base de datos MySQL (en cPanel: "MySQL Databases").
3. Abre la dirección del sitio y completa el instalador.

## Para desarrolladores

- [Crear un tema](docs/es/temas.md): el front-end es todo tuyo.
- [Crear un plugin](docs/es/plugins.md)
- [API de contenido (headless)](docs/es/headless.md): el contenido en JSON, para front-ends hechos fuera de PageBrick.
- [La promesa de compatibilidad](docs/es/compatibilidad.md): por qué las actualizaciones no rompen tus sitios.
- [Publicación: catálogo, firmas y versiones](docs/es/publicacion.md)

### Desarrollo local

```bash
docker compose up -d --build
docker compose exec app composer install
```

Abre http://localhost:8080. En el instalador usa el servidor `db`, la base de datos `pagebrick`, el usuario `pagebrick` y la contraseña `pagebrick`.

Cuentas de prueba para el desarrollo local: `admin@pagebrick.test` (administrador) y `editor@pagebrick.test` (editor), ambas con la contraseña `pagebrick-local`.

Mailpit captura los correos que envía el sitio local en http://localhost:8025. Para usarlo, configura el servidor `mailpit`, el puerto `1025` y la seguridad "Ninguna" en **Sistema → Correo electrónico**.

Pruebas:

```bash
docker compose exec app vendor/bin/phpunit
```

Para empezar de cero: borra `config.php` y ejecuta `docker compose down -v`.

## Licencia

[GPL-3.0-or-later](LICENSE). Creado por [Alcateia Digital](https://alcateia.digital). Incluye el editor [Trix](https://github.com/basecamp/trix) (MIT), [PHPMailer](https://github.com/PHPMailer/PHPMailer) (LGPL 2.1), las fuentes Public Sans, Fraunces y Nunito (OFL) y fotos de ejemplo de dominio público (CC0).
