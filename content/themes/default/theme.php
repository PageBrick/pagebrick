<?php
// PageBrick's default theme. The content fields are the standard ones defined by the core (core/standard.php):
// this theme only decides how they look. Copy this folder to start your own theme.
//
// A theme may ADD its own fields or page types here, and they show up in the panel by themselves, e.g.:
//   'templates' => [
//       'home' => ['fields' => ['video' => ['type' => 'url', 'label' => 'Vídeo do destaque']]],
//       'landing' => ['label' => 'Página de campanha', 'fields' => [...]],   // needs templates/landing.php
//   ],
//   'settings' => ['extras' => ['type' => 'group', 'label' => 'Opções do tema', 'fields' => [...]]],
// Content in extra fields stays saved if the site switches to another theme and comes back.

return [
    'templates' => [
        // The sections of the home page the client can put in any order (their fields are the standard ones; templates/home.php prints them
        // with $page->order() and pb_section()). 'plugins' is where plugins add theirs. A theme that doesn't list 'sections' keeps its own order.
        'home' => ['sections' => ['hero', 'about', 'services', 'numbers', 'testimonials', 'plugins', 'cta']],
    ],
];
