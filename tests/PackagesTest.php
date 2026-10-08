<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Installing from .zip and from the signed catalog, backups, rollback and PageBrick's own update. */
final class PackagesTest extends TestCase
{
    private string $dir;
    private string $keys;

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $this->dir = sys_get_temp_dir() . '/pb-pkg-' . bin2hex(random_bytes(4));
        foreach (['plugins', 'themes', 'backups', 'downloads'] as $folder) {
            mkdir("$this->dir/$folder", 0777, true);
        }
        $this->keys = sodium_crypto_sign_keypair();
        $GLOBALS['pb_config'] = pb_test_config([
            'plugins_dir' => "$this->dir/plugins",
            'themes_dir' => "$this->dir/themes",
            'backups_dir' => "$this->dir/backups",
            'trusted_keys' => [base64_encode(sodium_crypto_sign_publickey($this->keys))],
            'allow_insecure_urls' => true,
            'catalog_url' => "$this->dir/downloads/catalog.json",
        ]);
        pb_migrate();
    }

    protected function tearDown(): void
    {
        pb_rmtree($this->dir);
    }

    /** A .zip with [path => contents]. */
    private function zip(array $files, string $name = 'package.zip'): string
    {
        $file = "$this->dir/downloads/$name";
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $path => $contents) {
            $zip->addFromString($path, $contents);
        }
        $zip->close();
        return $file;
    }

    private function plugin(string $slug, string $version, string $code = '<?php'): array
    {
        return [
            "$slug/plugin.json" => json_encode(['name' => ucfirst($slug), 'version' => $version, 'api' => 1]),
            "$slug/plugin.php" => $code,
        ];
    }

    /** A catalog entry for $file, signed with $keys (the trusted pair by default). */
    private function entry(string $slug, string $version, string $file, ?string $keys = null): array
    {
        $sha256 = hash_file('sha256', $file);
        $secret = sodium_crypto_sign_secretkey($keys ?? $this->keys);
        return ['slug' => $slug, 'name' => ucfirst($slug), 'version' => $version, 'api' => 1, 'url' => $file, 'sha256' => $sha256,
            'signature' => base64_encode(sodium_crypto_sign_detached(pb_package_message('plugin', $slug, $version, $sha256), $secret))];
    }

    private function publish(array $plugins, ?array $core = null): void
    {
        file_put_contents("$this->dir/downloads/catalog.json", json_encode(['format' => 1, 'plugins' => $plugins, 'core' => $core]));
        pb_catalog(true);
    }

    public static function dangerousZips(): array
    {
        $manifest = json_encode(['name' => 'X', 'version' => '1.0.0', 'api' => 1]);
        return [
            'path escaping the folder' => [['evil/plugin.json' => $manifest, 'evil/plugin.php' => '<?php', 'evil/../../escape.php' => 'x'], 'caminhos inválidos'],
            'loose files, no folder' => [['plugin.json' => $manifest, 'plugin.php' => '<?php'], 'única pasta principal'],
            'two main folders' => [['a/plugin.json' => $manifest, 'a/plugin.php' => '<?php', 'b/x.php' => 'x'], 'única pasta principal'],
            'server config file' => [['evil/plugin.json' => $manifest, 'evil/plugin.php' => '<?php', 'evil/.htaccess' => 'SetHandler x'], 'configuração do servidor'],
            'no manifest' => [['evil/readme.txt' => 'oi'], 'plugin.json'],
            'bad folder name' => [['Meu Plugin/plugin.json' => $manifest, 'Meu Plugin/plugin.php' => '<?php'], 'letras minúsculas'],
        ];
    }

    #[DataProvider('dangerousZips')]
    public function test_dangerous_or_malformed_zips_are_refused(array $files, string $reason): void
    {
        $this->expectExceptionMessage($reason);
        pb_install_package($this->zip($files), 'plugin');
    }

    public function test_links_inside_a_zip_are_refused(): void
    {
        $file = $this->zip($this->plugin('evil', '1.0.0') + ['evil/link' => '/etc/passwd']);
        $zip = new ZipArchive();
        $zip->open($file);
        $zip->setExternalAttributesName('evil/link', ZipArchive::OPSYS_UNIX, 0120777 << 16);
        $zip->close();

        $this->expectExceptionMessage('atalhos');
        pb_install_package($file, 'plugin');
    }

    public function test_a_file_that_is_not_a_zip_is_refused(): void
    {
        file_put_contents("$this->dir/downloads/fake.zip", 'isto não é um zip');
        $this->expectExceptionMessage('não é um .zip válido');
        pb_install_package("$this->dir/downloads/fake.zip", 'plugin');
    }

    public function test_replacing_a_plugin_keeps_a_backup_and_restore_brings_it_back(): void
    {
        $first = pb_install_package($this->zip($this->plugin('good', '1.0.0')), 'plugin');
        $this->assertNull($first['backup']);

        $second = pb_install_package($this->zip($this->plugin('good', '2.0.0')), 'plugin');
        $this->assertFileExists($second['backup']);
        $this->assertSame('2.0.0', pb_package_version('plugin', 'good'));
        $this->assertSame('1.0.0', pb_backups('plugin_good_')[0]['version']);

        pb_restore_package('plugin', 'good');
        $this->assertSame('1.0.0', pb_package_version('plugin', 'good'));

        foreach (['3.0.0', '4.0.0', '5.0.0', '6.0.0'] as $version) {
            pb_install_package($this->zip($this->plugin('good', $version)), 'plugin');
        }
        $this->assertCount(PB_BACKUPS_KEPT, pb_backups('plugin_good_'), 'old backups are pruned');
        $this->assertSame([], glob("$this->dir/plugins/.tmp-*"), 'no leftovers');
    }

    public function test_backups_of_similar_names_do_not_mix(): void
    {
        pb_install_package($this->zip($this->plugin('news', '1.0.0')), 'plugin');
        pb_install_package($this->zip($this->plugin('news', '1.1.0')), 'plugin');
        pb_install_package($this->zip($this->plugin('news-extra', '9.0.0')), 'plugin');
        pb_install_package($this->zip($this->plugin('news-extra', '9.1.0')), 'plugin');

        $this->assertSame(['1.0.0'], array_column(pb_backups('plugin_news_'), 'version'));
    }

    public function test_catalog_installs_only_correctly_signed_packages(): void
    {
        $file = $this->zip($this->plugin('good', '1.0.0'), 'good-1.0.0.zip');
        $good = $this->entry('good', '1.0.0', $file);

        $refused = [
            'changed after signing' => [['sha256' => hash('sha256', 'outro conteúdo')] + $good, 'não confere'],
            'signed by an unknown key' => [$this->entry('good', '1.0.0', $file, sodium_crypto_sign_keypair()), 'assinatura'],
            'signature of another version' => [['version' => '2.0.0'] + $good, 'assinatura'],
            'paid package' => [['price' => 'R$ 99'] + $good, 'pago'],
            'other API version' => [['api' => 2] + $good, 'outra versão'],
        ];
        foreach ($refused as $case => [$entry, $reason]) {
            $this->publish([$entry]);
            try {
                pb_install_from_catalog('plugin', 'good');
                $this->fail("Installed: $case");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($reason, $e->getMessage(), $case);
            }
            $this->assertDirectoryDoesNotExist("$this->dir/plugins/good", $case);
        }

        $this->publish([$good]);
        pb_install_from_catalog('plugin', 'good');
        $this->assertSame('1.0.0', pb_package_version('plugin', 'good'));
    }

    public function test_a_signed_zip_with_another_folder_inside_is_refused(): void
    {
        $file = $this->zip($this->plugin('other', '1.0.0'), 'good-1.0.0.zip');
        $this->publish([$this->entry('good', '1.0.0', $file)]);

        $this->expectExceptionMessage('não corresponde');
        pb_install_from_catalog('plugin', 'good');
    }

    public function test_only_https_downloads_outside_development(): void
    {
        $GLOBALS['pb_config']['allow_insecure_urls'] = false;
        $this->expectExceptionMessage('só https');
        pb_download('http://example.com/plugin.zip', 1000);
    }

    public function test_a_broken_update_puts_the_previous_version_back_by_itself(): void
    {
        $v1 = $this->zip($this->plugin('good', '1.0.0', '<?php pb_add_filter("slot:test", fn(string $h) => $h . "v1");'), 'good-1.0.0.zip');
        $this->publish([$this->entry('good', '1.0.0', $v1)]);
        pb_install_from_catalog('plugin', 'good');
        pb_activate_plugin('good');

        $v2 = $this->zip($this->plugin('good', '2.0.0', '<?php throw new RuntimeException("v2 quebrada");'), 'good-2.0.0.zip');
        $this->publish([$this->entry('good', '2.0.0', $v2)]);
        $this->assertArrayHasKey('good', pb_available_updates()['plugin']);
        pb_update_plugin('good');

        pb_test_new_request();
        pb_load_plugins(); // first run of v2: it breaks
        $state = pb_plugin_states()['good'];
        $this->assertTrue($state['active']);
        $this->assertStringContainsString('anterior foi restaurada', $state['error']);
        $this->assertSame('1.0.0', pb_package_version('plugin', 'good'));

        pb_test_new_request();
        pb_load_plugins();
        $this->assertSame('v1', pb_slot('test'), 'next request runs the restored version');
    }

    public function test_pagebrick_update_replaces_only_its_own_files_and_can_be_undone(): void
    {
        $root = "$this->dir/site";
        foreach (['core/bootstrap.php' => 'velho', 'vendor/lib.php' => 'velho', 'index.php' => 'velho', 'config.php' => 'segredo',
                     '.htaccess' => 'meu', 'content/themes/meu.txt' => 'meu'] as $path => $contents) {
            @mkdir(dirname("$root/$path"), 0777, true);
            file_put_contents("$root/$path", $contents);
        }
        $release = $this->zip([
            'pagebrick/core/bootstrap.php' => 'novo', 'pagebrick/core/novo.php' => 'novo', 'pagebrick/vendor/lib.php' => 'novo',
            'pagebrick/index.php' => 'novo', 'pagebrick/config.php' => 'invadido', 'pagebrick/.htaccess' => 'trocado',
            'pagebrick/content/themes/meu.txt' => 'trocado',
        ], 'pagebrick-9.0.0.zip');

        $backup = pb_apply_core_package($release, $root, '1.0.0');

        $this->assertSame('novo', file_get_contents("$root/core/bootstrap.php"));
        $this->assertFileExists("$root/core/novo.php");
        $this->assertSame('novo', file_get_contents("$root/vendor/lib.php"));
        $this->assertSame('novo', file_get_contents("$root/index.php"));
        $this->assertSame('segredo', file_get_contents("$root/config.php"), 'config.php is never touched');
        $this->assertSame('meu', file_get_contents("$root/.htaccess"), '.htaccess is never touched');
        $this->assertSame('meu', file_get_contents("$root/content/themes/meu.txt"), 'content/ is never touched');
        $this->assertFileExists($backup);

        pb_restore_core($root);
        $this->assertSame('velho', file_get_contents("$root/core/bootstrap.php"));
        $this->assertFileDoesNotExist("$root/core/novo.php");
        $this->assertSame('velho', file_get_contents("$root/index.php"));
    }

    public function test_a_zip_that_is_not_pagebrick_is_not_applied_as_an_update(): void
    {
        $this->expectExceptionMessage('não é uma versão do PageBrick');
        pb_apply_core_package($this->zip(['outra-coisa/index.php' => 'x']), "$this->dir/site", '1.0.0');
    }

    public function test_core_updates_are_announced_from_the_catalog(): void
    {
        $this->publish([], ['version' => '99.0.0', 'url' => 'x', 'sha256' => 'x', 'signature' => 'x']);
        $this->assertSame('99.0.0', pb_available_updates()['core']['version']);
    }

    public function test_two_installations_at_the_same_time_are_not_allowed(): void
    {
        $lock = fopen("$this->dir/backups/.lock", 'c');
        flock($lock, LOCK_EX);
        try {
            $this->expectExceptionMessage('em andamento');
            pb_install_package($this->zip($this->plugin('good', '1.0.0')), 'plugin');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function test_the_maintainer_tool_signs_packages_the_site_accepts(): void
    {
        $keyFile = "$this->dir/key";
        $tool = PB_ROOT . '/tools/pagebrick.php';
        exec('php ' . escapeshellarg($tool) . ' keygen ' . escapeshellarg($keyFile), $out, $code);
        $this->assertSame(0, $code);
        $GLOBALS['pb_config']['trusted_keys'] = [trim(end($out))];

        mkdir("$this->dir/src/good", 0777, true);
        file_put_contents("$this->dir/src/good/plugin.json", json_encode(['name' => 'Good', 'version' => '1.2.0', 'api' => 1]));
        file_put_contents("$this->dir/src/good/plugin.php", '<?php');
        $json = shell_exec('php ' . escapeshellarg($tool) . ' package plugin ' . escapeshellarg("$this->dir/src/good") . ' '
            . escapeshellarg($keyFile) . ' ' . escapeshellarg("$this->dir/downloads") . ' ' . escapeshellarg("$this->dir/downloads") . ' 2>/dev/null');
        $this->publish([json_decode($json, true)]);

        pb_install_from_catalog('plugin', 'good');
        $this->assertSame('1.2.0', pb_package_version('plugin', 'good'));
    }
}
