<?php

use PHPUnit\Framework\TestCase;

/** The frame around every panel screen: what each person sees in the narrow bar and the list beside it, and the dashboard. */
final class PanelNavigationTest extends TestCase
{
    private int $admin;
    private int $editor;

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config();
        pb_migrate();
        pb_set_option('site_title', 'Página inicial');
        $this->admin = pb_create_user('Ana Souza', 'ana@example.com', 'senha-de-teste-123', 'admin');
        $this->editor = pb_create_user('Bia', 'bia@example.com', 'senha-de-teste-123', 'editor');
        pb_seed_demo();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        pb_test_new_request();
        http_response_code(200);
        pb_set_locale('pt-BR');
    }

    private function user(int $id): array
    {
        return pb_find_user($id);
    }

    private function open(int $user, string $path = '/admin', array $query = []): string
    {
        $_SESSION['user_id'] = $user;
        $_GET = $query;
        $_SERVER['REQUEST_URI'] = $path;
        pb_set_locale(pb_find_user($user)['locale'] ?: 'pt-BR');
        ob_start();
        pb_admin('GET', $path);
        return ob_get_clean();
    }

    private function sections(array $nav): array
    {
        return array_column($nav['sections'], 'id');
    }

    private function section(array $nav, string $id): array
    {
        return array_values(array_filter($nav['sections'], fn($section) => $section['id'] === $id))[0];
    }

    private function current(array $nav): array
    {
        $on = [];
        foreach ($nav['sections'] as $section) {
            foreach ($section['items'] as $item) {
                if (!empty($item['current'])) {
                    $on[] = $item['url'];
                }
            }
        }
        return $on;
    }

    public function test_an_administrator_has_content_plugins_and_settings(): void
    {
        $nav = pb_admin_nav($this->user($this->admin), '/admin');
        $this->assertSame(['content', 'plugins', 'settings'], $this->sections($nav));
        $this->assertSame(['/admin', '/admin/pages', '/admin/media', '/admin/menus', '/admin/settings'], array_column($this->section($nav, 'content')['items'], 'url'));
        $this->assertSame(['/admin/general', '/admin/themes', '/admin/appearance', '/admin/updates', '/admin/email', '/admin/users'], array_column($this->section($nav, 'settings')['items'], 'url'));
        $this->assertSame(['/admin/plugins'], array_column($this->section($nav, 'plugins')['items'], 'url'), 'administrators always manage plugins');
    }

    public function test_an_editor_has_only_content_until_a_plugin_offers_them_a_screen(): void
    {
        $this->assertSame(['content'], $this->sections(pb_admin_nav($this->user($this->editor), '/admin')), 'no settings, nothing to manage');

        pb_add_admin_page('chaves', 'Chaves da API', fn() => null, 'admin');
        $this->assertSame(['content'], $this->sections(pb_admin_nav($this->user($this->editor), '/admin')), 'a screen for administrators is not theirs');

        pb_add_admin_page('vitrine', 'Vitrine', fn() => null);
        $nav = pb_admin_nav($this->user($this->editor), '/admin');
        $this->assertSame(['content', 'plugins'], $this->sections($nav));
        $this->assertSame(['/admin/p/vitrine'], array_column($this->section($nav, 'plugins')['items'], 'url'));
        $this->assertSame('/admin/p/vitrine', $this->section($nav, 'plugins')['url'], 'the plug opens the first screen');
    }

    public function test_the_list_beside_the_bar_follows_where_the_person_is(): void
    {
        pb_add_admin_page('vitrine', 'Vitrine', fn() => null);
        $admin = $this->user($this->admin);
        $where = fn(string $path) => (function () use ($admin, $path) {
            $nav = pb_admin_nav($admin, $path);
            return [array_column(array_filter($nav['sections'], fn($s) => $s['open']), 'id'), $this->current($nav)];
        })();

        $this->assertSame([['content'], ['/admin']], $where('/admin'));
        $this->assertSame([['content'], ['/admin/pages']], $where('/admin/pages/edit'), 'editing a page is still "Pages"');
        $this->assertSame([['content'], ['/admin/settings']], $where('/admin/settings'));
        $this->assertSame([['settings'], ['/admin/users']], $where('/admin/users/edit'));
        $this->assertSame([['settings'], ['/admin/updates']], $where('/admin/updates'));
        $this->assertSame([['settings'], ['/admin/appearance']], $where('/admin/appearance'), 'the look is under Configurações now');
        $this->assertSame([['plugins'], ['/admin/p/vitrine']], $where('/admin/p/vitrine'));
        $this->assertSame([['plugins'], ['/admin/plugins']], $where('/admin/plugins'));
        $this->assertSame([['content'], []], $where('/admin/account'), 'a screen outside the sections shows the list of Content, with nothing marked');
        $this->assertSame([['content'], []], $where('/admin/pagesx'), 'only the same screen or what is under it');
    }

    public function test_exactly_one_list_is_open_and_the_bar_marks_the_section_the_person_is_in(): void
    {
        $html = $this->open($this->admin, '/admin/themes');
        $this->assertSame(1, substr_count($html, ' data-open'), 'one list at a time');
        $this->assertMatchesRegularExpression('~<section class="nav-group" data-section="settings" data-open~', $html);
        preg_match('~<div class="rail">(.*?)</div>~s', $html, $rail);
        $this->assertSame(1, substr_count($rail[1], 'aria-current'), 'and one icon of the bar');
        $this->assertMatchesRegularExpression('~aria-label="Configurações" aria-current="true"~', $rail[1]);
        $this->assertStringContainsString('<a href="/admin/themes" aria-current="page">', $html, 'and one entry of the list');
    }

    public function test_a_plugin_settings_screen_marks_only_its_own_plugin(): void
    {
        pb_activate_plugin('contact-form');
        pb_test_new_request();
        pb_load_plugins();
        $admin = $this->user($this->admin);
        $marked = function (string $plugin) use ($admin) {
            $_GET = ['plugin' => $plugin];
            $items = array_filter($this->section(pb_admin_nav($admin, '/admin/plugins/settings'), 'plugins')['items'], fn($i) => !empty($i['current']));
            return array_column($items, 'url');
        };
        $this->assertSame(['/admin/plugins/settings?plugin=contact-form'], $marked('contact-form'));
        $this->assertSame([], $marked('another'));
        $this->assertSame([], $marked(''));
    }

    public function test_an_administrator_is_told_about_updates_and_plugins_that_were_switched_off(): void
    {
        $this->assertSame([], pb_admin_nav($this->user($this->admin), '/admin')['notices'], 'nothing to say');
        $html = $this->open($this->admin);
        $this->assertStringContainsString('Tudo em dia.', $html);
        $this->assertStringNotContainsString('class="dot"', $html);

        pb_set_option('catalog_cache', json_encode(['fetched_at' => time(), 'data' => ['format' => 1, 'core' => ['version' => '9.9.9'], 'plugins' => [], 'themes' => []]]));
        $nav = pb_admin_nav($this->user($this->admin), '/admin');
        $this->assertSame(['O PageBrick 9.9.9 está disponível.'], array_column($nav['notices'], 'text'));
        $this->assertSame(1, $nav['updates']);
        $this->assertTrue($this->section($nav, 'settings')['dot'], 'the gear says it too');
        $this->assertSame(1, array_values(array_filter($this->section($nav, 'settings')['items'], fn($i) => $i['url'] === '/admin/updates'))[0]['count']);
        $html = $this->open($this->admin);
        $this->assertStringContainsString('O PageBrick 9.9.9 está disponível.', explode('</header>', $html)[0], 'in the bell of the header');
        $this->assertStringContainsString('class="dot"', $html);

        pb_activate_plugin('contact-form');
        pb_set_option('catalog_cache', json_encode(['fetched_at' => time(), 'data' => ['format' => 1, 'core' => ['version' => '9.9.9'], 'plugins' => [['slug' => 'contact-form', 'name' => 'Contact', 'version' => '9.0.0']], 'themes' => []]]));
        $nav = pb_admin_nav($this->user($this->admin), '/admin');
        $this->assertSame(['O PageBrick 9.9.9 está disponível.', 'Há atualizações de plugins ou temas.'], array_column($nav['notices'], 'text'), 'the new version and, besides it, the plugin that has one');
        $this->assertSame(2, $nav['updates']);

        $this->assertSame([], pb_admin_nav($this->user($this->editor), '/admin')['notices'], 'editors do not get them');
        $this->assertStringNotContainsString('class="menu notices"', $this->open($this->editor), 'nor the bell');
    }

    public function test_the_header_has_the_icon_of_the_brand_not_the_whole_logo(): void
    {
        $html = $this->open($this->admin);
        $header = explode('</header>', $html)[0];
        $this->assertStringContainsString('<a class="brand" href="/admin" aria-label="PageBrick"></a>', $header);
        $this->assertStringNotContainsString('<img', $header, 'a background of the stylesheet, which has a light and a dark version');
        $this->assertStringNotContainsString('logo.svg', $header);
        $css = file_get_contents(PB_ROOT . '/core/assets/admin.css');
        $this->assertMatchesRegularExpression('~\.top \.brand \{[^}]*var\(--mark\)~', $css);
        $this->assertStringContainsString('--mark: url(mark.svg)', $css);
        $this->assertStringContainsString('--mark: url(mark-dark.svg)', $css);
        $dark = file_get_contents(PB_ROOT . '/core/assets/mark-dark.svg');
        $light = file_get_contents(PB_ROOT . '/core/assets/mark.svg');
        $this->assertStringNotContainsString('<text', $light . $dark, 'only the brick');
        $this->assertStringContainsString('#171923', $light);
        $this->assertStringContainsString('#F3F3F6', $dark);
        $this->assertStringContainsString('class="guest"', $this->guest('/admin/login'), 'the sign-in screen keeps the whole logo');
    }

    private function guest(string $path): string
    {
        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = $path;
        ob_start();
        pb_admin('GET', $path);
        return ob_get_clean();
    }

    public function test_the_header_shows_the_site_and_whether_it_is_on_the_air(): void
    {
        $header = fn() => explode('</header>', $this->open($this->editor))[0];
        $this->assertStringContainsString('<strong>Página inicial</strong>', $header());
        $this->assertStringContainsString('<a class="pill ok" href="/admin">No ar</a>', $header());
        pb_set_site_mode('maintenance', '');
        $this->assertStringContainsString('<a class="pill warn" href="/admin">Em manutenção</a>', $header());
    }

    public function test_the_account_menu_holds_the_person_the_theme_the_language_and_signing_out(): void
    {
        $html = $this->open($this->admin);
        preg_match('~<details class="menu account">.*?</details>~s', $html, $menu);
        $menu = $menu[0];
        $this->assertStringContainsString('<span class="avatar" aria-hidden="true" data-initials="AS"></span>', $menu, 'the initials of first and last name');
        $this->assertStringContainsString('ana@example.com', $menu);
        $this->assertStringContainsString('Administrador', $menu);
        $this->assertStringContainsString('href="/admin/account"', $menu);
        $this->assertSame(3, substr_count($menu, 'name="locale"'), 'the three languages');
        $this->assertStringContainsString('action="/admin/logout"', $menu);
        $this->assertStringContainsString('data-theme-control', $menu);
        $this->assertStringContainsString('<a class="skip" href="#main">', $html, 'for people who use the keyboard');
        $this->assertStringContainsString('<main id="main"', $html);
        $this->assertStringContainsString('<p class="side-foot">PageBrick ' . PB_VERSION . '</p>', $html);
    }

    public function test_the_dashboard_counts_pages_and_lists_the_ones_edited_last(): void
    {
        $this->assertSame(5, (int) pb_db()->query("SELECT COUNT(*) FROM " . pb_table('pages') . " WHERE status = 'published'")->fetchColumn(), 'the demo pages');
        $draft = pb_page_create('Promoção de verão', 'page');
        pb_db()->exec('UPDATE ' . pb_table('pages') . " SET updated_at = '2020-01-01 10:00:00'");
        pb_db()->prepare('UPDATE ' . pb_table('pages') . " SET updated_at = '2026-10-10 09:30:00' WHERE id = ?")->execute([$draft]);

        $html = $this->open($this->admin);
        preg_match_all('~<strong>(\d+)</strong><span>([^<]+)</span>~', $html, $m);
        $stats = array_combine($m[2], $m[1]);
        $this->assertSame('5', $stats['Páginas publicadas']);
        $this->assertSame('1', $stats['Rascunhos']);
        $this->assertSame('1', $stats['Idiomas']);
        $this->assertSame((string) count(pb_media_list()), $stats['Mídia']);

        preg_match_all('~<a class="row-title" href="[^"]*">([^<]+)</a>~', $html, $titles);
        $this->assertSame('Promoção de verão', $titles[1][0], 'the newest first');
        $this->assertCount(5, $titles[1]);
        $this->assertStringContainsString('<span class="pill">Rascunho</span>', $html);
        $this->assertStringContainsString('10/10/2026 09:30', $html);
    }

    public function test_a_translation_edited_last_shows_up_with_its_language(): void
    {
        pb_set_site_locales(['en']);
        $translation = pb_page_translate(pb_home_page_id(), 'en');
        pb_db()->exec('UPDATE ' . pb_table('pages') . " SET updated_at = '2020-01-01 10:00:00'");
        pb_db()->prepare('UPDATE ' . pb_table('pages') . " SET updated_at = '2026-10-10 09:30:00' WHERE id = ?")->execute([$translation]);
        $title = pb_page_find($translation)['title'];
        $this->assertMatchesRegularExpression('~href="/admin/pages/edit\?id=' . $translation . '">' . preg_quote(e($title), '~') . '</a>\s*<span class="badge" title="[^"]*">EN</span>~', $this->open($this->admin));
    }

    public function test_the_dashboard_still_offers_the_site_status_to_administrators_only(): void
    {
        $this->assertStringContainsString('action="/admin/site-mode"', $this->open($this->admin));
        $this->assertStringNotContainsString('action="/admin/site-mode"', $this->open($this->editor));
    }
}
