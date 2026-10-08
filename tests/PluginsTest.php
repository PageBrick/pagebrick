<?php

use PHPUnit\Framework\TestCase;

/** The circuit breakers: plugins that misbehave on purpose (tests/fixtures/plugins) must never take the site down. */
final class PluginsTest extends TestCase
{
    protected function setUp(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config(['plugins_dir' => __DIR__ . '/fixtures/plugins']);
        pb_migrate();
    }

    private function state(string $slug): array
    {
        return pb_plugin_states()[$slug] ?? [];
    }

    private function visit(string $method, string $path): string
    {
        ob_start();
        pb_public($method, $path);
        return ob_get_clean();
    }

    public function test_a_plugin_screen_can_live_under_the_gear_icon(): void
    {
        $admin = pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
        $editor = pb_create_user('Bia', 'bia@example.com', 'senha-de-teste-123', 'editor');
        pb_add_admin_page('vitrine', 'Vitrine', fn() => print('tela da vitrine'));                       // everyday: the top menu
        pb_add_admin_page('chaves', 'Chaves da API', fn() => print('tela das chaves'), 'editor', 'settings'); // technical: the gear
        $this->assertSame('admin', $GLOBALS['pb_admin_pages']['chaves']['role'], 'a gear screen is for administrators, whatever was asked');

        $panel = function (int $user, string $path): string {
            $_SESSION['user_id'] = $user;
            ob_start();
            pb_admin('GET', $path);
            return ob_get_clean();
        };
        $home = $panel($admin, '/admin');
        $this->assertMatchesRegularExpression('~<nav[^>]*>.*href="/admin/p/vitrine".*</nav>~s', $home);
        $this->assertDoesNotMatchRegularExpression('~<nav[^>]*>.*href="/admin/p/chaves".*</nav>~s', $home, 'not in the top menu');
        $this->assertMatchesRegularExpression('~settings-menu.*href="/admin/p/chaves"~s', $home, 'but in the gear menu');
        $this->assertStringContainsString('tela das chaves', $panel($admin, '/admin/p/chaves'));

        $this->assertStringNotContainsString('tela das chaves', $panel($editor, '/admin/p/chaves'), 'an editor never reaches it');
        $this->assertStringNotContainsString('/admin/p/chaves', $panel($editor, '/admin'));
        $_SESSION = [];
    }

    public function test_plugins_with_problems_are_listed_but_cannot_be_activated(): void
    {
        $plugins = pb_plugins_available();
        $this->assertArrayNotHasKey('problem', $plugins['good']);
        $this->assertStringContainsString('outra versão', $plugins['wrong-api']['problem']);
        $this->assertStringContainsString('plugin.json', $plugins['broken-json']['problem']);

        $this->expectException(InvalidArgumentException::class);
        pb_activate_plugin('wrong-api');
    }

    public function test_good_plugin_activates_creates_its_tables_and_adds_html(): void
    {
        pb_activate_plugin('good');
        $this->assertTrue($this->state('good')['active']);
        $this->assertSame(1, $this->state('good')['db']);
        pb_db()->query('SELECT COUNT(*) FROM ' . pb_table('good_items'));

        pb_test_new_request();
        pb_load_plugins();
        $this->assertSame('<p>bom</p>', pb_slot('test'));
        $this->assertSame('olá do plugin', $this->visit('GET', '/bom'));
        $this->assertSame('resto: a/b', $this->visit('GET', '/bom/a/b'));
    }

    public function test_plugins_that_break_while_loading_are_not_activated(): void
    {
        foreach (['throws-on-load' => 'falha ao carregar', 'parse-error' => 'syntax error'] as $slug => $reason) {
            try {
                pb_activate_plugin($slug);
                $this->fail("$slug was activated");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($reason, $e->getMessage());
            }
            $this->assertFalse($this->state($slug)['active']);
            $this->assertStringContainsString($reason, $this->state($slug)['error']);
        }
    }

