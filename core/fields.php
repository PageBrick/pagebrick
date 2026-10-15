<?php
// The field system. Themes declare fields; the panel renders inputs for them;
// submitted data is cleaned here; templates read it through auto-escaping objects.
//
// A field definition: ['type' => 'text', 'label' => 'Título', 'help' => '...', ...]
// Types: text, password, textarea, richtext, image, url, email, tel, color, select (+ 'options'),
//        link (a page or an address), list (+ 'fields', 'item_label', 'add_label'),
//        group (+ 'fields'; 'toggle' => true adds a "show this section" switch).

// ------------------------------------------------------------------ cleaning (trust boundary)

/** Cleans submitted values against field definitions: unknown keys are dropped and every value is validated. */
function pb_collect_fields(array $defs, mixed $input): array
{
    $input = is_array($input) ? $input : [];
    $data = [];
    foreach ($defs as $name => $def) {
        $data[$name] = pb_collect_value($def, $input[$name] ?? null);
    }
    return $data;
}

function pb_collect_value(array $def, mixed $value): mixed
{
    if ($def['type'] === 'list') {
        $items = [];
        foreach (is_array($value) ? $value : [] as $item) {
            $item = pb_collect_fields($def['fields'], $item);
            if (array_filter($item, fn($v) => !in_array($v, ['', 0, []], true))) {
                $items[] = $item; // fully empty items are dropped
            }
        }
        return $items;
    }
    if ($def['type'] === 'group') {
        $group = pb_collect_fields($def['fields'], $value);
        if (!empty($def['toggle'])) {
            // The form always sends 0 or 1; a missing value (new page, demo content) means "show".
            $group['_visible'] = !is_array($value) || ($value['_visible'] ?? '1') !== '0';
        }
        return $group;
    }
    if ($def['type'] === 'link') {
        $page = is_array($value) && is_string($value['page'] ?? null) ? $value['page'] : (is_string($value) ? $value : '');
        if (preg_match('/^page:\d+$/', $page)) {
            return $page;
        }
        // A choice from the list other than a page: an address offered by a plugin (e.g. "/blog").
        $value = is_array($value) ? ($page !== '' ? $page : ($value['url'] ?? '')) : $value;
    }

    $value = is_string($value) ? trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '') : '';
    return match ($def['type']) {
        'text', 'password' => pb_limit($value, 500),
        'textarea' => pb_limit($value, 5000),
        'richtext' => pb_sanitize_html($value),
        'image' => ctype_digit($value) ? (int) $value : 0,
        'url', 'link' => pb_clean_url($value),
        'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? strtolower($value) : '',
        'tel' => pb_limit(preg_replace('/[^0-9+()\- ]/', '', $value), 30),
        'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : ($def['default'] ?? ''),
        'select' => array_key_exists($value, $def['options']) ? $value : (string) ($def['default'] ?? array_key_first($def['options'])),
        default => throw new LogicException("Unknown field type '{$def['type']}'"),
    };
}

/** Accepts web, e-mail, phone and same-site addresses; anything else (javascript:, data:...) becomes ''. */
function pb_clean_url(string $url): string
{
    $url = trim($url);
    if ($url === '' || preg_match('/[\s<>"\']/', $url)) {
        return '';
    }
    if (preg_match('~^(https?://|mailto:|tel:|/|#)~i', $url)) {
        return pb_limit($url, 500);
    }
    if (preg_match('~^[a-z0-9-]+(\.[a-z0-9-]+)+(/\S*)?$~i', $url)) {
        return 'https://' . pb_limit($url, 492); // "www.site.com.br" typed without https://
    }
    return '';
}

/**
 * Keeps only safe formatting from the rich text editor: no scripts, styles, attributes or event handlers.
 * Tables, dividers and pictures of the media library stay; a picture is accepted only by its media id (data-media), and the tag is rebuilt from it.
 */
