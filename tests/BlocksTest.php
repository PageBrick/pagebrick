<?php

use PHPUnit\Framework\TestCase;

/** The sections a client adds to a page (text, photo with text, cards...): kept, drawn as plain HTML, copied and put in any place. */
final class BlocksTest extends TestCase
{
    private int $admin;
    private ?string $tmp = null;

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config();
        pb_migrate();
        pb_set_option('site_title', 'Página inicial');
        $this->admin = pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
        pb_seed_demo();
        $GLOBALS['pb_organizer'] = false;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['pb_organizer'], $GLOBALS['pb_current_page']);
        $_SESSION = $_GET = $_POST = [];
        if ($this->tmp) {
            exec('rm -rf ' . escapeshellarg($this->tmp));
        }
    }

    private function page(string $slug): array
    {
        return pb_page_by_slug($slug) ?? throw new LogicException("No page $slug");
    }

    /** The form the panel sends for a page, with some changes. */
    private function form(array $page, array $changes = []): array
    {
        return array_replace_recursive(['id' => (string) $page['id'], 'title' => $page['title'], 'slug' => $page['slug'], 'seo_title' => '', 'seo_description' => '', 'f' => $page['data']], $changes);
    }

    /** Saves a page with these pieces (id => fields) and, when given, this order, and gives the page back. */
    private function withPieces(string $slug, array $pieces, ?array $order = null): array
    {
        $page = $this->page($slug);
        pb_page_publish($page['id'], $this->form($page, ['f' => ['_blocks' => $pieces] + ($order ? ['_order' => $order] : [])]), null);
        return pb_page_find($page['id']);
    }

    private function html(array $page): string
    {
        return pb_render_page($page);
    }

    private function admin(string $method, string $path): string
    {
        $_SESSION['user_id'] = $this->admin;
        ob_start();
        pb_admin($method, $path);
        return ob_get_clean();
    }

    // ------------------------------------------------------------------ the kinds

    public function test_there_are_nine_kinds_each_with_a_drawing_an_icon_and_plain_words(): void
    {
        $types = pb_block_types();
        $this->assertSame(['text', 'image_text', 'cards', 'faq', 'testimonials', 'cta', 'numbers', 'gallery', 'video'], array_keys($types));
        foreach ($types as $type => $definition) {
            $this->assertNotSame('', pb_admin_icon($definition['icon']), "$type has an icon");
            $this->assertStringContainsString('<svg', pb_block_sketch($type), "$type has a drawing");
            $this->assertNotSame('', $definition['help'], "$type says what it is");
            $this->assertFileExists(PB_ROOT . "/core/blocks/$type.php", "$type is drawn by the core without a theme");
            foreach ($definition['fields'] as $field) {
                $this->assertContains($field['type'], ['text', 'textarea', 'richtext', 'image', 'link', 'url', 'select', 'list'], "$type uses the standard fields");
            }
        }
        $this->assertStringNotContainsString('bloco', strtolower(json_encode(array_column($types, 'label'), JSON_UNESCAPED_UNICODE)), 'the panel says "section", not "block"');
    }

    public function test_the_default_theme_accepts_them_on_every_page_type_and_a_missing_type_is_a_simple_page(): void
    {
        foreach (['home', 'page', 'services', 'contact', 'a-type-this-theme-lacks'] as $template) {
            $this->assertCount(9, pb_template_blocks($template), $template);
        }
    }

    public function test_the_theme_says_which_kinds_it_draws(): void
    {
        $this->theme("'sections' => ['content'], 'blocks' => ['text', 'faq', 'evil']");
        $this->assertSame(['text', 'faq'], pb_template_blocks('page'), 'a list is a list, and nothing that is not a kind');
        $this->theme("'sections' => ['content']");
        $this->assertSame([], pb_template_blocks('page'), 'a theme that says nothing gets none');
    }

    /** A copy of the default theme in a temporary folder, whose theme.php defines the page type as this (what the theme says of its sections and pieces). */
    private function theme(string $pageDefinition, array $files = []): void
    {
        $this->tmp ??= sys_get_temp_dir() . '/pb-blocks-' . bin2hex(random_bytes(4));
        exec('rm -rf ' . escapeshellarg($this->tmp));
        pb_test_copy_dir(PB_ROOT . '/content/themes/default', "$this->tmp/default");
        file_put_contents("$this->tmp/default/theme.php", "<?php return ['templates' => ['page' => [$pageDefinition]]];");
        foreach ($files as $path => $content) {
            @mkdir(dirname("$this->tmp/default/$path"), 0777, true);
            file_put_contents("$this->tmp/default/$path", $content);
        }
        $GLOBALS['pb_config']['themes_dir'] = $this->tmp;
        unset($GLOBALS['pb_theme']);
    }

    // ------------------------------------------------------------------ what is kept

    public function test_a_page_keeps_its_pieces_in_the_order_the_form_sent_and_cleans_each_one(): void
    {
        $about = $this->withPieces('sobre', [
            'aaa111' => ['_type' => 'text', '_visible' => '1', 'title' => 'Nossa missão', 'text' => '<p>Olá</p><script>alert(1)</script>'],
            'bbb222' => ['_type' => 'cta', '_visible' => '0', 'title' => 'Fale', 'text' => 'Agora', 'button_label' => 'Ir', 'button_link' => ['page' => '', 'url' => 'javascript:alert(1)']],
            'ccc333' => ['_type' => 'nope', 'title' => 'x'],
            'dd' => ['_type' => 'text', 'title' => 'id curto demais'],
            'EVIL"' => ['_type' => 'text', 'title' => 'id perigoso'],
        ], ['block-bbb222', 'content', 'block-aaa111', 'block-nao-existe']);

        $this->assertSame(['aaa111', 'bbb222'], array_keys($about['data']['_blocks']), 'only real kinds, with ids that are safe in a form and a class');
        $this->assertSame('<p>Olá</p>', $about['data']['_blocks']['aaa111']['text'], 'the text is cleaned like any other text of the site');
        $this->assertFalse($about['data']['_blocks']['bbb222']['_visible'], 'a piece switched off stays, switched off');
        $this->assertSame('', $about['data']['_blocks']['bbb222']['button_link'], 'and its links are cleaned too');
        $this->assertSame(['block-bbb222', 'content', 'block-aaa111'], $about['data']['_order']);
    }

    public function test_removing_the_last_piece_leaves_the_page_as_it_was_before_pieces(): void
    {
        $about = $this->withPieces('sobre', ['aaa111' => ['_type' => 'text', 'title' => 'x']]);
        $this->assertArrayHasKey('_blocks', $about['data']);
        $form = $this->form($about);
        unset($form['f']['_blocks'], $form['f']['_order']);
        pb_page_publish($about['id'], $form, null);
        $about = pb_page_find($about['id']);
        $this->assertArrayNotHasKey('_blocks', $about['data']);
        $this->assertArrayNotHasKey('_order', $about['data'], 'an order is kept only when the client chose one');
    }

    public function test_a_piece_added_at_the_end_is_in_the_themes_order_and_stores_no_order(): void
    {
        $about = $this->withPieces('sobre', ['aaa111' => ['_type' => 'text', 'title' => 'x']], ['content', 'block-aaa111']);
        $this->assertArrayNotHasKey('_order', $about['data']);
        $this->assertSame(['content', 'block-aaa111'], array_keys(pb_page_sections($about)), 'it comes after the theme\'s sections');
    }

    public function test_what_the_theme_no_longer_draws_is_kept_for_when_it_comes_back(): void
    {
        $about = $this->withPieces('sobre', ['aaa111' => ['_type' => 'text', 'title' => 'Guardado']]);
        $this->theme("'sections' => ['content']");
        $this->assertSame([], pb_page_blocks($about['data'], 'page'), 'a theme without pieces draws none');
        $page = pb_page_from_input($about, $this->form($about, ['title' => 'Sobre nós']));
        $this->assertSame('Guardado', $page['data']['_blocks']['aaa111']['title'], 'saving the page does not lose them');
    }

    // ------------------------------------------------------------------ what visitors get

    public function test_the_pieces_are_printed_in_the_order_chosen_among_the_sections_and_a_hidden_one_is_not(): void
    {
        $about = $this->withPieces('sobre', [
            'aaa111' => ['_type' => 'text', 'title' => 'Primeira seção', 'text' => '<p>Texto um</p>'],
            'bbb222' => ['_type' => 'text', 'title' => 'Escondida', '_visible' => '0'],
            'ccc333' => ['_type' => 'numbers', 'title' => 'Em números', 'items' => [['value' => '15', 'label' => 'anos']]],
        ], ['block-ccc333', 'content', 'block-aaa111', 'block-bbb222']);
        $html = $this->html($about);

        $this->assertLessThan(strpos($html, 'Sobre</h1>'), strpos($html, 'Em números'), 'a piece can come before the page\'s own content');
        $this->assertLessThan(strpos($html, 'Primeira seção'), strpos($html, 'Sobre</h1>'));
        $this->assertStringNotContainsString('Escondida', $html);
        $this->assertStringContainsString('<ul class="pb-numbers">', $html);
        $this->assertStringContainsString('<strong>15</strong>', $html);
    }

    public function test_the_core_draws_plain_html_with_no_style_and_no_script(): void
    {
        $about = $this->withPieces('sobre', [
            'a00001' => ['_type' => 'text', 'title' => 'T', 'text' => '<p>x</p>'],
            'a00002' => ['_type' => 'image_text', 'title' => 'T', 'text' => '<p>x</p>', 'image' => '1', 'side' => 'left'],
            'a00003' => ['_type' => 'cards', 'title' => 'T', 'intro' => 'i', 'items' => [['title' => 'C', 'text' => 't', 'image' => '2', 'link' => ['page' => 'page:' . $this->page('contato')['id'], 'url' => '']]], 'link_label' => 'Mais', 'link' => ['page' => '', 'url' => 'https://exemplo.com']],
            'a00004' => ['_type' => 'faq', 'title' => 'T', 'items' => [['question' => 'Q?', 'answer' => '<p>R</p>']]],
            'a00005' => ['_type' => 'testimonials', 'title' => 'T', 'items' => [['quote' => 'Ótimo', 'name' => 'Maria', 'role' => 'Padaria']]],
            'a00006' => ['_type' => 'cta', 'title' => 'T', 'text' => 'x', 'button_label' => 'Vamos', 'button_link' => ['page' => '', 'url' => 'https://exemplo.com/a']],
            'a00007' => ['_type' => 'numbers', 'title' => 'T', 'items' => [['value' => '1', 'label' => 'l']]],
            'a00008' => ['_type' => 'gallery', 'title' => 'T', 'items' => [['image' => '3', 'caption' => 'Legenda']]],
            'a00009' => ['_type' => 'video', 'title' => 'T', 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'caption' => 'Clipe'],
        ]);
        $html = $this->html($about);
        foreach (['pb-block-text', 'pb-block-image_text pb-side-left', 'pb-block-cards', 'pb-block-faq', 'pb-block-testimonials', 'pb-block-cta', 'pb-block-numbers', 'pb-block-gallery', 'pb-block-video'] as $class) {
            $this->assertStringContainsString("class=\"pb-block $class", $html, $class);
        }
        $pieces = implode('', array_map(fn($file) => (string) file_get_contents($file), glob(PB_ROOT . '/core/blocks/*.php')));
        $this->assertStringNotContainsString('style=', $pieces, 'the core decides no look');
        $this->assertStringNotContainsString('<style', $pieces);
        $this->assertStringNotContainsString('<script', $pieces);
        $this->assertStringContainsString('<details class="pb-faq-item">', $html, 'the browser\'s own accordion: no script');
        $this->assertStringContainsString('<a href="/contato">C</a>', $html, 'a card can lead to a page of the site');
        $this->assertMatchesRegularExpression('~<figcaption>\s*<strong>Maria</strong>\s*<span>Padaria</span>\s*</figcaption>~', $html);
    }

    public function test_the_video_is_only_ever_a_youtube_video_by_its_id(): void
    {
        $id = 'dQw4w9WgXcQ';
        foreach (["https://www.youtube.com/watch?v=$id", "https://youtu.be/$id", "https://youtube.com/embed/$id", "https://m.youtube.com/shorts/$id?feature=share", "https://www.youtube-nocookie.com/embed/$id"] as $url) {
            $this->assertSame($id, pb_youtube_id($url), $url);
        }
        foreach (['', 'dQw4w9WgXcQ', 'https://evil.example/watch?v=' . $id, 'https://youtube.com.evil.example/watch?v=' . $id, 'javascript:alert(1)', 'https://youtu.be/short', "https://youtu.be/$id\"onload=\"x", 'https://www.youtube.com/watch?v=' . $id . 'x'] as $url) {
            $this->assertSame('', pb_youtube_id($url), $url);
        }
        $about = $this->withPieces('sobre', ['aaa111' => ['_type' => 'video', 'title' => 'Vídeo', 'url' => "https://www.youtube.com/watch?v=$id"], 'bbb222' => ['_type' => 'video', 'url' => 'https://evil.example/x']]);
        $html = $this->html($about);
        $this->assertStringContainsString("https://www.youtube-nocookie.com/embed/$id", $html);
        $this->assertSame(1, substr_count($html, '<iframe'), 'an address that is not YouTube draws nothing');
    }

    public function test_a_theme_draws_a_piece_its_own_way_with_a_file_of_the_same_name(): void
    {
        $this->theme("'sections' => ['content'], 'blocks' => true", ['templates/blocks/text.php' => '<aside class="mine"><?= $block->title ?> / <?= $type ?> / <?= $id ?></aside>']);
        $about = $this->withPieces('sobre', ['aaa111' => ['_type' => 'text', 'title' => 'Meu título'], 'bbb222' => ['_type' => 'cta', 'title' => 'Chamada']]);
        $html = $this->html($about);
        $this->assertStringContainsString('<aside class="mine">Meu título / text / aaa111</aside>', $html);
        $this->assertStringContainsString('pb-block-cta', $html, 'a kind the theme did not draw comes from the core');
    }

    public function test_a_theme_that_does_not_ask_for_pieces_shows_none(): void
    {
        $about = $this->withPieces('sobre', ['aaa111' => ['_type' => 'text', 'title' => 'Não aparece']]);
        $this->theme("'sections' => ['content']");
        $this->assertStringNotContainsString('Não aparece', $this->html($about));
    }

    public function test_the_editor_gets_each_piece_marked_so_it_can_be_picked_and_dragged_on_the_page(): void
    {
        $about = $this->withPieces('sobre', ['aaa111' => ['_type' => 'text', 'title' => 'Marcada']]);
        $GLOBALS['pb_organizer'] = true;
        $html = $this->html($about);
        $this->assertStringContainsString('<!--pb-section:block-aaa111-->', $html);
        $this->assertStringContainsString('<!--/pb-section:block-aaa111-->', $html);
        $GLOBALS['pb_organizer'] = false;
        $this->assertStringNotContainsString('pb-section', $this->html($about), 'visitors get no marks');
    }

    public function test_a_group_gives_a_piece_its_fields_and_puts_the_pieces_after_the_sections_it_has(): void
    {
        $about = $this->withPieces('sobre', ['aaa111' => ['_type' => 'text', 'title' => 'x'], 'bbb222' => ['_type' => 'faq', 'title' => 'y']], ['block-bbb222', 'content']);
        $_SERVER['REQUEST_URI'] = '/sobre';
        $GLOBALS['pb_current_page'] = $about + ['preview' => false];
        $group = new PbGroup(pb_template_fields('page'), $about['data']);
        $this->assertSame(['block-bbb222', 'content', 'block-aaa111'], $group->order(['content']));
        $piece = $group->piece('block-bbb222');
        $this->assertSame('faq', $piece->type());
        $this->assertSame('y', (string) $piece->title);
        $this->assertNull($group->piece('block-gone'));
        $this->assertSame(['content'], (new PbGroup([], []))->order(['content']), 'a page with none is as before');
    }

    // ------------------------------------------------------------------ the panel: add, copy, remove

    public function test_adding_a_section_answers_with_the_row_to_put_in_the_list(): void
    {
        $about = $this->page('sobre');
        $answer = pb_piece_response(['id' => (string) $about['id'], 'op' => 'add', 'type' => 'faq', 'f' => $about['data']]);
        $this->assertTrue($answer['ok']);
        $this->assertSame('block-' . $answer['id'], $answer['name']);
        $this->assertSame('Perguntas e respostas', $answer['label']);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{6}$/', $answer['id']);
        foreach (['data-section="' . $answer['name'] . '"', 'data-sort=', 'data-grip', 'data-duplicate', 'data-remove-piece', 'data-insert-before', ' open', 'name="f[_order][]" value="' . $answer['name'] . '"',
            'name="f[_blocks][' . $answer['id'] . '][_type]" value="faq"', 'name="f[_blocks][' . $answer['id'] . '][items][__i__][question]"', 'data-visible-switch'] as $part) {
            $this->assertStringContainsString($part, $answer['html'], $part);
        }
        $this->assertSame(1, substr_count($answer['html'], '<div class="pb-section pb-piece"'), 'one row');
    }

    public function test_a_new_section_cannot_be_a_kind_the_theme_does_not_draw_nor_an_unknown_one(): void
    {
        $about = $this->page('sobre');
        $this->assertFalse(pb_piece_response(['id' => (string) $about['id'], 'op' => 'add', 'type' => 'evil'])['ok']);
        $this->assertFalse(pb_piece_response(['id' => '99999', 'op' => 'add', 'type' => 'text'])['ok']);
        $this->theme("'sections' => ['content'], 'blocks' => ['text']");
        $this->assertFalse(pb_piece_response(['id' => (string) $about['id'], 'op' => 'add', 'type' => 'faq'])['ok']);
        $this->assertTrue(pb_piece_response(['id' => (string) $about['id'], 'op' => 'add', 'type' => 'text'])['ok']);
    }

    public function test_a_page_cannot_get_more_than_a_hundred_sections(): void
    {
        $about = $this->page('sobre');
        $many = [];
        for ($i = 0; $i < PB_BLOCK_LIMIT; $i++) {
            $many[sprintf('a%05d', $i)] = ['_type' => 'text'];
        }
        $answer = pb_piece_response(['id' => (string) $about['id'], 'op' => 'add', 'type' => 'text', 'f' => ['_blocks' => $many]]);
        $this->assertFalse($answer['ok']);
        $this->assertNotSame('', $answer['error']);
    }

    public function test_duplicating_a_piece_copies_what_is_in_the_form_now_with_a_new_name(): void
    {
        $about = $this->page('sobre');
        $answer = pb_piece_response(['id' => (string) $about['id'], 'op' => 'duplicate', 'section' => 'block-aaa111', 'f' => [
            '_blocks' => ['aaa111' => ['_type' => 'text', 'title' => 'Ainda não salvo', 'text' => '<p>Texto <strong>digitado</strong></p>']],
        ]]);
        $this->assertTrue($answer['ok']);
        $this->assertNotSame('block-aaa111', $answer['name']);
        $this->assertStringContainsString('value="Ainda não salvo"', $answer['html']);
        $this->assertStringContainsString('digitado', $answer['html']);
        $this->assertFalse(pb_piece_response(['id' => (string) $about['id'], 'op' => 'duplicate', 'section' => 'block-gone', 'f' => []])['ok'], 'a piece that is not there cannot be copied');
    }

    public function test_duplicating_a_theme_section_makes_the_piece_the_theme_says_it_is(): void
    {
        $home = pb_page_find(pb_home_page_id());
        $about = $home['data']['about'];
        $answer = pb_piece_response(['id' => (string) $home['id'], 'op' => 'duplicate', 'section' => 'about', 'f' => ['about' => ['title' => 'Título mudado agora', 'text' => $about['text'], 'image' => (string) $about['image']]]]);
        $this->assertTrue($answer['ok']);
        $this->assertSame('image_text', preg_match('/data-piece="([a-z_]+)"/', $answer['html'], $m) ? $m[1] : null, 'the "Quem somos" section is a photo with text');
        $this->assertStringContainsString('value="Título mudado agora"', $answer['html'], 'it is what the form has now');
        $this->assertStringContainsString('name="f[_blocks][' . $answer['id'] . '][image]" value="' . $about['image'] . '"', $answer['html'], 'with the same photo');

        $services = $home['data']['services'];
        $copy = pb_piece_response(['id' => (string) $home['id'], 'op' => 'duplicate', 'section' => 'services', 'f' => ['services' => ['title' => 'Serviços', 'items' => [['title' => 'Um', 'text' => 'a', 'image' => ''], ['title' => 'Dois', 'text' => 'b', 'image' => '']]]]]);
        $this->assertTrue($copy['ok']);
        $this->assertStringContainsString('data-piece="cards"', $copy['html']);
        $this->assertStringContainsString('][items][0][title]" maxlength="500" value="Um"', $copy['html']);
        $this->assertStringContainsString('][items][1][title]" maxlength="500" value="Dois"', $copy['html']);
        unset($services);

        $this->assertFalse(pb_piece_response(['id' => (string) $home['id'], 'op' => 'duplicate', 'section' => 'hero', 'f' => ['hero' => ['title' => 'x']]])['ok'], 'a section with no equal kind is not copied');
    }

    public function test_the_list_of_the_panel_has_the_pieces_in_order_and_the_buttons_to_add(): void
    {
        $about = $this->withPieces('sobre', ['aaa111' => ['_type' => 'text', 'title' => 'Minha seção']], ['block-aaa111', 'content']);
        $_GET = ['id' => (string) $about['id']];
        $html = $this->admin('GET', '/admin/pages/edit');
        $this->assertLessThan(strpos($html, 'pb-section-fixed" data-section="content"'), strpos($html, 'data-section="block-aaa111"'), 'the piece comes first in the list, as chosen');
        foreach (['data-open-gallery', 'data-gallery', 'data-add-piece="text"', 'data-add-piece="video"', 'data-sections-empty', 'data-insert-before', 'data-piece-title', 'Adicionar seção',
            'Que seção você quer adicionar?', 'value="Minha seção"', 'data-section-url'] as $part) {
            $this->assertStringContainsString($part, $html, $part);
        }
        $this->assertSame(9, substr_count($html, 'data-add-piece='), 'the gallery has every kind');
        $this->assertStringContainsString('data-sections-empty hidden', $html, 'with sections on the list there is no empty note');
    }

    public function test_a_page_without_any_section_says_so_and_offers_to_start(): void
    {
        $this->theme("'blocks' => true"); // pieces, and no section of the theme's own
        $_GET = ['id' => (string) $this->page('sobre')['id']];
        $html = $this->admin('GET', '/admin/pages/edit');
        $this->assertStringContainsString('data-open-gallery', $html);
        $this->assertStringContainsString('Esta página ainda não tem seções. Escolha uma para começar.', $html);
        $this->assertStringNotContainsString('data-sections-empty hidden', $html);
    }

    public function test_the_preview_tells_the_page_where_a_section_can_be_added(): void
    {
        $about = $this->withPieces('sobre', ['aaa111' => ['_type' => 'text', 'title' => 'x']]);
        $_POST = $this->form($about, ['organizer' => '1']);
        $html = $this->admin('POST', '/admin/pages/preview');
        $this->assertStringContainsString('"canAdd":true', $html);
        $this->assertStringContainsString('"movable":["content","block-aaa111"]', $html);
        $this->assertStringContainsString('<!--pb-section:block-aaa111-->', $html);
        $this->theme("'sections' => ['content'], 'blocks' => false");
        $this->assertStringContainsString('"canAdd":false', $this->admin('POST', '/admin/pages/preview'));
    }

    // ------------------------------------------------------------------ headless

    public function test_the_content_api_lists_the_pieces_on_the_site_in_their_order(): void
    {
        $about = $this->withPieces('sobre', [
            'aaa111' => ['_type' => 'text', 'title' => 'A', 'text' => '<p>t</p>'],
            'bbb222' => ['_type' => 'cta', 'title' => 'B', '_visible' => '0'],
            'ccc333' => ['_type' => 'numbers', 'title' => 'C', 'items' => [['value' => '9', 'label' => 'x']]],
        ], ['block-ccc333', 'content', 'block-aaa111', 'block-bbb222']);
        $api = pb_content_page($about);
        $this->assertSame(['block-ccc333', 'block-aaa111'], array_column($api['blocks'], 'id'), 'the visible ones, in order');
        $this->assertSame(['numbers', 'text'], array_column($api['blocks'], 'type'));
        $this->assertSame('9', $api['blocks'][0]['fields']['items'][0]['value']);
        $this->assertSame(['block-ccc333', 'content', 'block-aaa111', 'block-bbb222'], $api['section_order']);

        $plain = pb_content_page($this->page('contato'));
        $this->assertArrayNotHasKey('blocks', $plain, 'a page with none is answered as before');
    }
}
