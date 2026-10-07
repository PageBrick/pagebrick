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

    public function test_wrong_database_password_gives_a_readable_error(): void
    {
        $this->expectExceptionMessage('Não consegui conectar ao banco de dados');
        pb_install($this->input(['db_pass' => 'errada']), $this->root);
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
