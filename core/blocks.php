<?php
// Pieces: sections a client can add to a page (text, photo with text, cards...), put anywhere among the page's own sections, duplicate and remove.
//
// The core defines what each piece holds (the fields below) and prints it as plain HTML with stable class names and NO CSS: core/blocks/{type}.php.
// A theme draws it its own way by putting a file of the same name in its templates/blocks/ folder, and turns pieces on for a page type in theme.php
// ('blocks' => true, or a list of types). What the client adds is kept in the page's data under '_blocks', and its place among the sections in '_order'
// (as "block-{id}"); a page without pieces is stored, and printed, exactly as before.

const PB_BLOCK_LIMIT = 100;

/**
 * The kinds of piece, in the order the gallery shows them: type => ['label', 'help', 'icon', 'fields'].
 * Fields use the standard types, so the panel, the cleaning and the content API need nothing special.
 */
function pb_block_types(): array
{
    $title = ['type' => 'text', 'label' => __('Título')];
    $list = fn(string $label, string $item, string $add, array $fields) => ['type' => 'list', 'label' => $label, 'item_label' => $item, 'add_label' => $add, 'fields' => $fields];
    return [
        'text' => ['label' => __('Texto'), 'help' => __('Um título e um texto.'), 'icon' => 'align-left', 'fields' => [
            'title' => $title,
            'text' => ['type' => 'richtext', 'label' => __('Texto')],
        ]],
        'image_text' => ['label' => __('Foto com texto'), 'help' => __('Uma foto ao lado de um texto.'), 'icon' => 'columns-2', 'fields' => [
            'title' => $title,
            'text' => ['type' => 'richtext', 'label' => __('Texto')],
            'image' => ['type' => 'image', 'label' => __('Foto')],
            'side' => ['type' => 'select', 'label' => __('Onde fica a foto'), 'options' => ['right' => __('À direita do texto'), 'left' => __('À esquerda do texto')], 'default' => 'right'],
        ]],
        'cards' => ['label' => __('Cartões'), 'help' => __('Uma grade de cartões, cada um com título, texto e foto.'), 'icon' => 'layout-grid', 'fields' => [
            'title' => $title,
            'intro' => ['type' => 'textarea', 'label' => __('Introdução')],
            'items' => $list(__('Cartões'), __('Cartão'), __('Adicionar cartão'), [
                'title' => $title,
                'text' => ['type' => 'textarea', 'label' => __('Texto')],
                'image' => ['type' => 'image', 'label' => __('Foto')],
                'link' => ['type' => 'link', 'label' => __('O cartão leva para')],
            ]),
            'link_label' => ['type' => 'text', 'label' => __('Texto do link no fim da seção')],
            'link' => ['type' => 'link', 'label' => __('O link leva para')],
        ]],
        'faq' => ['label' => __('Perguntas e respostas'), 'help' => __('Perguntas que abrem para mostrar a resposta.'), 'icon' => 'circle-question-mark', 'fields' => [
            'title' => $title,
            'items' => $list(__('Perguntas'), __('Pergunta'), __('Adicionar pergunta'), [
                'question' => ['type' => 'text', 'label' => __('Pergunta')],
                'answer' => ['type' => 'richtext', 'label' => __('Resposta')],
            ]),
        ]],
        'testimonials' => ['label' => __('Depoimentos'), 'help' => __('O que os clientes dizem de você.'), 'icon' => 'quote', 'fields' => [
            'title' => $title,
            'items' => $list(__('Depoimentos'), __('Depoimento'), __('Adicionar depoimento'), [
                'quote' => ['type' => 'textarea', 'label' => __('O que a pessoa disse')],
                'name' => ['type' => 'text', 'label' => __('Nome')],
                'role' => ['type' => 'text', 'label' => __('Empresa, cargo ou cidade')],
            ]),
        ]],
        'cta' => ['label' => __('Chamada para ação'), 'help' => __('Um convite com um botão.'), 'icon' => 'megaphone', 'fields' => [
            'title' => $title,
            'text' => ['type' => 'textarea', 'label' => __('Texto')],
            'button_label' => ['type' => 'text', 'label' => __('Texto do botão')],
            'button_link' => ['type' => 'link', 'label' => __('O botão leva para')],
        ]],
        'numbers' => ['label' => __('Números'), 'help' => __('Números que mostram sua experiência.'), 'icon' => 'hash', 'fields' => [
            'title' => $title,
            'items' => $list(__('Números'), __('Número'), __('Adicionar número'), [
                'value' => ['type' => 'text', 'label' => __('Número'), 'help' => __('Por exemplo: 15, 300+ ou 4,9')],
                'label' => ['type' => 'text', 'label' => __('O que ele significa')],
            ]),
        ]],
        'gallery' => ['label' => __('Galeria de fotos'), 'help' => __('Várias fotos, cada uma com sua legenda.'), 'icon' => 'images', 'fields' => [
            'title' => $title,
            'items' => $list(__('Fotos'), __('Foto'), __('Adicionar foto'), [
                'image' => ['type' => 'image', 'label' => __('Foto')],
                'caption' => ['type' => 'text', 'label' => __('Legenda')],
            ]),
        ]],
        'video' => ['label' => __('Vídeo'), 'help' => __('Um vídeo do YouTube.'), 'icon' => 'circle-play', 'fields' => [
            'title' => $title,
            'url' => ['type' => 'url', 'label' => __('Endereço do vídeo no YouTube'), 'help' => __('Copie o endereço da barra do navegador, por exemplo https://www.youtube.com/watch?v=…')],
            'caption' => ['type' => 'text', 'label' => __('Legenda')],
        ]],
    ];
}

