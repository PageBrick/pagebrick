<?php

use PHPUnit\Framework\TestCase;

/**
 * The panel sends what a person writes as one field, "pb_form" (core/assets/admin.js), so the firewall of a host (Mod_Security) has nothing to refuse: a link to another
 * site, "<script" or a few words of a database command are everyday text for whoever writes a site. The server puts the fields back (pb_unseal_post()).
 */
final class SealedFormTest extends TestCase
{
    protected function setUp(): void
    {
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_SESSION = [];
        $_GET = [];
        unset($GLOBALS['pb_theme']);
        pb_test_new_request();
        http_response_code(200);
    }

    /** What the browser sends: the fields as a query string (URLSearchParams), in base64 (btoa of the UTF-8 bytes). */
    private function seal(array $fields): string
    {
        return rtrim(base64_encode(http_build_query($fields)), "="); // as the browser sends it: without the "=" padding
    }

    public function test_the_fields_come_back_as_if_they_had_been_sent_plain(): void
    {
        $fields = [
            'action' => 'publish',
            'title' => 'Olá, mundo: ação & cia',
            'f' => ['social' => ['linkedin' => 'https://www.linkedin.com/company/alcateia-digital'], 'body' => "<p>Veja <a href=\"https://exemplo.com\">aqui</a></p>\r\n<script>x()</script>", 'list' => ['a', 'b']],
        ];
        $_POST = ['_csrf' => 'token', 'pb_form' => $this->seal($fields)];
        pb_unseal_post(true);
        $this->assertEqualsCanonicalizing(['_csrf' => 'token'] + $fields, $_POST);
        $this->assertSame($fields['f'], $_POST['f']);
        $this->assertArrayNotHasKey('pb_form', $_POST);
        $this->assertSame('publish', pb_post('action'));
    }

    public function test_the_token_that_was_checked_is_the_one_that_counts(): void
    {
        $_POST = ['_csrf' => 'real', 'pb_form' => $this->seal(['_csrf' => 'forged', 'a' => '1'])];
        pb_unseal_post(true);
        $this->assertSame('real', $_POST['_csrf']);
        $this->assertSame('1', $_POST['a']);
    }

    public function test_the_sealed_fields_win_over_a_plain_field_of_the_same_name(): void
    {
        $_POST = ['_csrf' => 't', 'a' => 'plain', 'b' => 'plain', 'pb_form' => $this->seal(['a' => 'sealed'])];
        pb_unseal_post(true);
        $this->assertEqualsCanonicalizing(['a' => 'sealed', '_csrf' => 't', 'b' => 'plain'], $_POST);
    }

    public function test_nobody_signed_out_gets_past_the_firewall_with_it(): void
    {
        $_POST = ['_csrf' => 't', 'pb_form' => $this->seal(['f' => ['x' => 'y']])];
        pb_unseal_post(false);
        $this->assertSame(['_csrf' => 't'], $_POST, 'the field is thrown away and nothing is read from it');
    }

    public function test_what_is_not_a_sealed_form_is_thrown_away_without_a_sound(): void
    {
        foreach (['not base64 !!', '', ['a' => 'b']] as $junk) {
            $_POST = ['_csrf' => 't', 'x' => '1', 'pb_form' => $junk];
            pb_unseal_post(true);
            $this->assertSame(['_csrf' => 't', 'x' => '1'], $_POST);
        }
        $_POST = ['_csrf' => 't'];
        pb_unseal_post(true);
        $this->assertSame(['_csrf' => 't'], $_POST, 'a form that was not sealed (no script in the browser) goes as it is');
    }

    public function test_a_big_page_goes_through(): void
    {
        $body = str_repeat('<p>Um parágrafo com <a href="https://exemplo.com/x?a=1&b=2">um link</a> e "aspas" — acentuação.</p>' . "\r\n", 4000);
        $_POST = ['_csrf' => 't', 'pb_form' => $this->seal(['f' => ['body' => $body]])];
        pb_unseal_post(true);
        $this->assertSame($body, $_POST['f']['body']);
    }

    public function test_the_contact_screen_reads_a_link_to_another_site_sent_this_way(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config();
        pb_migrate();
        pb_seed_demo();
        $_POST = ['_csrf' => 't', 'pb_form' => $this->seal(['f' => ['social' => ['linkedin' => 'https://www.linkedin.com/company/alcateia-digital']]])];
        pb_unseal_post(true);
        // what the Settings screen does with the form (pb_admin_settings_save_screen): the same fields, the same values
        $saved = pb_collect_fields(pb_settings_fields(), $_POST['f']);
        $this->assertSame('https://www.linkedin.com/company/alcateia-digital', $saved['social']['linkedin']);
    }

    public function test_the_scripts_of_the_panel_seal_what_a_firewall_would_refuse(): void
    {
        $seal = (string) file_get_contents(PB_ROOT . '/core/assets/seal.js');
        $this->assertStringContainsString("out.append('pb_form'", $seal, 'the fields go in one field');
        $this->assertStringContainsString("name === '_csrf'", $seal, 'the token stays outside');
        $this->assertStringContainsString('value instanceof File', $seal, 'and so do the files');
        $this->assertStringContainsString('meta[name=pb-csrf]', $seal, 'only the screens of a signed-in person do it');
        $this->assertStringContainsString("replace(/=+$/, '')", $seal, 'without the "=" padding that a firewall reads as an SQL operator');
        $admin = (string) file_get_contents(PB_ROOT . '/core/assets/admin.js');
        $this->assertStringContainsString("form.hasAttribute('data-plain')", $admin, 'a form can ask to go as it is');
        $this->assertLessThan(strpos($admin, 'window.pbSeal'), strpos($admin, 'textarea[data-code]'), 'the code boxes are written before the form is sealed');
        $studio = (string) file_get_contents(PB_ROOT . '/core/assets/studio.js');
        $this->assertSame(3, substr_count($studio, 'window.pbSeal?.(data) ?? data'), 'the preview, the draft and the sections of the page editor do it too');
        $this->assertSame(substr_count($studio, 'new FormData(form)'), substr_count($studio, 'window.pbSeal?.(data) ?? data'), 'every request of the page editor that carries the form');
        $layout = (string) file_get_contents(PB_ROOT . '/core/views/layout.php');
        $this->assertLessThan(strpos($layout, "\$asset('studio.js')"), strpos($layout, "\$asset('seal.js')"), 'the sealing is there before the page editor sends its first preview');
    }
}
