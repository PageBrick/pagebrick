<?php
// Ready-made content of the test theme (see pb_import_content). The photo is created by ThemesTest.
return [
    'media' => ['foto' => ['file' => 'foto.png', 'alt' => 'Foto do tema']],
    'pages' => [
        ['title' => 'Início do tema', 'slug' => 'inicio', 'template' => 'home', 'home' => true,
            'data' => ['hero' => ['title' => 'TEMA-SEGUNDO-HERO', 'button_link' => 'page:novidades', 'image' => 'media:foto']]],
        ['title' => 'Novidades', 'slug' => 'novidades', 'template' => 'page', 'data' => ['intro' => 'Página que vem com o tema.']],
    ],
    'menus' => ['main' => [['label' => '', 'link' => 'page:novidades']]],
    'settings' => ['identity' => ['color' => '#123456']],
];