function pb_block_type(string $type): ?array
{
    return pb_block_types()[$type] ?? null;
}

/** The pieces a page type accepts (the theme says so in theme.php: 'blocks' => true for all of them, or a list of types); [] when it accepts none. */
function pb_template_blocks(string $template): array
{
    $templates = pb_theme()['templates'];
    $setting = ($templates[$template] ?? $templates['page'] ?? [])['blocks'] ?? false;
    $all = array_keys(pb_block_types());
    return $setting === true ? $all : (is_array($setting) ? array_values(array_intersect($setting, $all)) : []);
}

/** The id of a new piece: short, and only what is safe in a form field name and a CSS class. */
function pb_new_block_id(): string
{
    return substr(bin2hex(random_bytes(4)), 0, 6);
}

/** The pieces of a form, cleaned: only the allowed types, each with its own fields cleaned by the type's definitions. A piece hidden by the client stays, hidden. */
function pb_collect_blocks(mixed $input, array $allowed): array
{
    $blocks = [];
    foreach (is_array($input) ? $input : [] as $id => $block) {
        $id = (string) $id;
        $type = is_array($block) && is_string($block['_type'] ?? null) ? $block['_type'] : '';
        if (!preg_match('/^[a-z0-9]{3,16}$/', $id) || !in_array($type, $allowed, true) || !($definition = pb_block_type($type)) || count($blocks) >= PB_BLOCK_LIMIT) {
            continue;
        }
        $blocks[$id] = ['_type' => $type, '_visible' => ($block['_visible'] ?? '1') !== '0'] + pb_collect_fields($definition['fields'], $block);
    }
    return $blocks;
}

/**
 * What a theme's own section holds, as a piece of $type: the fields with the same name and type are copied (a list keeps the columns the piece has).
 * This is how "duplicate" works on a section the theme drew: the theme says which piece is the same thing (theme.php: 'sections' => ['about' => 'image_text']).
 */
function pb_piece_from_section(string $type, array $section, array $data): array
{
    $fields = pb_block_type($type)['fields'] ?? [];
    $text = function (mixed $value) use (&$text) { return is_array($value) ? array_map($text, $value) : (is_scalar($value) ? (string) $value : ''); };
    $copy = [];
    foreach ($fields as $name => $definition) {
        if (!isset($section['fields'][$name]) || ($section['fields'][$name]['type'] ?? '') !== $definition['type'] || !array_key_exists($name, $data)) {
            continue;
        }
        $copy[$name] = $text($data[$name]);
    }
    return pb_collect_fields($fields, $copy);
}

