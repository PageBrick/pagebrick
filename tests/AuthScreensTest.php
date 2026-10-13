<?php

use PHPUnit\Framework\TestCase;

/** The screens for people who are not signed in: sign in, forgot my password and new password. */
final class AuthScreensTest extends TestCase
{
    protected function setUp(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config(['mail' => 'memory']);
        pb_migrate();
        pb_set_option('site_title', 'Página inicial');
        pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        http_response_code(200);
        pb_set_locale('pt-BR');
    }

    private function guest(string $method, string $path, array $post = [], array $query = []): string
    {
        $_SESSION = [];
        $_POST = $post;
        $_GET = $query;
        $_SERVER['REQUEST_URI'] = $path;
        http_response_code(200);
        ob_start();
        pb_admin($method, $path);
        return ob_get_clean();
    }

    public function test_the_sign_in_card_names_the_site_has_icons_and_a_way_back_to_the_site(): void
    {
        $html = $this->guest('GET', '/admin/login');
        $this->assertStringContainsString('<body class="guest-page auth-page"', $html);
        $this->assertStringContainsString('<h1>Entrar no painel</h1>', $html);
        $this->assertStringContainsString('Painel de Página inicial', $html, 'whoever signs in knows which site this is');
        $this->assertSame(2, substr_count($html, 'class="input-icon"'), 'the e-mail and the password have an icon');
        $this->assertStringContainsString('<footer class="auth-foot"><a href="/">', $html, 'a way back to the site, in a landmark');
        $this->assertStringContainsString('<header class="guest">', $html, 'the whole logo, as before');
        $this->assertStringNotContainsString(PB_VERSION, $html, 'a visitor is not told which version runs here');
    }

    public function test_a_wrong_password_is_told_inside_the_card(): void
    {
        $html = $this->guest('GET', '/admin/login');
        $this->assertStringNotContainsString('role="alert"', $html);
        ob_start();
        pb_render('login', ['title' => 'Entrar', 'narrow' => true, 'error' => 'E-mail ou senha incorretos.', 'email' => 'ana@example.com']);
        $html = ob_get_clean();
        $this->assertMatchesRegularExpression('~<p class="error" role="alert">E-mail ou senha incorretos\.</p>~', $html);
        $this->assertStringContainsString('value="ana@example.com"', $html, 'the e-mail is kept');
    }

    public function test_forgot_asks_for_the_email_and_then_confirms_without_the_form(): void
    {
        $html = $this->guest('GET', '/admin/forgot');
        $this->assertStringContainsString('<h1>Esqueci minha senha</h1>', $html);
        $this->assertStringContainsString('class="input-icon"', $html);
        $this->assertStringContainsString('Enviar o link', $html);

        $sent = $this->guest('POST', '/admin/forgot', ['email' => 'ana@example.com']);
        $this->assertStringContainsString('<h1>Confira o seu e-mail</h1>', $sent);
        $this->assertStringContainsString('O link vale por 1 hora.', $sent);
        $this->assertStringContainsString('role="status"', $sent);
        $this->assertStringNotContainsString('<form', $sent, 'nothing left to fill in');
        $this->assertStringContainsString('Voltar para o login', $sent);
        $this->assertSame('ana@example.com', $GLOBALS['pb_sent_mail'][0]['to'], 'and the link went by e-mail');
    }

    public function test_the_new_password_screen_has_two_fields_for_a_good_link_and_explains_a_bad_one(): void
    {
        $this->guest('POST', '/admin/forgot', ['email' => 'ana@example.com']);
        preg_match('/token=([0-9a-f]{64})/', $GLOBALS['pb_sent_mail'][0]['text'], $m);

        $good = $this->guest('GET', '/admin/reset', [], ['token' => $m[1]]);
        $this->assertStringContainsString('<h1>Criar uma senha nova</h1>', $good);
        $this->assertStringContainsString('name="password"', $good);
        $this->assertStringContainsString('name="password_repeat"', $good);
        $this->assertStringContainsString('value="' . $m[1] . '"', $good, 'the link travels with the form');
        $this->assertSame(2, substr_count($good, 'class="input-icon"'));

        $bad = $this->guest('GET', '/admin/reset', [], ['token' => str_repeat('b', 64)]);
        $this->assertStringContainsString('Este link não vale mais', $bad);
        $this->assertStringContainsString('Pedir um link novo', $bad);
        $this->assertStringNotContainsString('name="password"', $bad);
    }

    public function test_the_other_screens_for_visitors_do_not_use_the_sign_in_frame(): void
    {
        $html = $this->guest('GET', '/admin/does-not-exist');
        $this->assertSame(404, http_response_code());
        $this->assertStringContainsString('<body class="guest-page"', $html);
        $this->assertStringNotContainsString('auth-wrap', $html);
    }
}
