<?php
// FROZEN: an agency theme built on PageBrick 1.0 (see ../../README.md). Never edit.
return [
    'templates' => [
        'home' => ['fields' => [
            'video' => ['type' => 'url', 'label' => 'Vídeo do destaque'],
        ]],
        'landing' => ['label' => 'Página de campanha', 'fields' => [
            'offer' => ['type' => 'text', 'label' => 'Oferta'],
            'deadline' => ['type' => 'text', 'label' => 'Prazo'],
            'perks' => ['type' => 'list', 'label' => 'Vantagens', 'fields' => [
                'title' => ['type' => 'text', 'label' => 'Vantagem'],
                'icon' => ['type' => 'image', 'label' => 'Ícone'],
            ]],
            'cta' => ['type' => 'group', 'label' => 'Botão', 'toggle' => true, 'fields' => [
                'label' => ['type' => 'text', 'label' => 'Texto'],
                'link' => ['type' => 'link', 'label' => 'Leva para'],
            ]],
        ]],
    ],
    'settings' => [
        'agency' => ['type' => 'group', 'label' => 'Agência', 'fields' => [
            'slogan' => ['type' => 'text', 'label' => 'Slogan'],
            'accent' => ['type' => 'color', 'label' => 'Cor de destaque', 'default' => '#0a7c66'],
        ]],
    ],
    'menus' => ['top' => 'Menu do topo'],
];
