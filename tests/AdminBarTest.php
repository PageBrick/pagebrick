<?php

use PHPUnit\Framework\TestCase;

/** The bar at the bottom of the site for people signed in to the panel: never for visitors, and live. */
final class AdminBarTest extends TestCase
{
    private int $admin;
    private int $editor;

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config();
        pb_migrate();
        pb_set_option('site_title', 'Página inicial');
        $this->admin = pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
        $this->editor = pb_create_user('Bia', 'bia@example.com', 'senha-de-teste-123', 'editor');
        pb_seed_demo();
        pb_db()->exec('UPDATE ' . pb_table('pages') . " SET updated_at = '2020-01-01 10:00:00'"); // so a save always changes the stamp
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        unset($GLOBALS['pb_current_page']);
        pb_test_new_request();
        http_response_code(200);
        pb_set_locale('pt-BR');
    }

    /** The site as a visitor (no user) or as someone signed in to the panel. */
    private function visit(string $path, ?int $user = null): string
    {
        pb_test_new_request();
        $_SESSION = $user ? ['user_id' => $user] : [];
        pb_set_locale(pb_site_locale());
        pb_load_plugins();
        $_SERVER['REQUEST_URI'] = $path;
        http_response_code(200);
        ob_start();
        pb_public('GET', $path);
        return ob_get_clean();
    }

    public function test_visitors_get_no_bar_and_nothing_else_changes_for_them(): void
    {
        $html = $this->visit('/');
        $this->assertStringNotContainsString('pb-bar', $html);
        $this->assertStringNotContainsString('admin-bar.js', $html);
        $this->assertStringNotContainsString('/admin/live', $html);
    }

    public function test_someone_signed_in_gets_the_bar_with_the_way_back_to_the_panel(): void
    {
        $html = $this->visit('/', $this->editor);
        $this->assertStringContainsString('<pb-bar id="pb-bar"', $html);
        $this->assertStringContainsString('data-poll="/admin/live"', $html);
        $this->assertStringContainsString('core/assets/admin-bar.js?v=', $html);
        // the brick takes them back to the panel in the same tab
        preg_match('~<a class="brand"[^>]*>~', $html, $brand);
        $this->assertStringContainsString('href="/admin"', $brand[0]);
        $this->assertStringNotContainsString('target=', $brand[0], 'no new tab');
        $this->assertStringContainsString('mark-dark.svg', $html);
        // and the quick actions
        foreach (['data-reload', 'data-live', 'data-phone', 'data-hide'] as $action) {
            $this->assertStringContainsString($action, $html);
        }
        $this->assertSame(1, substr_count($html, '<pb-bar'), 'once');
    }

    public function test_the_bar_offers_to_edit_the_page_being_seen(): void
    {
        $home = pb_home_page_id();
        $this->assertStringContainsString('href="/admin/pages/edit?id=' . $home . '"', $this->visit('/', $this->editor));
        $about = pb_page_by_slug('sobre');
        $this->assertStringContainsString('href="/admin/pages/edit?id=' . $about['id'] . '"', $this->visit('/sobre', $this->editor));
        $notFound = $this->visit('/nao-existe', $this->editor);
        $this->assertStringContainsString('<pb-bar', $notFound, 'on a missing page too');
        $this->assertStringNotContainsString('/admin/pages/edit', $notFound, 'but with nothing to edit');
    }

    public function test_a_theme_or_plugin_can_turn_it_off(): void
    {
        pb_test_new_request();
        pb_add_filter('admin_bar', fn(bool $show) => false);
        $_SESSION = ['user_id' => $this->editor];
        $_SERVER['REQUEST_URI'] = '/';
        ob_start();
        pb_public('GET', '/');
        $html = ob_get_clean();
        $this->assertStringContainsString('Diga aqui, em uma frase', $html, 'the site is there');
        $this->assertStringNotContainsString('pb-bar', $html);
    }

    public function test_a_preview_of_unsaved_content_has_no_bar(): void
    {
        $GLOBALS['pb_current_page'] = ['id' => 5, 'preview' => true];
        $_SESSION = ['user_id' => $this->editor];
        $this->assertSame('', pb_admin_bar());
        $GLOBALS['pb_current_page'] = ['id' => 5, 'preview' => false];
        $this->assertStringContainsString('<pb-bar', pb_admin_bar());
    }

    public function test_when_the_site_is_not_live_the_bar_says_so_instead_of_the_old_strip(): void
    {
        pb_set_site_mode('construction', '');
        $html = $this->visit('/', $this->editor);
        $this->assertStringContainsString('Em construção', $html);
        $this->assertSame(1, substr_count($html, 'Mudar no painel'), 'one notice, not two');
        $this->assertStringNotContainsString('position:fixed', $html, 'the old strip is not drawn on top of the bar');

        pb_set_site_mode('maintenance', '');
        $this->assertStringContainsString('Em manutenção', $this->visit('/', $this->editor));
        pb_set_site_mode('live', '');
        $this->assertStringNotContainsString('class="chip"', $this->visit('/', $this->editor));
    }

    public function test_the_bar_speaks_the_language_of_the_person_not_of_the_site(): void
    {
        pb_set_user_locale($this->editor, 'en');
        $html = $this->visit('/', $this->editor);
        $this->assertStringContainsString('Edit page', $html);
        $this->assertStringContainsString('Back to the dashboard', $html);
        $this->assertStringContainsString('<html lang="pt-BR">', $html, 'the site stays in its own language');
        $this->assertStringContainsString('Diga aqui, em uma frase', $html);
        $this->assertSame('pt-BR', pb_locale(), 'and the language is given back after the bar');
    }

    public function test_the_stamp_changes_when_something_that_shapes_the_site_is_saved(): void
    {
        $before = pb_live_stamp();
        $this->assertSame($before, pb_live_stamp(), 'it holds still while nothing is saved');

        $changes = [
            'a page' => fn() => pb_page_save(pb_page_by_slug('sobre')['id'], ['title' => 'Sobre nós', 'slug' => 'sobre', 'status' => 'published', 'seo_title' => '', 'seo_description' => '', 'f' => []], $this->admin),
            'the contact settings' => fn() => pb_save_settings(['contact' => ['phone' => '(19) 3333-4444']]),
            'a menu' => fn() => pb_save_menu('main', [['label' => 'Início', 'link' => ['page' => '/', 'url' => '']]]),
            'the site name' => fn() => pb_set_option('site_title', 'Outro nome'),
            'the site status' => fn() => pb_set_site_mode('maintenance', ''),
            'a translation' => fn() => pb_set_option('theme_settings@en', '{"footer":{"text":"Hi"}}'),
            'a photo description' => fn() => pb_db()->exec('UPDATE ' . pb_table('media') . " SET alt = 'Nova descrição' WHERE id = (SELECT id FROM (SELECT MIN(id) AS id FROM " . pb_table('media') . ') m)'),
        ];
        foreach ($changes as $what => $change) {
            $change();
            $after = pb_live_stamp();
            $this->assertNotSame($before, $after, "saving $what changes it");
            $before = $after;
        }
    }

    public function test_the_stamp_does_not_move_with_what_changes_by_itself(): void
    {
        $before = pb_live_stamp();
        pb_set_option('catalog_cache', '{"fetched_at": 1}');
        pb_set_option('themes', '{"default": {"error_at": "2026-10-11 10:00:00"}}');
        pb_set_option('plugins', '{}');
        $this->assertSame($before, pb_live_stamp(), 'a new catalog check or a theme error note would otherwise reload every page forever');
    }

    public function test_the_blog_adds_its_posts_to_the_stamp(): void
    {
        pb_activate_plugin('blog');
        pb_test_new_request();
        pb_load_plugins();
        $before = pb_live_stamp();
        pbb_save(pbb_create('Primeiro texto'), ['title' => 'Primeiro texto', 'slug' => '', 'status' => 'published', 'published_on' => date('Y-m-d'), 'f' => []]);
        $this->assertNotSame($before, pb_live_stamp(), 'a saved post is noticed');
    }

    public function test_the_panel_answers_the_bar_with_the_stamp_for_whoever_is_signed_in(): void
    {
        $_SESSION['user_id'] = $this->editor;
        $_SERVER['REQUEST_URI'] = '/admin/live';
        ob_start();
        pb_admin('GET', '/admin/live');
        $answer = json_decode(ob_get_clean(), true);
        $this->assertSame(['stamp' => pb_live_stamp()], $answer);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $answer['stamp']);
    }

    public function test_the_script_never_reloads_in_a_loop_and_waits_for_whoever_is_typing(): void
    {
        $js = file_get_contents(PB_ROOT . '/core/assets/admin-bar.js');
        $this->assertStringContainsString('recent.length >= 5', $js, 'a limit on automatic reloads');
        $this->assertStringContainsString('typing()', $js);
        $this->assertStringContainsString("'pb-live'", $js, 'the choice of live or not is remembered');
        $this->assertStringContainsString('document.hidden', $js, 'nothing is asked while the tab is in the background');
    }
}
