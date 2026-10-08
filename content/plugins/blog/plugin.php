<?php
// Official PageBrick plugin: blog with categories.
// This file only registers what the plugin adds; the code lives in functions.php.

require_once __DIR__ . '/functions.php';

$table = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
pb_plugin_migrations([
    1 => [
        'CREATE TABLE ' . pb_table('blog_posts') . " (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            slug VARCHAR(190) NOT NULL UNIQUE,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            published_on DATE NOT NULL,
            data MEDIUMTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY status_date (status, published_on)
        ) $table",
        'CREATE TABLE ' . pb_table('blog_categories') . " (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(190) NOT NULL UNIQUE,
            parent_id INT UNSIGNED NULL,
            description VARCHAR(500) NOT NULL DEFAULT '',
            KEY parent_id (parent_id)
        ) $table",
        'CREATE TABLE ' . pb_table('blog_post_categories') . " (
            post_id INT UNSIGNED NOT NULL,
            category_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (post_id, category_id),
            KEY category_id (category_id)
        ) $table",
    ],
]);

pb_plugin_settings([
    'title' => ['type' => 'text', 'label' => __('Nome do blog'), 'help' => __('Em branco: "Blog". Pode ser "Notícias", "Artigos", "Novidades"…')],
    'path' => ['type' => 'text', 'label' => __('Endereço'), 'help' => __('Em branco: blog (fica em seusite.com.br/blog). Use só letras, números e hífen. Não use o endereço de uma página que já existe.')],
    'intro' => ['type' => 'textarea', 'label' => __('Introdução da página do blog')],
    'home' => ['type' => 'select', 'label' => __('Textos mais recentes na página inicial'), 'default' => '3',
        'options' => ['0' => __('Não mostrar'), '3' => __('Mostrar os 3 mais recentes'), '6' => __('Mostrar os 6 mais recentes')]],
]);

$base = pbb_base();
pb_add_route('GET', "/$base", 'pbb_list_page');
pb_add_route('GET', "/$base/*", 'pbb_route');
// Content API for front ends built outside PageBrick (see core/headless.php).
pb_add_route('GET', '/api/v1/blog', 'pbb_api_list');
pb_add_route('GET', '/api/v1/blog/*', 'pbb_api_post');
pb_add_filter('slot:home', fn(string $html) => $html . pbb_home_section());
pb_add_filter('sitemap_urls', fn(array $urls) => array_merge($urls, pbb_sitemap_urls()));
pb_add_filter('head_html', fn(string $html) => $html . pbb_stylesheet());
pb_add_filter('link_targets', fn(array $targets) => $targets + pbb_link_targets());
pb_add_admin_page('blog', pbb_title(), 'pbb_admin');