/** The 11-character id of a YouTube video from any of its usual addresses; '' for anything else (nothing but YouTube is ever put in the page). */
function pb_youtube_id(string $url): string
{
    $parts = parse_url(trim($url));
    $host = strtolower(preg_replace('/^(www|m)\./i', '', (string) ($parts['host'] ?? '')));
    if (!in_array($host, ['youtube.com', 'youtube-nocookie.com', 'youtu.be'], true) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
        return '';
    }
    $path = (string) ($parts['path'] ?? '');
    parse_str((string) ($parts['query'] ?? ''), $query);
    $id = $host === 'youtu.be' ? ltrim($path, '/') : (preg_match('~^/(?:embed|shorts|live|v)/([^/?]+)~', $path, $m) ? $m[1] : (string) ($query['v'] ?? ''));
    return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : '';
}

// ------------------------------------------------------------------ what the site prints

/** The file that draws a piece: the theme's (templates/blocks/{type}.php) or, without one, the core's plain HTML. */
function pb_block_file(string $type): string
{
    $own = pb_theme()['dir'] . "/templates/blocks/$type.php";
    return is_file($own) ? $own : PB_ROOT . "/core/blocks/$type.php";
}

/**
 * A piece the client added, as HTML ('' when it is hidden, gone, or of a kind this theme does not draw).
 * $section is its name in the page's order: "block-{id}". The template gets $block (its fields), $type, $id and $page.
 */
function pb_block(PbGroup $page, string $section): string
{
    $block = $page->piece($section);
    if (!$block || !$block->visible() || !in_array($block->type(), pb_template_blocks(pb_current_template()), true)) {
        return '';
    }
    return pb_include(pb_block_file($block->type()), ['block' => $block, 'type' => $block->type(), 'id' => substr($section, 6), 'page' => $page]);
}

/** The page type being drawn (the pieces a theme allows depend on it): a type the theme does not have is drawn as a simple page. */
function pb_current_template(): string
{
    $template = (string) ($GLOBALS['pb_current_page']['template'] ?? 'page');
    return isset(pb_theme()['templates'][$template]) ? $template : 'page';
}

/** The pieces a page has, and this theme draws for its type: id => the piece as stored. */
function pb_page_blocks(array $data, string $template): array
{
    $allowed = pb_template_blocks($template);
    $blocks = [];
    foreach (is_array($data['_blocks'] ?? null) ? $data['_blocks'] : [] as $id => $block) {
        if (is_array($block) && in_array($block['_type'] ?? '', $allowed, true)) {
            $blocks[(string) $id] = $block;
        }
    }
    return $blocks;
}

/**
 * All the sections of a page, in the order the client chose: the ones the theme draws ($render gets a name from $names and gives its HTML) and the
 * pieces the client added, each marked by pb_section() while the page is being edited. A theme's template is just:
 *   <?= pb_sections($page, ['hero', 'about', 'cta'], fn($name) => pb_include(__DIR__ . "/parts/$name.php", ['page' => $page])) ?>
 */
function pb_sections(PbGroup $page, array $names, callable $render): string
{
    $html = '';
    foreach ($page->order($names) as $section) {
        $html .= pb_section($section, str_starts_with($section, 'block-') ? pb_block($page, $section) : (string) $render($section));
    }
    return $html;
}

// ------------------------------------------------------------------ the panel