function pb_sanitize_html(string $html): string
{
    if (trim($html) === '') {
        return '';
    }
    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $body = $doc->getElementsByTagName('body')->item(0);
    if (!$body) {
        return '';
    }
    pb_sanitize_children($body);
    // blank lines at the end are what the editor keeps after a table or a picture, so there is somewhere to type
    while ($last = $body->lastChild) {
        $blank = $last instanceof DOMText ? trim($last->textContent) === ''
            : $last instanceof DOMElement && in_array(strtolower($last->tagName), ['p', 'div'], true) && trim($last->textContent) === '' && !$last->getElementsByTagName('img')->length;
        if (!$blank) {
            break;
        }
        $body->removeChild($last);
    }
    $out = '';
    foreach ($body->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return trim($out);
}

function pb_sanitize_children(DOMNode $node): void
{
    static $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'del', 's', 'a', 'ul', 'ol', 'li', 'h2', 'h3', 'blockquote', 'div', 'hr',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td'];
    static $rename = ['h1' => 'h2', 'pre' => 'p'];
    static $drop = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select',
        'template', 'svg', 'math', 'noscript', 'head', 'title', 'meta', 'link', 'base'];

    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child instanceof DOMText) {
            continue;
        }
        if (!$child instanceof DOMElement || in_array(strtolower($child->tagName), $drop, true)) {
            $node->removeChild($child); // comments, processing instructions, dangerous elements
            continue;
        }
        if (strtolower($child->tagName) === 'img') {
            pb_sanitize_image($node, $child);
            continue;
        }
        pb_sanitize_children($child);
        $tag = strtolower($child->tagName);
        if (isset($rename[$tag])) {
            $renamed = $child->ownerDocument->createElement($rename[$tag]);
            while ($child->firstChild) {
                $renamed->appendChild($child->firstChild);
            }
            $node->replaceChild($renamed, $child);
            [$child, $tag] = [$renamed, $rename[$tag]];
        }
        if (!in_array($tag, $allowed, true)) {
            while ($child->firstChild) {
                $node->insertBefore($child->firstChild, $child); // keep the text, lose the tag
            }
            $node->removeChild($child);
            continue;
        }
        foreach (iterator_to_array($child->attributes) as $attr) {
            if (!($tag === 'a' && $attr->name === 'href')) {
                $child->removeAttribute($attr->name);
            }
        }
        if ($tag === 'a') {
            $href = pb_clean_url($child->getAttribute('href'));
            $href === '' ? $child->removeAttribute('href') : $child->setAttribute('href', $href);
        } elseif (in_array($tag, ['li', 'td', 'th'], true)) {
            pb_unwrap_single_paragraph($child);
        }
    }
}

/** A picture of the media library, rebuilt from its id: whatever else the submitted tag carried (address, handlers, sizes) is ignored. */
function pb_sanitize_image(DOMNode $parent, DOMElement $img): void
{
    $id = $img->getAttribute('data-media');
    $media = ctype_digit($id) ? pb_media_find((int) $id) : null;
    if (!$media || !str_starts_with((string) $media['mime'], 'image/')) {
        $parent->removeChild($img);
        return;
    }
    $alt = trim($img->getAttribute('alt'));
    foreach (iterator_to_array($img->attributes) as $attr) {
        $img->removeAttribute($attr->name);
    }
    $img->setAttribute('src', pb_media_url($media));
    $img->setAttribute('alt', pb_limit($alt !== '' ? $alt : (string) $media['alt'], 200));
    $img->setAttribute('width', (string) (int) $media['width']);
    $img->setAttribute('height', (string) (int) $media['height']);
    $img->setAttribute('data-media', (string) (int) $media['id']);
}

/** A list item or table cell that holds a single paragraph keeps just its text: <li><p>Item</p></li> becomes <li>Item</li>. */
function pb_unwrap_single_paragraph(DOMElement $cell): void
{
    $paragraph = null;
    foreach ($cell->childNodes as $child) {
        if ($child instanceof DOMText && trim($child->textContent) === '') {
            continue;
        }
        if ($paragraph || !$child instanceof DOMElement || strtolower($child->tagName) !== 'p') {
            return;
        }
        $paragraph = $child;
    }
    if (!$paragraph) {
        return;
    }
    while ($paragraph->firstChild) {
        $cell->insertBefore($paragraph->firstChild, $paragraph);
    }
    $cell->removeChild($paragraph);
}

/**
 * Rich text as it is shown: the pictures of the media library get their address, size and loading attributes from the library,
 * so they follow the site if it moves; a picture deleted from the library disappears. Text without pictures is returned as it is.
 * $forEditor keeps the media id for the panel's editor; $absolute gives full addresses for the content API.
 */
