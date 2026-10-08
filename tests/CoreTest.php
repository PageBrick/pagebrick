<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CoreTest extends TestCase
{
    private int $adminId;

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = ['db' => pb_test_db()];
        pb_migrate();
        $this->adminId = pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
    }

    public function test_login_with_right_password_returns_the_user(): void
    {
        $user = pb_login('  ANA@example.com ', 'senha-de-teste-123', '10.0.0.1');
        $this->assertSame($this->adminId, (int) $user['id']);
    }

    public function test_wrong_password_and_unknown_email_look_the_same(): void
    {
        $messages = [];
        foreach (['ana@example.com', 'ninguem@example.com'] as $email) {
            try {
                pb_login($email, 'errada', '10.0.0.1');
            } catch (InvalidArgumentException $e) {
                $messages[] = $e->getMessage();
            }
        }
        $this->assertSame(['E-mail ou senha incorretos.', 'E-mail ou senha incorretos.'], $messages);
    }

    public function test_login_is_blocked_after_five_failures_even_with_the_right_password(): void
    {
        $this->failLogins(5, '10.0.0.1');

        try {
            pb_login('ana@example.com', 'senha-de-teste-123', '10.0.0.1');
            $this->fail('Expected login to be blocked');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Muitas tentativas', $e->getMessage());
        }
        // Another IP is not affected.
        $this->assertSame($this->adminId, (int) pb_login('ana@example.com', 'senha-de-teste-123', '10.0.0.2')['id']);
    }

    public function test_successful_login_resets_the_failure_count(): void
    {
        $this->failLogins(4, '10.0.0.1');
        pb_login('ana@example.com', 'senha-de-teste-123', '10.0.0.1');
        $this->failLogins(4, '10.0.0.1');

        $this->assertSame($this->adminId, (int) pb_login('ana@example.com', 'senha-de-teste-123', '10.0.0.1')['id']);
    }

    /** Asks for a reset link for $email and returns the token e-mailed, or null when nothing was sent. */
    private function resetLink(string $email, string $ip = '10.0.0.1'): ?string
    {
        $GLOBALS['pb_config']['mail'] = 'memory';
        $GLOBALS['pb_sent_mail'] = [];
        pb_password_reset_request($email, $ip);
        $mail = $GLOBALS['pb_sent_mail'][0] ?? null;
        return $mail && preg_match('/token=([0-9a-f]{64})/', $mail['text'], $m) ? $m[1] : null;
    }

    public function test_forgot_password_link_works_once(): void
    {
        $token = $this->resetLink(' ANA@example.com');
        $this->assertNotNull($token);
        $this->assertSame('ana@example.com', $GLOBALS['pb_sent_mail'][0]['to']);
        $stored = pb_db()->query('SELECT token_hash FROM ' . pb_table('password_resets'))->fetchColumn();
        $this->assertSame(hash('sha256', $token), $stored, 'only a hash of the link is kept');

        pb_password_reset($token, 'senha-nova-456', 'senha-nova-456');
        $this->assertSame($this->adminId, (int) pb_login('ana@example.com', 'senha-nova-456', '10.0.0.1')['id']);
        $this->expectExceptionMessage('não vale mais');
        pb_password_reset($token, 'outra-senha-789', 'outra-senha-789');
    }

    public function test_forgot_password_says_nothing_about_unknown_emails(): void
    {
        $this->assertNull($this->resetLink('ninguem@example.com'));
        $this->assertSame([], $GLOBALS['pb_sent_mail']);
        $this->assertSame(0, (int) pb_db()->query('SELECT COUNT(*) FROM ' . pb_table('password_resets'))->fetchColumn());
    }

    public function test_reset_link_checks_the_password_and_expires(): void
    {
        $token = $this->resetLink('ana@example.com');
        foreach ([['curta', 'curta', '8 caracteres'], ['senha-nova-456', 'senha-nova-457', 'não são iguais']] as [$password, $repeat, $message]) {
            try {
                pb_password_reset($token, $password, $repeat);
                $this->fail("Expected: $message");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
        $this->assertNotNull(pb_password_reset_user($token), 'a mistake does not spend the link');
        $latest = $this->resetLink('ana@example.com');
        $this->assertNull(pb_password_reset_user($token), 'a new link replaces the old one');
        $this->assertNotNull(pb_password_reset_user($latest));
        pb_db()->exec('UPDATE ' . pb_table('password_resets') . ' SET expires_at = NOW() - INTERVAL 1 MINUTE');
        $this->assertNull(pb_password_reset_user($latest), 'it expires after an hour');
    }

    public function test_forgot_password_requests_are_limited_per_ip(): void
    {
        for ($i = 0; $i < PB_LOGIN_MAX_FAILURES; $i++) {
            $this->resetLink('ninguem@example.com', '10.0.0.9');
        }
        $this->assertNotNull($this->resetLink('ana@example.com', '10.0.0.8'), 'another IP is not affected');
        $this->expectExceptionMessage('Muitas tentativas');
        $this->resetLink('ana@example.com', '10.0.0.9');
    }

    public function test_roles(): void
    {
        $admin = ['role' => 'admin'];
        $editor = ['role' => 'editor'];
        $unknown = ['role' => 'hacker'];

        $this->assertTrue(pb_has_role($admin, 'admin'));
        $this->assertTrue(pb_has_role($admin, 'editor'));
        $this->assertTrue(pb_has_role($editor, 'editor'));
        $this->assertFalse(pb_has_role($editor, 'admin'));
        $this->assertFalse(pb_has_role($unknown, 'editor'));
        $this->assertFalse(pb_has_role($admin, 'no-such-role'));
    }

    public static function invalidUsers(): array
    {
        return [
            'empty name' => ['', 'x@example.com', 'senha-de-teste', 'editor'],
            'bad e-mail' => ['Bia', 'bia-sem-arroba', 'senha-de-teste', 'editor'],
            'short password' => ['Bia', 'bia@example.com', '1234567', 'editor'],
            'unknown role' => ['Bia', 'bia@example.com', 'senha-de-teste', 'superadmin'],
            'duplicate e-mail' => ['Outra Ana', 'ANA@example.com', 'senha-de-teste', 'editor'],
        ];
    }

    #[DataProvider('invalidUsers')]
    public function test_invalid_users_are_rejected(string $name, string $email, string $password, string $role): void
    {
        $this->expectException(InvalidArgumentException::class);
        pb_create_user($name, $email, $password, $role);
    }

    public function test_users_cannot_delete_themselves_but_can_delete_others(): void
    {
        $editorId = pb_create_user('Bia', 'bia@example.com', 'senha-de-teste', 'editor');

        pb_delete_user($editorId, $this->adminId);
        $this->assertNull(pb_find_user($editorId));

        $this->expectException(InvalidArgumentException::class);
        pb_delete_user($this->adminId, $this->adminId);
    }

    public function test_admin_can_reset_a_password_and_change_a_role(): void
    {
        $editorId = pb_create_user('Bia', 'bia@example.com', 'senha-de-teste', 'editor');

        pb_update_user($editorId, 'Bia Souza', 'bia@example.com', 'admin', 'nova-senha-123');
        $this->assertSame('admin', pb_find_user($editorId)['role']);
        $this->assertSame($editorId, (int) pb_login('bia@example.com', 'nova-senha-123', '10.0.0.9')['id']);

        pb_update_user($editorId, 'Bia Souza', 'bia@example.com', 'admin'); // empty password keeps the current one
        $this->assertSame($editorId, (int) pb_login('bia@example.com', 'nova-senha-123', '10.0.0.9')['id']);

        $this->expectExceptionMessage('Já existe um usuário com este e-mail.');
        pb_update_user($editorId, 'Bia', 'ana@example.com', 'editor');
    }

    public function test_own_account_changes_need_the_current_password(): void
    {
        $ana = pb_find_user($this->adminId);
        try {
            pb_update_own_account($ana, 'errada', 'Ana', 'ana@example.com', 'outra-senha-123');
            $this->fail('Changed without the current password');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('A senha atual não confere.', $e->getMessage());
        }

        pb_update_own_account($ana, 'senha-de-teste-123', 'Ana Lima', 'ana@example.com', 'outra-senha-123');
        $this->assertSame('Ana Lima', pb_find_user($this->adminId)['name']);
        $this->assertSame('admin', pb_find_user($this->adminId)['role'], 'own role never changes here');
        $this->assertSame($this->adminId, (int) pb_login('ana@example.com', 'outra-senha-123', '10.0.0.9')['id']);
    }

    public function test_csrf_token_check(): void
    {
        $token = pb_csrf_token();

        $this->assertTrue(pb_csrf_valid($token));
        $this->assertFalse(pb_csrf_valid('forjado'));
        $this->assertFalse(pb_csrf_valid(null));
        $this->assertFalse(pb_csrf_valid([$token]));
    }

    public function test_escaping_and_paths(): void
    {
        $this->assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', e('<script>alert("x")</script>'));

        $_SERVER['SCRIPT_NAME'] = '/site/index.php';
        $_SERVER['REQUEST_URI'] = '/site/admin/users?page=2';
        $this->assertSame('/admin/users', pb_request_path());
        $this->assertSame('/site/admin', pb_url('/admin'));
    }

    private function failLogins(int $times, string $ip): void
    {
        for ($i = 0; $i < $times; $i++) {
            try {
                pb_login('ana@example.com', 'errada', $ip);
            } catch (InvalidArgumentException) {
            }
        }
    }
}
