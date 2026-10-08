<?php

use PHPUnit\Framework\TestCase;

/** "Em construção" and "Em manutenção": visitors get a notice (503), people logged in to the panel see the site. */
final class SiteModeTest extends TestCase
{
    private int $admin;
    private int $editor;

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config();
        pb_migrate();
        pb_set_option('site_title', 'Padaria Exemplo');
        $this->admin = pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
        $this->editor = pb_create_user('Edu', 'edu@example.com', 'senha-de-teste-123', 'editor');
        pb_seed_demo();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        http_response_code(200);
    }

    /** @return array{int, string} status and output */
    private function visit(string $path, ?int $userId = null): array
    {
        pb_test_new_request();
        $_SESSION = $userId ? ['user_id' => $userId] : [];
        $_SERVER['REQUEST_URI'] = $path;
        http_response_code(200);
        ob_start();
        str_starts_with($path, '/admin') ? pb_admin('GET', $path) : pb_public('GET', $path);
        return [http_response_code(), ob_get_clean()];
    }

    public function test_the_site_is_live_by_default(): void
    {
        $this->assertSame('live', pb_site_mode());
        [$status, $html] = $this->visit('/');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('Diga aqui, em uma frase', $html);
    }

    public function test_under_construction_visitors_see_only_the_notice(): void
    {
        pb_set_site_mode('construction', '');
        foreach (['/', '/sobre', '/sitemap.xml'] as $path) {
            [$status, $html] = $this->visit($path);
            $this->assertSame(503, $status, $path);
            $this->assertStringContainsString('Site em construção', $html);
            $this->assertStringContainsString('noindex', $html);
            $this->assertStringNotContainsString('Diga aqui, em uma frase', $html);
        }
        [$status, $json] = $this->visit('/api/v1/pages');
        $this->assertSame([503, ['error' => 'under_construction']], [$status, json_decode($json, true)]);

        [$status, $html] = $this->visit('/', $this->editor);
        $this->assertSame(200, $status, 'people logged in to the panel see the site');
        $this->assertStringContainsString('Diga aqui, em uma frase', $html);
        $this->assertStringContainsString('Mudar no painel', $html);
    }

    public function test_maintenance_with_a_message_of_your_own(): void
    {
        pb_set_site_mode('maintenance', '  Voltamos às 15h.  ');
        [$status, $html] = $this->visit('/');
        $this->assertSame(503, $status);
        $this->assertStringContainsString('Site em manutenção', $html);
        $this->assertStringContainsString('Voltamos às 15h.', $html);

        pb_set_site_mode('live', '');
        $this->assertSame(200, $this->visit('/')[0]);
        $this->expectException(InvalidArgumentException::class);
        pb_set_site_mode('fechado', '');
    }

    public function test_a_theme_can_draw_the_notice_and_a_broken_one_falls_back(): void
    {
        $dir = sys_get_temp_dir() . '/pb-closed-' . bin2hex(random_bytes(4));
        pb_test_copy_dir(PB_ROOT . '/content/themes/default', "$dir/default");
        $GLOBALS['pb_config']['themes_dir'] = $dir;
        try {
            pb_set_site_mode('construction', '');
            file_put_contents("$dir/default/templates/closed.php", '<p>MEU-AVISO <?= e($title) ?></p>');
            $this->assertStringContainsString('MEU-AVISO Site em construção', $this->visit('/')[1]);

            file_put_contents("$dir/default/templates/closed.php", '<?php throw new RuntimeException("aviso quebrado");');
            [$status, $html] = $this->visit('/');
            $this->assertSame(503, $status);
            $this->assertStringContainsString('Site em construção', $html, "the core's own notice");
            $this->assertStringContainsString('aviso quebrado', pb_theme_states()['default']['error']);
        } finally {
            pb_rmtree($dir);
        }
    }

    public function test_only_administrators_change_it(): void
    {
        $this->assertStringContainsString('Situação do site', $this->visit('/admin', $this->admin)[1]);
        $this->assertStringNotContainsString('name="mode"', $this->visit('/admin', $this->editor)[1]);

        pb_test_new_request();
        $_SESSION = ['user_id' => $this->editor];
        ob_start();
        pb_admin('POST', '/admin/site-mode');
        ob_end_clean();
        $this->assertSame(403, http_response_code());
        $this->assertSame('live', pb_site_mode());

        pb_set_site_mode('maintenance', '');
        $this->assertStringContainsString('O site está em manutenção', $this->visit('/admin/pages', $this->editor)[1], 'the panel warns everyone');
    }
}
