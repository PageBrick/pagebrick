<?php

use PHPUnit\Framework\TestCase;

/**
 * The additional catalog: a second list of plugins and themes next to the public one, for what is not in the public store (the closed plugins of an agency).
 * Same signatures, same buttons; it can add packages and nothing else.
 */
final class ExtraCatalogTest extends TestCase
{
    private string $dir;
    private string $keys;

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $this->dir = sys_get_temp_dir() . '/pb-extra-' . bin2hex(random_bytes(4));
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
        $this->publish([$this->entry('blog', '1.0.0', $this->zip('blog', '1.0.0'))]);
    }

    protected function tearDown(): void
    {
        pb_rmtree($this->dir);
        $_SESSION = [];
        unset($GLOBALS['pb_theme']);
        pb_test_new_request();
    }

    /** A plugin .zip. */
    private function zip(string $slug, string $version): string
    {
        $file = "$this->dir/downloads/$slug-$version.zip";
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString("$slug/plugin.json", json_encode(['name' => ucfirst($slug), 'version' => $version, 'api' => 1]));
        $zip->addFromString("$slug/plugin.php", '<?php');
        $zip->close();
        return $file;
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
    }

    /** Writes the additional catalog's file and returns its address. */
    private function publishExtra(array $plugins, array $more = []): string
    {
        $file = "$this->dir/downloads/extra.json";
        file_put_contents($file, json_encode(['format' => 1, 'plugins' => $plugins] + $more));
        return $file;
    }

    private function slugs(): array
    {
        return array_column(pb_catalog()['plugins'], 'slug');
    }

    public function test_its_plugins_join_the_public_ones_and_install_like_any_other(): void
    {
        $url = $this->publishExtra([$this->entry('secreto', '1.0.0', $this->zip('secreto', '1.0.0'))]);
        $this->assertSame(1, pb_set_extra_catalog_url($url), 'it says how many it offers');
        $this->assertSame(['blog', 'secreto'], $this->slugs());
        $this->assertSame('secreto', pb_install_from_catalog('plugin', 'secreto')['slug']);
        $this->assertSame('1.0.0', pb_package_version('plugin', 'secreto'));
    }

    public function test_it_can_add_packages_and_nothing_else(): void
    {
        $this->publish([$this->entry('blog', '1.0.0', $this->zip('blog', '1.0.0'))], ['version' => '1.5.0', 'api' => [1], 'requires_php' => '8.2', 'url' => 'x', 'sha256' => 'x', 'signature' => 'x']);
        $url = $this->publishExtra([
            $this->entry('blog', '9.9.9', $this->zip('blog', '9.9.9')), // a package the public catalog has: its entry is the one that counts
            $this->entry('outro', '1.0.0', $this->zip('outro', '1.0.0')),
            'not an entry',
        ], ['core' => ['version' => '9.9.9', 'api' => [1], 'requires_php' => '8.2', 'url' => 'https://exemplo.com/x.zip', 'sha256' => 'x', 'signature' => 'x']]);
        pb_set_extra_catalog_url($url);
        $catalog = pb_catalog();
        $this->assertSame('1.5.0', $catalog['core']['version'], 'the core is the public catalog\'s');
        $this->assertSame(['blog', 'outro'], $this->slugs());
        $this->assertSame('1.0.0', pb_catalog_entry('plugin', 'blog')['version'], 'an official slug keeps its official entry');
        $this->assertNull(pb_available_updates()['core'], 'and no update of PageBrick can come from there');
    }

    public function test_a_package_it_offers_needs_the_projects_signature_like_any_other(): void
    {
        $other = sodium_crypto_sign_keypair();
        $url = $this->publishExtra([$this->entry('falso', '1.0.0', $this->zip('falso', '1.0.0'), $other)]);
        pb_set_extra_catalog_url($url);
        $this->expectException(InvalidArgumentException::class);
        pb_install_from_catalog('plugin', 'falso');
    }

    public function test_a_new_version_of_an_installed_plugin_is_offered_as_an_update(): void
    {
        $url = $this->publishExtra([$this->entry('secreto', '1.0.0', $this->zip('secreto', '1.0.0'))]);
        pb_set_extra_catalog_url($url);
        pb_install_from_catalog('plugin', 'secreto');
        $this->assertSame([], pb_available_updates()['plugin'], 'up to date: nothing is offered');

        $this->publishExtra([$this->entry('secreto', '1.1.0', $this->zip('secreto', '1.1.0'))]);
        pb_catalog(true); // the hourly check does this
        $this->assertSame('1.1.0', pb_available_updates()['plugin']['secreto']['version']);
        pb_install_from_catalog('plugin', 'secreto');
        $this->assertSame('1.1.0', pb_package_version('plugin', 'secreto'));
        $this->assertSame([], pb_available_updates()['plugin']);
    }

    public function test_each_catalog_keeps_its_own_last_good_copy(): void
    {
        $url = $this->publishExtra([$this->entry('secreto', '1.0.0', $this->zip('secreto', '1.0.0'))]);
        pb_set_extra_catalog_url($url);

        unlink($url); // the additional catalog stops answering: its plugins stay, with the reason
        $this->assertSame(['blog', 'secreto'], array_column(pb_catalog(true)['plugins'], 'slug'));
        $this->assertNotNull(pb_extra_catalog_error());
        $this->assertNull(pb_catalog_error(), 'the public one is fine');

        $this->publishExtra([$this->entry('secreto', '1.0.0', $this->zip('secreto', '1.0.0'))]);
        unlink("$this->dir/downloads/catalog.json"); // now the public one: it keeps its copy, the additional one still answers
        $this->assertSame(['blog', 'secreto'], array_column(pb_catalog(true)['plugins'], 'slug'));
        $this->assertNotNull(pb_catalog_error());
        $this->assertNull(pb_extra_catalog_error());
    }

    public function test_the_address_is_checked_before_it_is_saved(): void
    {
        $GLOBALS['pb_config']['allow_insecure_urls'] = false; // outside local development only https is taken
        foreach (['ftp://exemplo.com/c.json', 'http://exemplo.com/c.json', 'https://exemplo.com/um catalogo.json', 'exemplo.com/c.json', 'https://' . str_repeat('a', 700)] as $bad) {
            try {
                pb_set_extra_catalog_url($bad);
                $this->fail("accepted $bad");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('https://', $e->getMessage());
            }
        }
        $GLOBALS['pb_config']['allow_insecure_urls'] = true;
        file_put_contents("$this->dir/downloads/not-a-catalog.json", '{"hello": 1}');
        try {
            pb_set_extra_catalog_url("$this->dir/downloads/not-a-catalog.json");
            $this->fail('accepted a file that is not a catalog');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('não respondeu como esperado', $e->getMessage());
        }
        $this->assertSame('', pb_extra_catalog_url(), 'nothing was saved');
        $this->assertSame(['blog'], $this->slugs());

    }

    public function test_removing_it_takes_its_plugins_off_the_list_and_keeps_the_installed_ones(): void
    {
        pb_set_extra_catalog_url($this->publishExtra([$this->entry('secreto', '1.0.0', $this->zip('secreto', '1.0.0'))]));
        pb_install_from_catalog('plugin', 'secreto');
        $this->assertSame(0, pb_set_extra_catalog_url(''));
        $this->assertSame(['blog'], $this->slugs());
        $this->assertSame('', pb_extra_catalog_label());
        $this->assertSame('1.0.0', pb_package_version('plugin', 'secreto'), 'what is installed stays');
    }

    public function test_the_key_in_the_address_is_never_shown_again(): void
    {
        $GLOBALS['pb_config']['extra_catalog_url'] = 'http://127.0.0.1:9/c/CHAVE-SECRETA-123/catalog.json'; // nobody answers there: the screen has to say so without the address
        $this->assertSame('https://127.0.0.1/…', pb_extra_catalog_label());
        $_SESSION['user_id'] = pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start();
        pb_admin('GET', '/admin/updates');
        $html = ob_get_clean();
        $this->assertStringContainsString('https://127.0.0.1/…', $html);
        $this->assertStringNotContainsString('CHAVE-SECRETA', $html, 'the key travels in the address: it is a secret');
        $this->assertStringContainsString('Não consegui verificar o catálogo adicional', $html);
        $this->assertStringContainsString('definido no config.php', $html, 'an address set in config.php is not edited on the screen');
        $this->assertStringNotContainsString('name="extra_url"', $html);
    }

    public function test_the_screen_offers_the_field_to_administrators_only(): void
    {
        $admin = pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
        $editor = pb_create_user('Bia', 'bia@example.com', 'senha-de-teste-123', 'editor');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SESSION['user_id'] = $admin;
        ob_start();
        pb_admin('GET', '/admin/updates');
        $html = ob_get_clean();
        $this->assertStringContainsString('name="extra_url"', $html);
        $this->assertStringNotContainsString('value=""', str_contains($html, 'id="extra-url"') ? substr($html, strpos($html, 'id="extra-url"'), 160) : '', 'the field starts empty');

        $_SESSION['user_id'] = $editor;
        http_response_code(200);
        ob_start();
        pb_admin('GET', '/admin/updates');
        ob_end_clean();
        $this->assertSame(403, http_response_code(), 'an editor does not even open the screen');
    }
}