function pb_richtext_html(string $html, bool $forEditor = false, bool $absolute = false): string
{
    if (!str_contains($html, 'data-media=')) {
        return $html;
    }
    return (string) preg_replace_callback('~<img\b[^>]*\sdata-media="(\d+)"[^>]*>~', function (array $m) use ($forEditor, $absolute): string {
        $media = pb_media_find((int) $m[1]);
        if (!$media) {
            return '';
        }
        $url = pb_media_url($media);
        $alt = preg_match('~\salt="([^"]*)"~', $m[0], $a) ? $a[1] : e((string) $media['alt']);
        return sprintf('<img src="%s" alt="%s" width="%d" height="%d"%s>', e($absolute ? pb_content_absolute($url) : $url), $alt,
            $media['width'], $media['height'], $forEditor ? ' data-media="' . (int) $media['id'] . '"' : ' loading="lazy" decoding="async"');
    }, $html);
}

// ------------------------------------------------------------------ reading (templates)

/** A set of fields: a page's content, a section, a list item or the site settings. Reading a field gives a safe value. */
final class PbGroup
{
    public function __construct(private array $defs, private array $data)
    {
    }

    /**
     * The sections of the page in the order the client chose in the panel. $default is the theme's own order (the section names,
     * as they are listed in 'sections' of theme.php): names the client has not placed yet come last, in that order.
     * @return string[]
     */
    public function order(array $default): array
    {
        $saved = is_array($this->data['_order'] ?? null) ? $this->data['_order'] : [];
        return array_values(array_unique([...array_intersect($saved, $default), ...$default]));
    }

    /** False when the user switched this section off in the panel. */
    public function visible(): bool
    {
        return ($this->data['_visible'] ?? true) !== false;
    }

    public function __get(string $name): PbValue|PbList|PbGroup
    {
        $def = $this->defs[$name] ?? null;
        if ($def === null) {
            if ($GLOBALS['pb_config']['debug'] ?? false) {
                trigger_error("PageBrick: unknown field '$name'", E_USER_WARNING);
            }
            return new PbValue(['type' => 'text'], '');
        }
        $value = $this->data[$name] ?? null;
        if ($def['type'] === 'group') {
            return new PbGroup($def['fields'], is_array($value) ? $value : []);
        }
        if ($def['type'] === 'list') {
            return new PbList(array_map(fn($item) => new PbGroup($def['fields'], (array) $item), is_array($value) ? $value : []));
        }
        return new PbValue($def, $value ?? '');
    }

    public function __isset(string $name): bool
    {
        return isset($this->defs[$name]);
    }
}

final class PbList implements IteratorAggregate, Countable
{
    /** @param PbGroup[] $items */
    public function __construct(private array $items)
    {
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return !$this->items;
    }
}

final class PbValue implements Stringable
{
    public function __construct(private array $def, private mixed $value)
    {
    }

    /** Safe HTML for the value: text is escaped, rich text was cleaned on save, images become <img>. */
    public function __toString(): string
    {
        return match ($this->def['type']) {
            'richtext' => pb_richtext_html((string) $this->value),
            'textarea' => nl2br(e((string) $this->value), false),
            'image' => $this->img(),
            'url', 'link', 'email', 'tel' => e($this->url()),
            default => e((string) $this->value),
        };
    }

    /** The stored value, unescaped. If you print it, escape it yourself with e(). */
    public function raw(): mixed
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->def['type'] === 'image' ? !$this->media() : in_array($this->value, ['', 0, null], true);
    }

    /** Address for links, e-mails, phones and images ('thumb' for the small image). Escape it when printing. */
    public function url(string $size = 'full'): string
    {
        $value = (string) $this->value;
        return match ($this->def['type']) {
            'image' => ($m = $this->media()) ? pb_media_url($m, $size) : '',
            'link' => pb_link_url($value),
            'email' => $value === '' ? '' : "mailto:$value",
            'tel' => $value === '' ? '' : 'tel:' . preg_replace('/[^0-9+]/', '', $value),
            default => $value,
        };
    }

    /** <img> tag for an image field, '' when empty. Use lazy: false for images at the top of the page. */
    public function img(string $class = '', string $size = 'full', bool $lazy = true): string
    {
        $m = $this->media();
        if (!$m) {
            return '';
        }
        return sprintf('<img src="%s" alt="%s" width="%d" height="%d" %s%s>',
            e(pb_media_url($m, $size)), e($m['alt']), $m['width'], $m['height'],
            $lazy ? 'loading="lazy" decoding="async"' : 'fetchpriority="high"', $class === '' ? '' : ' class="' . e($class) . '"');
    }

    private function media(): ?array
    {
        return $this->def['type'] === 'image' && $this->value ? pb_media_find((int) $this->value) : null;
    }
}