    public function test_a_plugin_that_breaks_mid_page_is_switched_off_and_the_page_goes_on(): void
    {
        pb_activate_plugin('good');
        pb_activate_plugin('throws-in-filter');
        pb_activate_plugin('wrong-type');

        pb_test_new_request();
        pb_load_plugins();
        $this->assertSame('<p>bom</p>', pb_slot('test'), 'the good plugin still works');
        $this->assertFalse($this->state('throws-in-filter')['active']);
        $this->assertStringContainsString('quebrou no filtro', $this->state('throws-in-filter')['error']);
        $this->assertFalse($this->state('wrong-type')['active']);
        $this->assertStringContainsString('returned array instead of string', $this->state('wrong-type')['error']);
        $this->assertTrue($this->state('good')['active']);

        pb_test_new_request();
        pb_load_plugins();
        $this->assertSame('<p>bom</p>', pb_slot('test'), 'next request: the broken ones are no longer loaded');
    }

    public function test_a_broken_plugin_route_shows_a_message_instead_of_half_a_page(): void
    {
        pb_activate_plugin('route-crash');
        pb_test_new_request();
        pb_load_plugins();

        $html = $this->visit('GET', '/quebra');
        $this->assertStringNotContainsString('meio caminho', $html);
        $this->assertStringContainsString('temporariamente indisponível', $html);
        $this->assertFalse($this->state('route-crash')['active']);
    }

    public function test_a_fatal_error_inside_a_plugin_switches_it_off_for_the_next_request(): void
    {
        pb_activate_plugin('good');
        ob_start();
        $slug = pb_plugin_handle_fatal([
            'type' => E_ERROR,
            'message' => 'Allowed memory size exhausted',
            'file' => pb_plugins_dir() . '/good/plugin.php',
            'line' => 3,
        ]);
        $output = ob_get_clean();

        $this->assertSame('good', $slug);
        $this->assertFalse($this->state('good')['active']);
        $this->assertStringContainsString('foi desligado automaticamente', $output);
        $this->assertNull(pb_plugin_handle_fatal(['type' => E_ERROR, 'message' => 'x', 'file' => PB_ROOT . '/core/app.php', 'line' => 1]), 'core errors are not blamed on plugins');
        $this->assertNull(pb_plugin_handle_fatal(['type' => E_WARNING, 'message' => 'x', 'file' => pb_plugins_dir() . '/good/plugin.php', 'line' => 1]));
    }

    public function test_a_plugin_whose_folder_disappeared_is_switched_off(): void
    {
        pb_set_plugin_state('ghost', ['active' => true]);
        pb_load_plugins();
        $this->assertFalse($this->state('ghost')['active']);
        $this->assertNotEmpty($this->state('ghost')['error']);
    }

    public function test_a_deleted_plugin_shows_in_the_panel_and_can_be_forgotten(): void
    {
        pb_set_plugin_state('ghost', ['active' => false, 'error' => 'a pasta sumiu']);
        $this->assertTrue(pb_plugins_for_panel()['ghost']['missing']);

        pb_dismiss_plugin_error('ghost');
        $this->assertArrayNotHasKey('ghost', pb_plugin_states());
    }

    public function test_safe_mode_runs_no_plugin(): void
    {
        pb_activate_plugin('good');
        pb_test_new_request();
        $_SESSION['pb_safe_mode'] = true;
        pb_load_plugins();
        $this->assertSame('', pb_slot('test'));
        $this->assertTrue($this->state('good')['active'], 'safe mode does not change what is active');
    }

    public function test_plugin_settings_use_the_field_system(): void
    {
        pb_activate_plugin('good');
        pb_save_plugin_settings('good', ['greeting' => '  <b>Olá</b> ', 'hacker' => 'x']);
        $this->assertSame('&lt;b&gt;Olá&lt;/b&gt;', (string) pb_plugin_settings_values('good')->greeting);
    }

    public function test_recovery_key_is_stable_and_secret(): void
    {
        $key = pb_recovery_key();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $key);
        $this->assertSame($key, pb_recovery_key());
    }
}
