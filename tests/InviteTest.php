<?php

use PHPUnit\Framework\TestCase;

/** Adding a person by name, e-mail and role: they get an e-mail with a link, choose their own password there and go straight into the panel. */
final class InviteTest extends TestCase
{
    private int $admin;

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config();
        pb_migrate();
        pb_set_option('site_title', 'Acme');
        pb_set_option('site_url', 'https://acme.example');
        $this->admin = pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
        $GLOBALS['pb_sent_mail'] = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $_GET = $_POST = [];
        pb_set_locale('pt-BR');
    }

    private function token(string $link): string
    {
        preg_match('/token=([0-9a-f]{64})/', $link, $m);
        return $m[1] ?? '';
    }

    public function test_an_invited_person_is_pending_and_gets_an_e_mail_with_the_link(): void
    {
        $invite = pb_invite_user('  Bia ', 'BIA@example.com', 'editor', 'Ana');

        $this->assertTrue($invite['sent']);
        $this->assertSame('bia@example.com', $invite['user']['email']);
        $this->assertNotEmpty(pb_find_user((int) $invite['user']['id'])['invited_at'], 'pending until the password is chosen');
        $mail = $GLOBALS['pb_sent_mail'][0];
        $this->assertSame('bia@example.com', $mail['to']);
        $this->assertSame('Convite para o painel de Acme', $mail['subject']);
        $this->assertStringContainsString('Ana convidou você', $mail['text']);
        $this->assertStringContainsString('como Editor', $mail['text']);
        $this->assertStringContainsString('7 dias', $mail['text']);
        $this->assertStringContainsString($invite['link'], $mail['text']);
        $this->assertStringStartsWith('https://acme.example/admin/reset?token=', $invite['link']);
    }

    public function test_the_link_lasts_a_week_and_only_its_hash_is_kept(): void
    {
        $invite = pb_invite_user('Bia', 'bia@example.com', 'editor', 'Ana');
        $row = pb_db()->query('SELECT token_hash, expires_at > NOW() + INTERVAL 6 DAY AS long_enough, expires_at < NOW() + INTERVAL 8 DAY AS not_too_long FROM ' . pb_table('password_resets'))->fetch();
        $this->assertSame(hash('sha256', $this->token($invite['link'])), $row['token_hash']);
        $this->assertSame([1, 1], [(int) $row['long_enough'], (int) $row['not_too_long']], 'a week, not the hour of "forgot my password"');
    }

    public function test_nobody_can_sign_in_before_the_link_is_used(): void
    {
        pb_invite_user('Bia', 'bia@example.com', 'editor', 'Ana');
        foreach (['', 'bia@example.com', 'senha-de-teste-123', 'password'] as $guess) {
            try {
                pb_login('bia@example.com', $guess, '10.0.0.9');
                $this->fail("the account was opened with '$guess'");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_choosing_the_password_ends_the_invitation_and_the_link_works_once(): void
    {
        $invite = pb_invite_user('Bia', 'bia@example.com', 'editor', 'Ana');
        $token = $this->token($invite['link']);

        $this->assertNotEmpty(pb_password_reset_user($token)['invited_at'], 'the screen can tell an invitation from a reset');
        $user = pb_password_reset($token, 'minha-senha-nova-1', 'minha-senha-nova-1');
        $this->assertNotEmpty($user['invited_at'], 'what the handler needs to sign the person in');

        $this->assertEmpty(pb_find_user((int) $user['id'])['invited_at'], 'no longer pending');
        $this->assertSame((int) $user['id'], (int) pb_login('bia@example.com', 'minha-senha-nova-1', '10.0.0.9')['id']);
        $this->expectExceptionMessage('não vale mais');
        pb_password_reset($token, 'outra-senha-123', 'outra-senha-123');
    }

    public function test_sending_again_replaces_the_link_and_a_person_who_chose_a_password_has_nothing_to_resend(): void
    {
        $first = pb_invite_user('Bia', 'bia@example.com', 'editor', 'Ana');
        $second = pb_send_invite((int) $first['user']['id'], 'Ana');

        $this->assertNotSame($first['link'], $second['link']);
        $this->assertNull(pb_password_reset_user($this->token($first['link'])), 'the old link stops working');
        $this->assertNotNull(pb_password_reset_user($this->token($second['link'])));
        $this->assertCount(2, $GLOBALS['pb_sent_mail']);

        pb_password_reset($this->token($second['link']), 'minha-senha-nova-1', 'minha-senha-nova-1');
        $this->expectExceptionMessage('já criou a senha');
        pb_send_invite((int) $first['user']['id'], 'Ana');
    }

    public function test_an_account_that_was_not_invited_has_no_invitation_to_send(): void
    {
        $this->expectExceptionMessage('já criou a senha');
        pb_send_invite($this->admin, 'Ana');
    }

    public function test_when_the_e_mail_cannot_go_the_link_comes_back_to_be_handed_over_another_way(): void
    {
        $GLOBALS['pb_config']['mail'] = 'fail';
        $invite = pb_invite_user('Bia', 'bia@example.com', 'editor', 'Ana');

        $this->assertFalse($invite['sent']);
        $this->assertNotSame('', $this->token($invite['link']));
        $this->assertNotNull(pb_password_reset_user($this->token($invite['link'])), 'and the link works');
        $this->assertNotEmpty(pb_find_user((int) $invite['user']['id'])['invited_at']);

        pb_invite_notice($invite);
        $this->assertSame('bia@example.com', $_SESSION['invite_link']['email']);
        $this->assertSame($invite['link'], pb_take_invite_link()['link']);
        $this->assertNull(pb_take_invite_link(), 'shown once');
        $this->assertSame('error', $_SESSION['flash'][0][0]);
    }

    public function test_the_invitation_is_in_the_language_of_the_site_not_of_who_sends_it(): void
    {
        pb_set_option('locale', 'en');
        pb_set_locale('pt-BR'); // the person inviting has the panel in Portuguese
        pb_invite_user('Bia', 'bia@example.com', 'admin', 'Ana');
        $this->assertSame('Invitation to the Acme dashboard', $GLOBALS['pb_sent_mail'][0]['subject']);
        $this->assertStringContainsString('as Administrator', $GLOBALS['pb_sent_mail'][0]['text']);
        $this->assertSame('pt-BR', pb_locale(), 'and the panel goes on in the language it was in');
    }

    public function test_deleting_a_person_takes_the_invitation_link_with_them(): void
    {
        $invite = pb_invite_user('Bia', 'bia@example.com', 'editor', 'Ana');
        pb_delete_user((int) $invite['user']['id'], $this->admin);
        $this->assertNull(pb_password_reset_user($this->token($invite['link'])));
        $this->assertSame(0, (int) pb_db()->query('SELECT COUNT(*) FROM ' . pb_table('password_resets'))->fetchColumn());
    }

    public function test_a_password_given_by_an_administrator_ends_the_invitation_too(): void
    {
        $invite = pb_invite_user('Bia', 'bia@example.com', 'editor', 'Ana');
        $id = (int) $invite['user']['id'];
        pb_update_user($id, 'Bia', 'bia@example.com', 'editor', 'senha-dada-pelo-admin');

        $this->assertEmpty(pb_find_user($id)['invited_at']);
        $this->assertNull(pb_password_reset_user($this->token($invite['link'])), 'and the link is spent');
        $this->assertSame($id, (int) pb_login('bia@example.com', 'senha-dada-pelo-admin', '10.0.0.9')['id']);
    }

    private function screen(string $method, string $path, array $get = []): string
    {
        $_SESSION['user_id'] = $this->admin;
        $_GET = $get;
        ob_start();
        pb_admin($method, $path);
        return ob_get_clean();
    }

    public function test_the_list_marks_who_has_not_chosen_a_password_and_offers_to_send_again(): void
    {
        $invite = pb_invite_user('Bia', 'bia@example.com', 'editor', 'Ana');
        $html = $this->screen('GET', '/admin/users');

        $this->assertStringContainsString('Convite pendente', $html);
        $this->assertSame(1, substr_count($html, 'Convite pendente'), 'only the invited person');
        $this->assertStringContainsString('Reenviar convite', $html);
        $this->assertStringContainsString('action="/admin/users/invite"', $html);
        $this->assertStringContainsString('Senha (opcional)', $html);
        $this->assertStringNotContainsString('id="invite-link"', $html, 'the e-mail went, so there is no link to copy');

        pb_password_reset($this->token($invite['link']), 'minha-senha-nova-1', 'minha-senha-nova-1');
        $this->assertStringNotContainsString('Convite pendente', $this->screen('GET', '/admin/users'));
    }

    public function test_the_link_to_copy_is_on_the_list_once_when_the_e_mail_could_not_go(): void
    {
        $GLOBALS['pb_config']['mail'] = 'fail';
        $invite = pb_invite_user('Bia', 'bia@example.com', 'editor', 'Ana');
        pb_invite_notice($invite);

        $html = $this->screen('GET', '/admin/users');
        $this->assertStringContainsString('id="invite-link"', $html);
        $this->assertStringContainsString(htmlspecialchars($invite['link'], ENT_QUOTES), $html);
        $this->assertStringContainsString('data-copy="invite-link"', $html);
        $this->assertStringNotContainsString('id="invite-link"', $this->screen('GET', '/admin/users'), 'a second visit does not show it again');
    }

    public function test_the_link_opens_a_welcome_page_for_an_invitation_and_the_usual_one_for_a_reset(): void
    {
        $invite = pb_invite_user('Bia', 'bia@example.com', 'editor', 'Ana');
        $html = $this->screen('GET', '/admin/reset', ['token' => $this->token($invite['link'])]);
        $this->assertStringContainsString('Bem-vindo ao painel de Acme', $html);
        $this->assertStringContainsString('Criar a senha e entrar', $html);

        pb_password_reset_request('ana@example.com', '10.0.0.5');
        $link = $GLOBALS['pb_sent_mail'][1]['text'];
        $html = $this->screen('GET', '/admin/reset', ['token' => $this->token($link)]);
        $this->assertStringContainsString('Salvar a senha nova', $html);
        $this->assertStringNotContainsString('Bem-vindo ao painel', $html);
    }
}
