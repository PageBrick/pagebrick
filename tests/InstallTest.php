<?php

use PHPUnit\Framework\TestCase;

final class InstallTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $this->root = sys_get_temp_dir() . '/pb-install-' . bin2hex(random_bytes(4));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/config.php');
        @rmdir($this->root);
    }

    private function input(array $override = []): array
    {
        $db = pb_test_db();
        return $override + [
            'site_title' => 'Padaria Exemplo',
            'db_host' => $db['host'],
            'db_name' => $db['name'],
            'db_user' => $db['user'],
            'db_pass' => $db['pass'],
            'db_prefix' => 'pb_',
            'admin_name' => 'Ana',
            'admin_email' => 'ana@example.com',
            'admin_password' => 'senha-de-teste-123',
        ];
    }

    private function tables(): array
    {
        return pb_db_connect(pb_test_db())->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    }

    public function test_install_prepares_database_and_writes_config(): void
    {
        $result = pb_install($this->input(), $this->root);

        $this->assertTrue($result['written']);
        $config = require $this->root . '/config.php';
        $this->assertSame('pb_', $config['db']['prefix']);
        $this->assertSame('Padaria Exemplo', pb_option('site_title'));
        $this->assertSame(array_key_last(pb_migrations()), pb_installed_version());
        $this->assertSame('admin', pb_find_user($result['admin_id'])['role']);
        $this->assertTrue(pb_plugin_states()['contact-form']['active'], 'the contact form comes switched on');
    }

    public function test_install_refuses_to_overwrite_an_existing_installation(): void
    {
        pb_install($this->input(), $this->root);

        $this->expectExceptionMessage('Já existe um PageBrick instalado');
        pb_install($this->input(['admin_email' => 'intruso@example.com']), $this->root);
    }

    public function test_install_rejects_unsafe_table_prefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        pb_install($this->input(['db_prefix' => 'x; DROP TABLE users']), $this->root);
    }

    public function test_invalid_admin_is_rejected_before_touching_the_database(): void
    {
        try {
            pb_install($this->input(['admin_email' => 'nao-e-email']), $this->root);
            $this->fail('Expected an exception');
        } catch (InvalidArgumentException) {
        }
        $this->assertSame([], $this->tables());
        $this->assertFileDoesNotExist($this->root . '/config.php');
        $this->assertNull($GLOBALS['pb_config']);
    }

    public function test_database_errors_say_what_to_do(): void
    {
        $error = function (array $override): string {
            try {
                pb_install_db($this->input($override));
                return '';
            } catch (InvalidArgumentException $e) {
                return $e->getMessage();
            }
        };
        $this->assertSame('O usuário ou a senha do banco não conferem.', $error(['db_pass' => 'errada']));
        $this->assertStringContainsString('O banco "nao_existe" não existe', $error(['db_name' => 'nao_existe']));
        $this->assertStringContainsString('Não encontrei o servidor de banco "nao-existe.invalid"', $error(['db_host' => 'nao-existe.invalid']));
        $this->assertSame('', $error([]));
    }

    /** Renders the installer like a request would: [status, html]. */
    private function installer(string $method, int $step = 0, array $post = [], string $path = '/'): array
    {
        $GLOBALS['pb_config'] = null;
        $_GET = $step ? ['step' => (string) $step] : [];
        $_POST = $post;
        $_SERVER['REQUEST_URI'] = $path;
        http_response_code(200);
        ob_start();
        pb_install_page($method);
        $html = ob_get_clean();
        $_POST = [];
        return [http_response_code(), $html];
    }

    public function test_the_installer_walks_through_four_steps_like_wordpress(): void
    {
        $_SESSION = [];
        $this->assertStringContainsString('English', $this->installer('GET')[1]);
        $check = $this->installer('GET', 2)[1];
        $this->assertStringContainsString('Conferência do servidor', $check);
        $this->assertStringContainsString('Vamos lá', $check, 'nothing required is missing in the test server');
        $this->assertSame('{"rewrite": true}', $this->installer('GET', 0, [], '/install-check')[1]);

        $this->assertStringContainsString('Testar a conexão', $this->installer('GET', 4)[1], 'step 4 needs a working database first');
        $db = pb_test_db();
        [$status, $html] = $this->installer('POST', 3, ['db_host' => $db['host'], 'db_name' => $db['name'], 'db_user' => $db['user'], 'db_pass' => 'errada']);
        $this->assertSame(422, $status);
        $this->assertStringContainsString('não conferem', $html);
        $this->assertStringContainsString('value="' . $db['name'] . '"', $html, 'what was typed stays, except the password');

        $html = $this->installer('POST', 3, ['db_host' => $db['host'], 'db_name' => $db['name'], 'db_user' => $db['user'], 'db_pass' => $db['pass']])[1];
        $this->assertStringContainsString('Tudo certo com o banco de dados', $html);
        $this->assertStringContainsString('Gerar senha forte', $html);
        $this->assertSame($db['name'], $_SESSION['pb_install_db']['name']);
        $_SESSION = [];
        http_response_code(200);
    }

    public function test_unwritable_folder_returns_config_for_manual_creation(): void
    {
        $result = pb_install($this->input(), $this->root . '/pasta-que-nao-existe');

        $this->assertFalse($result['written']);
        $this->assertStringContainsString("'prefix' => 'pb_'", $result['php']);
    }

    public function test_migrations_can_run_twice(): void
    {
        $GLOBALS['pb_config'] = ['db' => pb_test_db()];
        pb_migrate();
        pb_migrate();
        $this->assertSame(array_key_last(pb_migrations()), pb_installed_version());
    }
}