// ------------------------------------------------------------------ panel inputs

/** Panel inputs for a set of fields, named like "$prefix[field]". */
function pb_field_inputs(array $defs, array $data, string $prefix): string
{
    $html = '';
    foreach ($defs as $name => $def) {
        $html .= pb_field_input($def, $data[$name] ?? null, "{$prefix}[{$name}]");
    }
    return $html;
}

function pb_field_input(array $def, mixed $value, string $name): string
{
    if ($def['type'] === 'list') {
        return pb_list_input($def, $value, $name);
    }
    if ($def['type'] === 'group') {
        return pb_group_input($def, is_array($value) ? $value : [], $name);
    }
    $id = 'f-' . trim(preg_replace('/[^a-z0-9_]+/i', '-', $name), '-');
    $v = is_scalar($value) ? (string) $value : '';
    $attrs = 'id="' . e($id) . '" name="' . e($name) . '"';

    $input = match ($def['type']) {
        'text' => "<input $attrs maxlength=\"500\" value=\"" . e($v) . '">',
        // Saved passwords are never sent back to the browser.
        'password' => "<input type=\"password\" $attrs autocomplete=\"new-password\" placeholder=\"" . e($v !== '' ? __('(guardada; deixe em branco para manter)') : '') . '">',
        'url' => "<input $attrs placeholder=\"https://\" value=\"" . e($v) . '">',
        'email' => "<input type=\"email\" $attrs value=\"" . e($v) . '">',
        'tel' => "<input type=\"tel\" $attrs value=\"" . e($v) . '">',
        'color' => "<input type=\"color\" $attrs value=\"" . e($v !== '' ? $v : ($def['default'] ?? '#000000')) . '">',
        'textarea' => "<textarea $attrs rows=\"4\">" . e($v) . '</textarea>',
        'select' => "<select $attrs>" . implode('', array_map(
            fn($key, $label) => '<option value="' . e((string) $key) . '"' . ((string) $key === ($v !== '' ? $v : (string) ($def['default'] ?? '')) ? ' selected' : '') . '>' . e($label) . '</option>',
            array_keys($def['options']), $def['options'])) . '</select>',
        // The panel's editor (core/assets/editor.js) replaces this box; without JavaScript it stays as a box with the text's HTML.
        'richtext' => "<textarea $attrs rows=\"8\" data-richtext>" . e(pb_richtext_html($v, true)) . '</textarea>',
        'image' => pb_image_input($id, $name, (int) $v),
        'link' => pb_link_input($id, $name, $v),
        default => throw new LogicException("Unknown field type '{$def['type']}'"),
    };
    return '<div class="field"><label id="' . e($id) . '-label" for="' . e($id) . '">' . e($def['label'] ?? $name) . '</label>'
        . $input . (isset($def['help']) ? '<p class="help">' . e($def['help']) . '</p>' : '') . '</div>';
}

/** The handle of a section of the page: drag it, or focus it and use the arrow keys (core/assets/studio.js). */
function pb_section_grip(string $label): string
{
    return '<button type="button" class="pb-grip" data-grip aria-label="' . e(sprintf(__('Mover a seção "%s"'), $label)) . '" title="'
        . e(__('Arraste para mudar a posição. Com o teclado: setas para cima e para baixo.')) . '">' . pb_admin_icon('grip-vertical') . '</button>';
}

/**
 * A section: a collapsible box, optionally with a "show on the site" switch. With $section (its name) it is a row the client can move:
 * a handle, and its place in the order goes with the form (f[_order][]).
 */
