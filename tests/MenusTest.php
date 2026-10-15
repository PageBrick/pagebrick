<?php

use PHPUnit\Framework\TestCase;

/** The menus screen's promises: what is typed waits in a draft until Publish, and the site beside the form shows the menus as typed. */
final class MenusTest extends TestCase
{
    private int $admin;

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config();
        pb_migrate();
        pb_set_option('site_title', 'Página inicial');
        $this->admin = pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
        pb_seed_demo();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['pb_menu_preview'], $GLOBALS['pb_organizer']);
        $_SESSION = $_GET = $_POST = [];
    }

    private function admin(string $method, string $path): string
    {
        $_SESSION['user_id'] = $this->admin;
        ob_start();
        pb_admin($method, $path);
        return ob_get_clean();
    }

    /** The menu the way the form sends it: location => [index => ['label' => ..., 'link' => ['page' => ..., 'url' => ...]]]. */
    private function form(string $location, array $links): array
    {
        $items = [];
        foreach ($links as $i => $link) {
            $items[$i] = ['label' => '', 'link' => ['page' => $link, 'url' => '']];
        }
        return [$location => $items];
    }

    /** The titles of a menu as the site prints them. */
    private function titles(string $location): array
    {
        return array_column(pb_menu($location), 'label');
    }

    /** Every menu as the form sends it: the published links, with the ones given in $change in their place. */
    private function all(array $change): array
    {
        $menus = [];
        foreach (array_keys(pb_theme()['menus']) as $location) {
            $menus += $this->form($location, $change[$location] ?? array_map(fn($i) => (string) $i['link'], pb_menu_items_raw($location)));
        }
        return $menus;
    }

    private function id(string $slug): int
    {
        return (int) pb_page_by_slug($slug)['id'];
    }

    public function test_the_screen_has_a_card_for_each_menu_with_a_row_for_each_item_and_the_site_beside_them(): void
    {
        $html = $this->admin('GET', '/admin/menus');
        foreach (['data-studio="menus"', 'data-menu-location="main"', 'data-menu-location="footer"', 'data-sortable', 'data-frame="a"', 'data-frame="b"', 'data-publish',
            'name="menu[main][0][label]"', 'name="menu[main][0][link][page]"', '__i__', 'data-quick-add', 'studio.js', 'data-split', 'name="page"'] as $part) {
            $this->assertStringContainsString($part, $html, $part);
        }
        $rows = count(pb_menu_items_raw('main')) + count(pb_menu_items_raw('footer')) + 2; // each menu's template is a row too
        $this->assertSame($rows, substr_count($html, 'data-menu-item>'), 'a row for each item');
    }

    public function test_a_draft_of_the_menus_waits_and_the_site_keeps_the_published_ones(): void
    {
        $before = $this->titles('main');
        $reversed = array_reverse(array_map(fn($i) => (string) $i['link'], pb_menu_items_raw('main')));
        $this->assertTrue(pb_menus_save_draft($this->all(['main' => $reversed])));

        $this->assertSame($before, $this->titles('main'), 'visitors still get the published menu');
        $this->assertNotNull(pb_menus_draft());
        $this->assertSame($reversed, array_column(pb_menu_items_working('main'), 'link'), 'the editor opens on the draft');
        $this->assertSame(array_column(pb_menu_items_raw('footer'), 'link'), array_column(pb_menu_items_working('footer'), 'link'), 'a menu nobody changed is the published one');
    }

    public function test_a_draft_equal_to_the_published_menus_is_no_draft(): void
    {
        $published = array_map(fn($i) => (string) $i['link'], pb_menu_items_raw('main'));
        pb_menus_save_draft($this->all(['main' => array_reverse($published)]));
        $this->assertNotNull(pb_menus_draft());
        $this->assertFalse(pb_menus_save_draft($this->all([])), 'put back as it was: nothing waits');
        $this->assertNull(pb_menus_draft());
    }

    public function test_publishing_puts_the_menus_of_the_form_on_the_site_and_clears_the_draft(): void
    {
        $links = ['page:' . $this->id('contato'), 'page:' . $this->id('inicio')];
        pb_menus_save_draft($this->form('main', array_reverse($links)));
        pb_menus_publish($this->form('main', $links));

        $this->assertSame(['Contato', 'Início'], $this->titles('main'));
        $this->assertNull(pb_menus_draft());
        $this->assertSame([], pb_menu_items_raw('footer'), 'a menu the form did not send is published empty: the form is the whole truth');
    }

    public function test_discarding_throws_the_draft_away(): void
    {
        pb_menus_save_draft($this->form('main', ['page:' . $this->id('contato')]));
        $this->assertNotNull(pb_menus_draft());
        pb_menus_discard_draft();
        $this->assertNull(pb_menus_draft());
        $this->assertSame(array_column(pb_menu_items_raw('main'), 'link'), array_column(pb_menu_items_working('main'), 'link'));
    }

    public function test_only_the_menus_of_the_theme_are_taken_and_empty_rows_are_dropped(): void
    {
        $menus = pb_menus_collect([
            'main' => [['label' => '', 'link' => ['page' => '', 'url' => '']], ['label' => 'Fale', 'link' => ['page' => 'page:' . $this->id('contato'), 'url' => '']], 'lixo'],
            'inventado' => [['label' => 'x', 'link' => ['page' => '', 'url' => 'https://exemplo.com']]],
        ]);
        $this->assertSame(array_keys(pb_theme()['menus']), array_keys($menus));
        $this->assertSame([['label' => 'Fale', 'link' => 'page:' . $this->id('contato')]], $menus['main'], 'an empty row is nothing; a typed text is kept');
        $this->assertSame([], $menus['footer']);
    }

    public function test_the_screen_opens_on_the_draft_and_says_so(): void
    {
        pb_menus_save_draft($this->form('main', ['page:' . $this->id('contato')]) + ['footer' => []]);
        $html = $this->admin('GET', '/admin/menus');
        $this->assertStringContainsString('data-state="changes"', $html);
        $this->assertStringContainsString('Publicados · com alterações não publicadas', $html);
        $this->assertDoesNotMatchRegularExpression('~data-discard[^>]* hidden~', $html, '"discard" is offered');
        $this->assertSame(1, substr_count($html, 'name="menu[main][0][label]"'));
        $this->assertSame(0, substr_count($html, 'name="menu[main][1][label]"'), 'the draft has one row in the main menu, not the published four');
    }

    public function test_the_preview_shows_the_menus_as_typed_and_saves_nothing(): void
    {
        $published = $this->titles('main');
        $_POST = ['page' => (string) pb_home_page_id(), 'menu' => $this->form('main', ['page:' . $this->id('contato'), 'page:' . $this->id('sobre')])];
        $html = $this->admin('POST', '/admin/menus/preview');
        preg_match('~<ul class="menu[^"]*">(.*?)</ul>~s', $html, $m);
        $this->assertNotEmpty($m, 'the header menu is in the page');
        $this->assertLessThan(strpos($m[1], 'Sobre'), strpos($m[1], 'Contato'), 'in the order that was typed');
        $this->assertStringNotContainsString('Serviços', $m[1], 'and nothing else');
        $this->assertStringContainsString('window.PB_FRAME', $html, 'with the page\'s script');
        $this->assertStringNotContainsString('<!--pb-section:', $html, 'but no sections to drag: here the menus are what is edited');

        unset($GLOBALS['pb_menu_preview']);
        $this->assertSame($published, $this->titles('main'), 'the published menu is untouched');
        $this->assertNull(pb_menus_draft(), 'looking is not saving');
    }

    public function test_the_preview_can_be_any_published_page_and_falls_back_to_the_home(): void
    {
        $_POST = ['page' => (string) $this->id('contato'), 'menu' => $this->all([])];
        $this->assertStringContainsString('aria-current="page"', $this->admin('POST', '/admin/menus/preview'), 'the page that is looked at is the current one');
        $draft = pb_page_create('Escondida', 'page', [], 'draft');
        $_POST = ['page' => (string) $draft, 'menu' => $this->all([])];
        $this->assertStringNotContainsString('Escondida', $this->admin('POST', '/admin/menus/preview'), 'a page not on the site is not shown: the home is');
    }

    public function test_an_item_says_when_its_page_is_not_on_the_site(): void
    {
        $draft = pb_page_create('Rascunho', 'page', [], 'draft');
        $this->assertSame('', pb_menu_item_note(['link' => 'page:' . $this->id('contato')]));
        $this->assertSame('', pb_menu_item_note(['link' => 'https://exemplo.com']));
        $this->assertStringContainsString('rascunho', pb_menu_item_note(['link' => 'page:' . $draft]));
        $this->assertStringContainsString('não existe', pb_menu_item_note(['link' => 'page:99999']));
    }
}
