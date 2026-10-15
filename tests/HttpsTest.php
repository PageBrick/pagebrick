<?php

use PHPUnit\Framework\TestCase;

/** The button that makes the site open only over https:// (a few lines in .htaccess, put there and taken out by Settings → Address and languages). */
final class HttpsTest extends TestCase
{
    private const ORIGINAL = "# PageBrick: every request goes through index.php.\nOptions -Indexes\n\n<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteRule ^core/(?!assets/) - [F,L]\n</IfModule>\n";

    private string $dir;
    private int $admin;

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $this->dir = sys_get_temp_dir() . '/pb-https-' . bin2hex(random_bytes(4));
        mkdir("$this->dir/backups", 0777, true);
        file_put_contents("$this->dir/.htaccess", self::ORIGINAL);
        $GLOBALS['pb_config'] = pb_test_config(['htaccess_file' => "$this->dir/.htaccess", 'backups_dir' => "$this->dir/backups"]);
        pb_migrate();
        pb_set_option('site_title', 'Site');
        pb_set_option('site_url', 'http://exemplo.test');
        $this->admin = pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
        unset($_SERVER['HTTPS']);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTPS']);
        $_SESSION = $_GET = $_POST = [];
        pb_rmtree($this->dir);
    }

    private function file(): string
    {
        return (string) file_get_contents("$this->dir/.htaccess");
    }

    private function screen(): string
    {
        $_SESSION['user_id'] = $this->admin;
        ob_start();
        pb_admin('GET', '/admin/general');
        return ob_get_clean();
    }

    public function test_the_rules_leave_alone_what_is_already_https_what_a_proxy_says_is_https_and_the_certificate_checks(): void
    {
        $block = pb_https_block();
        $this->assertStringStartsWith(PB_HTTPS_BEGIN, $block);
        $this->assertStringEndsWith(PB_HTTPS_END . "\n", $block);
        foreach (['RewriteCond %{HTTPS} !=on', 'RewriteCond %{HTTP:X-Forwarded-Proto} !https [NC]', 'RewriteCond %{REQUEST_URI} !^/\.well-known/', 'RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]'] as $line) {
            $this->assertStringContainsString($line, $block, $line);
        }
    }

    public function test_the_block_goes_to_the_top_once_and_comes_out_leaving_the_file_as_it_was(): void
    {
        $this->assertFalse(pb_https_forced());
        pb_https_set(true);
        $this->assertTrue(pb_https_forced());
        $this->assertStringStartsWith(PB_HTTPS_BEGIN, $this->file());
        $this->assertStringEndsWith(self::ORIGINAL, $this->file(), 'everything that was there stays, after the block');

        pb_https_set(true);
        $this->assertSame(1, substr_count($this->file(), PB_HTTPS_BEGIN), 'pressing twice does not stack the block');

        pb_https_set(false);
        $this->assertFalse(pb_https_forced());
        $this->assertSame(self::ORIGINAL, $this->file(), 'taken out, the file is exactly the one it was');
        pb_https_set(false);
        $this->assertSame(self::ORIGINAL, $this->file(), 'taking out what is not there changes nothing');
    }

    public function test_the_file_as_it_was_is_kept_outside_the_web_before_every_change(): void
    {
        pb_https_set(true);
        $copies = glob("$this->dir/backups/htaccess_*.txt");
        $this->assertCount(1, $copies);
        $this->assertSame(self::ORIGINAL, file_get_contents($copies[0]));
    }

    public function test_an_htaccess_that_does_not_exist_yet_is_created(): void
    {
        unlink("$this->dir/.htaccess");
        pb_https_set(true);
        $this->assertTrue(pb_https_forced());
        pb_https_set(false);
        $this->assertSame('', $this->file());
    }

    public function test_a_file_the_server_cannot_write_says_so_instead_of_pretending(): void
    {
        $GLOBALS['pb_config']['htaccess_file'] = "$this->dir/no-such-folder/.htaccess";
        $this->assertFalse(pb_https_writable());
        $this->expectExceptionMessage('não conseguiu escrever');
        pb_https_set(true);
    }

    public function test_over_plain_http_the_browser_must_have_opened_the_https_address_first(): void
    {
        try {
            pb_https_apply(true, false);
            $this->fail('forcing https:// without proof that it works would lock everybody out');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Não foi possível confirmar', $e->getMessage());
        }
        $this->assertFalse(pb_https_forced());
        $this->assertSame('http://exemplo.test', pb_option('site_url'));

        $this->assertStringContainsString('Pronto', pb_https_apply(true, true));
        $this->assertTrue(pb_https_forced());
        $this->assertSame('https://exemplo.test', pb_option('site_url'), 'the official address follows');
    }

    public function test_a_visit_that_is_already_over_https_needs_no_proof(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $this->assertStringContainsString('Pronto', pb_https_apply(true, false));
        $this->assertTrue(pb_https_forced());
        $this->assertStringContainsString('Desligado', pb_https_apply(false, false));
        $this->assertFalse(pb_https_forced());
        $this->assertSame('https://exemplo.test', pb_option('site_url'), 'switching it off does not take the https:// address away');
    }

    public function test_the_settings_screen_offers_the_button_with_the_check_over_http_and_without_it_over_https(): void
    {
        $http = $this->screen();
        $this->assertStringContainsString('Conexão segura (https)', $http);
        $this->assertStringContainsString('Obrigar https://', $http);
        $this->assertStringContainsString('data-https-check="https://', $http, 'over http the browser tries the https address first');
        $this->assertStringContainsString('name="action" value="on"', $http);
        $this->assertStringContainsString('action="/admin/general/https"', $http);

        $_SERVER['HTTPS'] = 'on';
        $https = $this->screen();
        $this->assertStringContainsString('Obrigar https://', $https);
        $this->assertStringNotContainsString('data-https-check', $https, 'a visit that came over https already proves it');
    }

    public function test_the_screen_says_when_it_is_on_and_offers_to_turn_it_off(): void
    {
        pb_https_set(true);
        $html = $this->screen();
        $this->assertStringContainsString('Ligado: quem abrir o site com http://', $html);
        $this->assertStringContainsString('name="action" value="off"', $html);
        $this->assertStringContainsString('Desligar', $html);
        $this->assertStringNotContainsString('Obrigar https://', $html);
    }

    public function test_a_file_that_cannot_be_written_shows_the_lines_to_paste_by_hand(): void
    {
        $GLOBALS['pb_config']['htaccess_file'] = "$this->dir/no-such-folder/.htaccess";
        $html = $this->screen();
        $this->assertStringContainsString('não conseguiu escrever no arquivo .htaccess', $html);
        $this->assertStringContainsString('RewriteCond %{HTTPS} !=on', $html);
        $this->assertStringNotContainsString('Obrigar https://</button>', $html);
    }
}