/** A drawing of what a piece looks like, for the gallery: plain lines and boxes, in the colour of the text. */
function pb_block_sketch(string $type): string
{
    $line = fn(int $x, int $y, int $w, int $h = 3) => "<rect x=\"$x\" y=\"$y\" width=\"$w\" height=\"$h\" rx=\"1.5\" fill=\"currentColor\" opacity=\".55\"/>";
    $box = fn(int $x, int $y, int $w, int $h) => "<rect x=\"$x\" y=\"$y\" width=\"$w\" height=\"$h\" rx=\"3\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.6\" opacity=\".75\"/>";
    $shapes = match ($type) {
        'text' => $line(10, 10, 30, 5) . $line(10, 21, 76) . $line(10, 28, 70) . $line(10, 35, 74) . $line(10, 42, 52),
        'image_text' => $box(8, 8, 36, 40) . $line(52, 12, 26, 5) . $line(52, 24, 34) . $line(52, 31, 30) . $line(52, 38, 32),
        'cards' => $box(6, 14, 26, 32) . $box(35, 14, 26, 32) . $box(64, 14, 26, 32) . $line(10, 36, 18) . $line(39, 36, 18) . $line(68, 36, 18),
        'faq' => $box(8, 8, 80, 11) . $box(8, 22, 80, 11) . $box(8, 36, 80, 11) . $line(13, 12, 40) . $line(13, 26, 32) . $line(13, 40, 46),
        'testimonials' => '<text x="10" y="30" font-size="26" font-weight="700" fill="currentColor" opacity=".55">“</text>' . $line(26, 16, 58) . $line(26, 24, 50) . $line(26, 32, 54) . '<circle cx="31" cy="43" r="4" fill="none" stroke="currentColor" stroke-width="1.6" opacity=".75"/>' . $line(40, 41, 26),
        'cta' => $line(24, 12, 48, 5) . $line(18, 24, 60) . $line(26, 31, 44) . '<rect x="32" y="40" width="32" height="10" rx="5" fill="currentColor" opacity=".75"/>',
        'numbers' => $line(10, 14, 20, 8) . $line(38, 14, 20, 8) . $line(66, 14, 20, 8) . $line(10, 28, 22) . $line(38, 28, 22) . $line(66, 28, 22) . $line(10, 40, 18) . $line(38, 40, 18) . $line(66, 40, 18),
        'gallery' => $box(8, 8, 38, 20) . $box(50, 8, 38, 20) . $box(8, 31, 38, 17) . $box(50, 31, 38, 17),
        'video' => $box(8, 8, 80, 40) . '<path d="M42 20l14 8-14 8z" fill="currentColor" opacity=".75"/>',
        default => '',
    };
    return '<svg viewBox="0 0 96 56" aria-hidden="true">' . $shapes . '</svg>';
}

/**
 * The small buttons at the end of a row of the page's list: copy this section (when it can be copied), remove it (a piece the client added), and
 * the "+" on the top edge that adds a new section right before it (when the page accepts pieces).
 */
function pb_row_actions(string $label, bool $copy, bool $remove, bool $insert): string
{
    $button = fn(string $attr, string $icon, string $title, string $aria, string $class = '') => '<button type="button" class="pb-row-btn' . $class . '" ' . $attr
        . ' title="' . e($title) . '" aria-label="' . e(sprintf($aria, $label)) . '">' . pb_admin_icon($icon) . '</button>';
    return ($copy || $remove ? '<span class="pb-row-actions">'
            . ($copy ? $button('data-duplicate', 'copy', __('Duplicar esta seção'), __('Duplicar a seção "%s"')) : '')
            . ($remove ? $button('data-remove-piece', 'trash', __('Remover esta seção'), __('Remover a seção "%s"'), ' danger') : '')
            . '</span>' : '')
        . ($insert ? '<button type="button" class="pb-plus" data-insert-before title="' . e(__('Adicionar uma seção aqui')) . '" aria-label="'
            . e(sprintf(__('Adicionar uma seção antes de "%s"'), $label)) . '">' . pb_admin_icon('plus') . '</button>' : '');
}