function pb_group_input(array $def, array $data, string $name, string $section = ''): string
{
    $visible = ($data['_visible'] ?? true) !== false;
    $switch = empty($def['toggle']) ? '' : '<label class="switch"><input type="hidden" name="' . e($name) . '[_visible]" value="0">'
        . '<input type="checkbox" name="' . e($name) . '[_visible]" value="1"' . ($visible ? ' checked' : '') . '> '
        . e(__('Mostrar esta seção no site')) . '</label>';
    // a top-level group is known to the page editor by its name, so a click on that part of the page can open it
    $group = preg_match('/^f\[([a-z0-9_]+)\]$/i', $name, $m) ? ' data-group="' . e($m[1]) . '"' : '';
    return '<details class="pb-group' . ($section !== '' ? ' pb-section' : '') . '"' . $group . ($section !== '' ? ' data-section="' . e($section) . '" data-sort="' . e($section) . '"' : '')
        . ($section === '' && ($visible || empty($def['toggle'])) ? ' open' : '') . '>' // a section of the page starts closed: a short row to drag, opened to edit
        . '<summary>' . ($section !== '' ? pb_section_grip((string) ($def['label'] ?? '')) : '') . '<span class="pb-section-name">' . e($def['label'] ?? '') . '</span>'
        . (empty($def['toggle']) || $visible ? '' : ' <span class="badge muted">' . e(__('oculta')) . '</span>') . '</summary>'
        . ($section !== '' ? '<input type="hidden" name="f[_order][]" value="' . e($section) . '">' : '')
        . (isset($def['help']) ? '<p class="help">' . e($def['help']) . '</p>' : '')
        . $switch . pb_field_inputs($def['fields'], $data, $name) . '</details>';
}

function pb_list_input(array $def, mixed $items, string $name): string
{
    foreach ($def['fields'] as $sub) {
        if (in_array($sub['type'], ['list', 'group'], true)) {
            throw new LogicException('List items cannot contain lists or groups.');
        }
    }
    $item = fn(array $data, string $index) => '<fieldset class="pb-item" data-item><legend>' . e($def['item_label'] ?? __('Item')) . '</legend>'
        . pb_field_inputs($def['fields'], $data, "{$name}[{$index}]")
        . '<div class="pb-item-actions">'
        . '<button type="button" class="link" data-up>' . e(__('Subir')) . '</button>'
        . '<button type="button" class="link" data-down>' . e(__('Descer')) . '</button>'
        . '<button type="button" class="link danger" data-remove>' . e(__('Remover')) . '</button>'
        . '</div></fieldset>';

    $html = '<fieldset class="pb-list" data-list><legend>' . e($def['label'] ?? '') . '</legend>'
        . (isset($def['help']) ? '<p class="help">' . e($def['help']) . '</p>' : '') . '<div data-items>';
    foreach (is_array($items) ? array_values($items) : [] as $i => $data) {
        $html .= $item((array) $data, (string) $i);
    }
    return $html . '</div><template>' . $item([], '__i__') . '</template>'
        . '<button type="button" class="secondary" data-add>+ ' . e($def['add_label'] ?? __('Adicionar item')) . '</button></fieldset>';
}

function pb_image_input(string $id, string $name, int $mediaId): string
{
    $media = $mediaId ? pb_media_find($mediaId) : null;
    return '<div class="pb-image" data-image>'
        . '<input type="hidden" id="' . e($id) . '" name="' . e($name) . '" value="' . ($media ? $mediaId : '') . '">'
        . '<div class="pb-image-preview" data-preview>' . ($media ? '<img src="' . e(pb_media_url($media, 'thumb')) . '" alt="">' : '') . '</div>'
        . '<button type="button" class="secondary" data-pick>' . e(__('Escolher imagem')) . '</button>'
        . '<button type="button" class="link danger" data-clear>' . e(__('Tirar imagem')) . '</button>'
        . '</div>';
}

function pb_link_input(string $id, string $name, string $value): string
{
    $options = '<option value="">' . e(__('Outro endereço…')) . '</option>';
    $listed = false;
    foreach (pb_page_list() as $page) {
        if ($page['translation_of'] !== null) {
            continue; // translations: a link to the original leads to them in their language
        }
        $key = 'page:' . $page['id'];
        $listed = $listed || $value === $key;
        $options .= '<option value="' . e($key) . '"' . ($value === $key ? ' selected' : '') . '>' . e($page['title']) . '</option>';
    }
    // Plugins can offer their own addresses here: pb_add_filter('link_targets', fn($t) => $t + ['/blog' => 'Blog']).
    foreach (pb_apply_filters('link_targets', []) as $url => $label) {
        $listed = $listed || $value === (string) $url;
        $options .= '<option value="' . e((string) $url) . '"' . ($value === (string) $url ? ' selected' : '') . '>' . e((string) $label) . '</option>';
    }
    return '<div class="pb-link" data-link>'
        . '<select id="' . e($id) . '" name="' . e($name) . '[page]">' . $options . '</select>'
        . '<input name="' . e($name) . '[url]" placeholder="https://" aria-label="' . e(__('Endereço')) . '" value="'
        . e($listed ? '' : $value) . '">'
        . '</div>';
}
