<?php

use PHPUnit\Framework\TestCase;

/** The page editor's promises: changes wait in a draft until Publish, and the sections of a page can be put in any order. */
final class OrganizerTest extends TestCase
{
    private const HOME_SECTIONS = ['hero', 'about', 'services', 'numbers', 'testimonials', 'plugins', 'cta'];

    private int $admin;

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
        unset($GLOBALS['pb_organizer']);
        $_SESSION = $_GET = $_POST = [];
    }

    private function page(string $slug): array
    {
        return pb_page_by_slug($slug) ?? throw new LogicException("No page $slug");
    }

    /** The form the panel would send for a page, with some changes. */
    private function form(array $page, array $changes = []): array
    {
        return array_replace_recursive([
            'title' => $page['title'], 'slug' => $page['slug'], 'seo_title' => $page['seo_title'], 'seo_description' => $page['seo_description'], 'f' => $page['data'],
        ], $changes);
    }

    private function revisions(int $id): int
    {
        return count(pb_page_revisions($id));
    }

    private function home(): array
    {
        return pb_page_find(pb_home_page_id());
    }

    /** Where each marked section starts in the page's HTML, in order. */
    private function renderedOrder(array $page): array
    {
        $GLOBALS['pb_organizer'] = true;
        $html = pb_render_page($page, true);
        $GLOBALS['pb_organizer'] = false;
        preg_match_all('/<!--pb-section:([a-z]+)-->/', $html, $m);
        return $m[1];
    }

    // ------------------------------------------------------------------ the order of the sections

    public function test_the_default_theme_lets_the_client_order_the_sections_of_its_pages(): void
    {
        $this->assertSame(self::HOME_SECTIONS, pb_template_sections('home'));
        $this->assertSame(['content'], pb_template_sections('page'), 'a simple page has its own content, and what the client adds around it');
        $this->assertSame(['content', 'cta'], pb_template_sections('services'));
        $this->assertSame(['content'], pb_template_sections('a-template-that-does-not-exist'), 'a type the theme lacks is a simple page');
    }

    public function test_an_order_is_cleaned_to_the_sections_of_the_template(): void
    {
        $this->assertSame(['cta', 'hero', 'about', 'services', 'numbers', 'testimonials', 'plugins'],
            pb_clean_section_order(['cta', 'hero', 'hero', 'evil', ['x'], 'about'], self::HOME_SECTIONS));
        $this->assertSame(self::HOME_SECTIONS, pb_clean_section_order('not a list', self::HOME_SECTIONS));
        $this->assertSame(self::HOME_SECTIONS, pb_clean_section_order(null, self::HOME_SECTIONS));
    }

    public function test_a_group_gives_its_names_in_the_clients_order_with_the_new_ones_last(): void
    {
        $group = new PbGroup([], ['_order' => ['cta', 'gone', 'hero']]);
        $this->assertSame(['cta', 'hero', 'about', 'services'], $group->order(['hero', 'about', 'services', 'cta']));
        $this->assertSame(['hero', 'about'], (new PbGroup([], []))->order(['hero', 'about']), 'no order saved: the theme\'s own');
    }

    public function test_the_form_sends_the_order_and_a_page_left_in_the_themes_order_stores_none(): void
    {
        $home = $this->home();
        $page = pb_page_from_input($home, $this->form($home, ['f' => ['_order' => ['cta', 'hero']]]));
        $this->assertSame(['cta', 'hero', 'about', 'services', 'numbers', 'testimonials', 'plugins'], $page['data']['_order']);

        $about = $this->page('sobre');
        $page = pb_page_from_input($about, $this->form($about, ['f' => ['_order' => ['body', 'intro']]]));
        $this->assertArrayNotHasKey('_order', $page['data'], 'a page left in the order of its theme keeps no order');
        $page = pb_page_from_input($home, $this->form($home, ['f' => ['_order' => self::HOME_SECTIONS]]));
        $this->assertArrayNotHasKey('_order', $page['data'], 'and the order of the theme itself is not an order the client chose');
    }

    public function test_the_home_page_is_printed_in_the_chosen_order_and_marked_only_for_the_editor(): void
    {
        $home = $this->home();
        $this->assertSame(self::HOME_SECTIONS, $this->renderedOrder($home), 'with no order chosen: the theme\'s own');

        pb_page_publish($home['id'], $this->form($home, ['f' => ['_order' => ['cta', 'services', 'hero']]]), null);
        $this->assertSame(['cta', 'services', 'hero', 'about', 'numbers', 'testimonials', 'plugins'], $this->renderedOrder($this->home()));

        $html = pb_render_page($this->home());
        $this->assertStringNotContainsString('pb-section', $html, 'visitors get no marks');
    }

    public function test_a_site_that_never_chose_an_order_gets_the_same_html_as_before(): void
    {
        $home = $this->home();
        $plain = pb_render_page($home);
        $withDefault = pb_render_page(['data' => ['_order' => self::HOME_SECTIONS] + $home['data']] + $home);
        $this->assertSame($plain, $withDefault, 'saving the theme\'s own order changes nothing');
        $this->assertStringContainsString('<h1', $plain);
    }

    public function test_the_content_api_tells_the_order_only_when_one_was_chosen(): void
    {
        $home = $this->home();
        $this->assertArrayNotHasKey('section_order', pb_content_page($home));
        pb_page_publish($home['id'], $this->form($home, ['f' => ['_order' => ['cta', 'hero']]]), null);
        $this->assertSame(['cta', 'hero', 'about', 'services', 'numbers', 'testimonials', 'plugins'], pb_content_page($this->home())['section_order']);
    }

    public function test_pb_section_marks_a_section_only_while_the_client_edits(): void
    {
        $this->assertSame('<p>x</p>', pb_section('about', '<p>x</p>'));
        $GLOBALS['pb_organizer'] = true;
        $this->assertSame('<!--pb-section:about--><p>x</p><!--/pb-section:about-->', pb_section('about', '<p>x</p>'));
        $this->assertSame('<!--pb-section:ab--><!--/pb-section:ab-->', pb_section('a"b>', ''), 'a name cannot close the comment');
    }

    public function test_the_panel_lists_the_sections_in_the_pages_order_and_plugins_only_when_one_uses_the_place(): void
    {
        $home = $this->home();
        $this->assertSame(['hero', 'about', 'services', 'numbers', 'testimonials', 'cta'], array_keys(pb_page_sections($home)), 'no plugin uses the home page: no "plugins" row');
        $this->assertSame('Destaque (topo da página)', pb_page_sections($home)['hero']['label']);
        pb_add_filter('slot:home', fn(string $html) => $html . 'news');
        $this->assertSame(self::HOME_SECTIONS, array_keys(pb_page_sections($home)));
        $this->assertSame('Conteúdo de plugins', pb_page_sections($home)['plugins']['label']);
        $this->assertNull(pb_page_sections($home)['plugins']['group']);
        unset($GLOBALS['pb_hooks']['slot:home']);
    }

    // ------------------------------------------------------------------ the screens

    private function admin(string $method, string $path): string
    {
        $_SESSION['user_id'] = $this->admin;
        ob_start();
        pb_admin($method, $path);
        return ob_get_clean();
    }

    public function test_the_editor_screen_has_the_form_the_sections_and_the_page_beside_them(): void
    {
        $_GET = ['id' => (string) pb_home_page_id()];
        $html = $this->admin('GET', '/admin/pages/edit');
        foreach (['data-studio', 'data-sections', 'data-section="hero"', 'data-section="cta"', 'data-group="hero"', 'name="f[_order][]"', 'data-frame="a"', 'data-frame="b"',
            'data-publish', 'name="action" value="draft"', 'studio.js', 'studio.css'] as $part) {
            $this->assertStringContainsString($part, $html, $part);
        }
        $this->assertSame(1, substr_count($html, 'data-section="hero"'), 'every section once');
        $this->assertDoesNotMatchRegularExpression('~<details class="pb-section-box"[^>]* open~', $html, 'the rows start closed');

        $_GET = ['id' => (string) $this->page('sobre')['id']];
        $about = $this->admin('GET', '/admin/pages/edit');
        $this->assertStringContainsString('data-studio', $about, 'every page gets the page beside the form');
        $this->assertStringContainsString('data-sections', $about, 'and, in this theme, sections to add and move');
    }

    public function test_the_editor_opens_on_the_draft_and_says_so(): void
    {
        $about = $this->page('sobre');
        pb_page_save_draft($about['id'], $this->form($about, ['f' => ['intro' => 'Texto do rascunho']]), null);
        $_GET = ['id' => (string) $about['id']];
        $html = $this->admin('GET', '/admin/pages/edit');
        $this->assertStringContainsString('Texto do rascunho', $html);
        $this->assertStringContainsString('data-state="changes"', $html);
        $this->assertStringContainsString('Publicada · com alterações não publicadas', $html);
        $this->assertMatchesRegularExpression('~data-discard[^>]*>~', $html);
        $this->assertDoesNotMatchRegularExpression('~data-discard[^>]* hidden~', $html, '"discard" is offered');
    }

    public function test_the_preview_marks_the_sections_and_brings_the_pages_script_only_when_asked(): void
    {
        $home = $this->home();
        $_POST = $this->form($home, ['id' => (string) $home['id'], 'f' => ['_order' => ['cta', 'hero']]]);
        $plain = $this->admin('POST', '/admin/pages/preview');
        $this->assertStringNotContainsString('pb-section', $plain, 'the old preview (another tab) is the page as it is');
        $this->assertStringNotContainsString('PB_FRAME', $plain);

        $_POST['organizer'] = '1';
        $html = $this->admin('POST', '/admin/pages/preview');
        $this->assertLessThan(strpos($html, '<!--pb-section:hero-->'), strpos($html, '<!--pb-section:cta-->'), 'it shows the order that was typed, not the saved one');
        $this->assertStringContainsString('window.PB_FRAME', $html);
        $this->assertStringContainsString('"movable":["cta","hero"', $html);
        $this->assertStringContainsString('"hero":"Destaque (topo da página)"', $html);
        $this->assertLessThan(strpos($html, 'PB_FRAME'), strrpos($html, '</main>'), 'the script comes at the end of the page');
        $this->assertSame(self::HOME_SECTIONS, $this->renderedOrder($this->home()), 'and nothing was saved by looking');
    }

    // ------------------------------------------------------------------ the draft

    public function test_a_published_page_keeps_what_visitors_see_while_changes_wait_in_the_draft(): void
    {
        $about = $this->page('sobre');
        $before = $this->revisions($about['id']);
        $this->assertSame('draft', pb_page_save_draft($about['id'], $this->form($about, ['f' => ['intro' => 'Texto novo, ainda não publicado']]), null));

        $live = pb_page_find($about['id']);
        $this->assertSame($about['data']['intro'], $live['data']['intro'], 'the page visitors see did not change');
        $this->assertStringNotContainsString('ainda não publicado', pb_render_page($live));
        $this->assertSame($before, $this->revisions($about['id']), 'a draft is not a version of the history');

        $working = pb_page_working_copy($live);
        $this->assertTrue($working['has_draft']);
        $this->assertSame('Texto novo, ainda não publicado', $working['data']['intro'], 'the editor opens on the draft');
        $this->assertStringContainsString('ainda não publicado', pb_render_page($working, true), 'and the preview shows it');
        $this->assertTrue((bool) array_column(pb_page_list(), 'has_draft', 'id')[$about['id']], 'the list says which pages have changes waiting');
    }

    public function test_publishing_puts_the_draft_on_the_site_keeps_the_old_version_and_clears_the_draft(): void
    {
        $about = $this->page('sobre');
        pb_page_save_draft($about['id'], $this->form($about, ['title' => 'Quem somos', 'f' => ['intro' => 'Versão nova']]), null);
        $before = $this->revisions($about['id']);

        pb_page_publish($about['id'], $this->form($about, ['title' => 'Quem somos', 'f' => ['intro' => 'Versão nova']]), null);
        $live = pb_page_find($about['id']);
        $this->assertSame('Versão nova', $live['data']['intro']);
        $this->assertSame('Quem somos', $live['title']);
        $this->assertNull($live['draft']);
        $this->assertSame($before + 1, $this->revisions($about['id']), 'the version that was on the site went to the history');
        $this->assertFalse(pb_page_working_copy($live)['has_draft']);
    }

    public function test_publishing_takes_the_form_as_it_is_even_if_the_draft_was_never_saved(): void
    {
        $about = $this->page('sobre');
        pb_page_publish($about['id'], $this->form($about, ['f' => ['intro' => 'Direto para o site']]), null);
        $this->assertSame('Direto para o site', pb_page_find($about['id'])['data']['intro']);
    }

    public function test_a_draft_equal_to_the_live_page_is_no_draft(): void
    {
        $about = $this->page('sobre');
        pb_page_save_draft($about['id'], $this->form($about, ['f' => ['intro' => 'Outra']]), null);
        $this->assertNotNull(pb_page_find($about['id'])['draft']);
        // a form sends every value as text, the picture's id included
        pb_page_save_draft($about['id'], $this->form($about, ['f' => ['image' => (string) $about['data']['image']]]), null);
        $this->assertNull(pb_page_find($about['id'])['draft'], 'put back as it was: nothing waits');
    }

    public function test_the_draft_of_a_changed_order_and_discarding_it(): void
    {
        $home = $this->home();
        pb_page_save_draft($home['id'], $this->form($home, ['f' => ['_order' => ['services', 'hero']]]), null);
        $this->assertSame(self::HOME_SECTIONS, $this->renderedOrder($this->home()), 'visitors keep the old order');
        $this->assertSame('services', array_key_first(pb_page_sections(pb_page_working_copy($this->home()))));

        pb_page_discard_draft($home['id']);
        $this->assertNull($this->home()['draft']);
        $this->assertSame('hero', array_key_first(pb_page_sections(pb_page_working_copy($this->home()))));
    }

    public function test_a_page_that_was_never_published_is_simply_saved(): void
    {
        $id = pb_page_create('Rascunho', 'page', ['intro' => 'Primeira'], 'draft');
        $page = pb_page_find($id);
        $this->assertSame('page', pb_page_save_draft($id, $this->form($page, ['f' => ['intro' => 'Segunda']]), null, true));
        $page = pb_page_find($id);
        $this->assertSame('Segunda', $page['data']['intro']);
        $this->assertNull($page['draft'], 'nothing public to protect: no separate draft');
        $this->assertSame('draft', $page['status']);
        $this->assertSame(0, $this->revisions($id), 'the automatic save keeps no version');

        pb_page_save_draft($id, $this->form($page, ['f' => ['intro' => 'Terceira']]), null);
        $this->assertSame(1, $this->revisions($id), 'the button does');
        pb_page_publish($id, $this->form(pb_page_find($id)), null);
        $this->assertSame('published', pb_page_find($id)['status']);
    }

    public function test_a_title_that_cannot_be_saved_yet_is_refused_and_leaves_the_draft_as_it_was(): void
    {
        $about = $this->page('sobre');
        pb_page_save_draft($about['id'], $this->form($about, ['f' => ['intro' => 'Primeiro rascunho']]), null);
        try {
            pb_page_save_draft($about['id'], $this->form($about, ['title' => '']), null, true);
            $this->fail('an empty title was accepted');
        } catch (InvalidArgumentException) {
        }
        $this->assertSame('Primeiro rascunho', pb_page_working_copy(pb_page_find($about['id']))['data']['intro']);
    }

    public function test_taking_a_page_off_the_site_keeps_what_was_being_edited_and_the_home_cannot_go(): void
    {
        $about = $this->page('sobre');
        pb_page_save_draft($about['id'], $this->form($about, ['f' => ['intro' => 'Ainda não estava no ar']]), null);
        pb_page_unpublish($about['id'], null);
        $page = pb_page_find($about['id']);
        $this->assertSame('draft', $page['status']);
        $this->assertSame('Ainda não estava no ar', $page['data']['intro']);
        $this->assertNull($page['draft']);

        $this->expectException(InvalidArgumentException::class);
        pb_page_unpublish(pb_home_page_id(), null);
    }

    public function test_restoring_a_version_throws_the_draft_away(): void
    {
        $about = $this->page('sobre');
        pb_page_publish($about['id'], $this->form($about, ['f' => ['intro' => 'Segunda versão']]), null);
        pb_page_save_draft($about['id'], $this->form($about, ['f' => ['intro' => 'Rascunho em cima']]), null);
        $this->assertNotNull(pb_page_find($about['id'])['draft']);
        pb_page_restore(pb_page_revisions($about['id'])[0]['id'], null);
        $page = pb_page_find($about['id']);
        $this->assertNull($page['draft']);
        $this->assertSame($about['data']['intro'], $page['data']['intro']);
    }

    public function test_a_draft_does_not_reach_the_public_routes_or_the_content_api(): void
    {
        $about = $this->page('sobre');
        pb_page_save_draft($about['id'], $this->form($about, ['f' => ['intro' => 'SEGREDO-DO-RASCUNHO']]), null);
        $_SERVER['REQUEST_URI'] = '/sobre';
        ob_start();
        pb_public('GET', '/sobre');
        $html = ob_get_clean();
        $this->assertStringNotContainsString('SEGREDO-DO-RASCUNHO', $html);
        $this->assertStringNotContainsString('SEGREDO-DO-RASCUNHO', json_encode(pb_content_page(pb_page_find($about['id']))));
    }
}