/** What the client sees of a piece in the list of the page: the row to move, with the fields inside. $open: the row starts open (a piece just added). */
function pb_piece_row(string $id, string $type, array $data, bool $open = false): string
{
    $definition = pb_block_type($type) ?? ['label' => $type, 'icon' => 'layout-grid', 'fields' => []];
    $name = "f[_blocks][$id]";
    $visible = ($data['_visible'] ?? true) !== false;
    $label = $definition['label'];
    $title = trim((string) ($data['title'] ?? ''));
    return '<div class="pb-section pb-piece" data-section="block-' . e($id) . '" data-sort="block-' . e($id) . '" data-piece="' . e($type) . '" data-tools="2">'
        . pb_row_tools($label, pb_row_actions($label, true, true, true))
        . '<details class="pb-section-box"' . ($open ? ' open' : '') . '>'
        . '<summary><span class="pb-piece-icon">' . pb_admin_icon($definition['icon']) . '</span>'
        . '<span class="pb-section-name" data-sort-name>' . e($label) . '</span>'
        . '<span class="pb-piece-title" data-piece-title>' . e($title) . '</span>'
        . '<span class="badge muted" data-hidden-badge' . ($visible ? ' hidden' : '') . '>' . e(__('oculta')) . '</span>'
        . '</summary>'
        . '<input type="hidden" name="f[_order][]" value="block-' . e($id) . '">'
        . '<input type="hidden" name="' . e($name) . '[_type]" value="' . e($type) . '">'
        . '<label class="switch"><input type="hidden" name="' . e($name) . '[_visible]" value="0">'
        . '<input type="checkbox" name="' . e($name) . '[_visible]" value="1"' . ($visible ? ' checked' : '') . ' data-visible-switch> ' . e(__('Mostrar esta seção no site')) . '</label>'
        . pb_field_inputs($definition['fields'], $data, $name)
        . '</details></div>';
}

/** The gallery the client picks a new piece from: a big button for each kind, with its drawing and one line to say what it is. */
function pb_block_gallery(array $types): string
{
    $html = '';
    foreach ($types as $type) {
        if (!$definition = pb_block_type($type)) {
            continue;
        }
        $html .= '<button type="button" class="pb-pick" data-add-piece="' . e($type) . '">'
            . '<span class="pb-pick-sketch">' . pb_block_sketch($type) . '</span>'
            . '<span class="pb-pick-name">' . e($definition['label']) . '</span>'
            . '<span class="pb-pick-help">' . e($definition['help']) . '</span></button>';
    }
    return $html;
}

/**
 * The answer to "add a section" and "duplicate this section" (the page editor asks with the form as it is now): the row to put in the list,
 * rendered here so the panel never has to guess what a copy contains. Errors come as a message the person can read.
 */
function pb_piece_response(array $post): array
{
    $page = pb_page_find((int) ($post['id'] ?? 0));
    if (!$page) {
        return ['ok' => false, 'error' => __('Página não encontrada.')];
    }
    $template = $page['template'];
    $allowed = pb_template_blocks($template);
    $form = is_array($post['f'] ?? null) ? $post['f'] : [];
    $taken = is_array($form['_blocks'] ?? null) ? array_keys($form['_blocks']) : [];
    if (count($taken) >= PB_BLOCK_LIMIT) {
        return ['ok' => false, 'error' => __('A página já tem seções demais. Remova alguma antes de adicionar outra.')];
    }
    do {
        $id = pb_new_block_id();
    } while (in_array($id, $taken, true));

    $type = '';
    $data = [];
    $section = (string) ($post['section'] ?? '');
    if (($post['op'] ?? '') === 'add') {
        $type = is_string($post['type'] ?? null) ? $post['type'] : '';
        $data = pb_collect_fields(pb_block_type($type)['fields'] ?? [], []);
    } elseif (str_starts_with($section, 'block-')) { // a copy of a piece, as it is in the form now
        $source = pb_collect_blocks($form['_blocks'] ?? [], $allowed)[substr($section, 6)] ?? [];
        $type = (string) ($source['_type'] ?? '');
        $data = $source;
    } elseif (($type = pb_template_section_types($template)[$section] ?? '') !== '' && ($group = pb_template_fields($template)[$section] ?? null)) {
        $data = pb_piece_from_section($type, $group, pb_collect_value($group, $form[$section] ?? []));
    }
    if (!in_array($type, $allowed, true) || !$data) {
        return ['ok' => false, 'error' => __('Não foi possível criar esta seção.')];
    }
    $data = ['_type' => $type, '_visible' => true] + $data;
    return ['ok' => true, 'id' => $id, 'name' => "block-$id", 'type' => $type, 'label' => pb_block_type($type)['label'], 'html' => pb_piece_row($id, $type, $data, true)];
}
