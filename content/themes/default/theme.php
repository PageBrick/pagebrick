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
        // The sections of a page the client can put in any order (their fields are the standard ones; the template prints them with
        // pb_sections(), or $page->order() and pb_section()). 'plugins' is where plugins add theirs; 'content' is the page's own fields (title, text...).
        // 'about' => 'image_text' says that the section is the same thing as that kind of piece, so the client can duplicate it.
        // 'blocks' => true lets the client add pieces of any kind (text, photo with text, cards...) and put them among the sections: core/blocks/*.php
        // draws them as plain HTML, and a file of the same name in templates/blocks/ replaces it. A theme that doesn't say 'blocks' gets none.
        'home' => [
            'sections' => ['hero', 'about' => 'image_text', 'services' => 'cards', 'numbers' => 'numbers', 'testimonials' => 'testimonials', 'plugins', 'cta' => 'cta'],
            'blocks' => true,
        ],
        'page' => ['sections' => ['content'], 'blocks' => true],
        'services' => ['sections' => ['content', 'cta' => 'cta'], 'blocks' => true],
        'contact' => ['sections' => ['content'], 'blocks' => true],
    ],
];
