<?php

use PHPUnit\Framework\TestCase;

require_once PB_ROOT . '/tools/api.php';

/**
 * The guarantee that updating PageBrick doesn't break sites already built on it:
 * 1. the public API keeps its promise (tests/fixtures/api-v1.json);
 * 2. a site built on 1.0 (tests/fixtures/sites/v1.0) keeps working;
 * 3. the panel refuses an update the site isn't ready for;
 * 4. after an update, the first request checks the site and goes back if anything broke;
 * 5. index.php restores the backup if the new version can't even start.
 */
final class CompatibilityTest extends TestCase
{
    private const FROZEN_SITE = __DIR__ . '/fixtures/sites/v1.0';
    /** Fingerprint of the frozen 1.0 site. If this fails, someone edited it: undo that and fix PageBrick instead. */
    private const FROZEN_SITE_SHA256 = 'ae2f1e633281050614a429704d578dbe3038861097f331772ed2308c417ae8ec';

    private string $dir;

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $this->dir = sys_get_temp_dir() . '/pb-compat-' . bin2hex(random_bytes(4));
        pb_test_copy_dir(PB_ROOT . '/content/themes/default', "$this->dir/themes/default");
        pb_test_copy_dir(self::FROZEN_SITE . '/themes/agencia', "$this->dir/themes/agencia");
        pb_test_copy_dir(self::FROZEN_SITE . '/plugins/agencia-extras', "$this->dir/plugins/agencia-extras");
        // A link, not a copy: PHP must see the very same functions.php other tests already loaded.
        symlink(PB_ROOT . '/content/plugins/contact-form', "$this->dir/plugins/contact-form");
        pb_test_copy_dir(__DIR__ . '/fixtures/plugins/breaks-pages', "$this->dir/plugins/breaks-pages");
        mkdir("$this->dir/backups");
        $GLOBALS['pb_config'] = pb_test_config([
            'themes_dir' => "$this->dir/themes", 'plugins_dir' => "$this->dir/plugins",
            'backups_dir' => "$this->dir/backups", 'core_root' => "$this->dir/site",
        ]);
        pb_migrate();
        pb_set_option('site_title', 'Agência Cliente');
        pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
        pb_seed_demo();
        unset($GLOBALS['break_pages']);
    }

    protected function tearDown(): void
    {
        pb_rmtree($this->dir);
    }

    private function visit(string $path): string
    {
        pb_test_new_request();
        pb_load_plugins();
        $_SERVER['REQUEST_URI'] = $path;
        ob_start();
        pb_public('GET', $path);
        return ob_get_clean();
    }

    // ---------------------------------------------------------------- 1. the promise

    public function test_the_public_api_keeps_its_promise(): void
    {
        $frozen = json_decode((string) file_get_contents(__DIR__ . '/fixtures/api-v1.json'), true);
        $this->assertSame([], pb_api_breaks($frozen, pb_api_describe()), 'Something themes or plugins rely on was removed or changed. Keep it working (deprecate, don\'t remove).');
    }

    public function test_the_promise_checker_catches_breaking_changes(): void
    {
        $frozen = json_decode((string) file_get_contents(__DIR__ . '/fixtures/api-v1.json'), true);
        $now = $frozen;
        unset($now['functions']['pb_slot']);
        $now['functions']['pb_url']['params'][0]['name'] = 'caminho';
        $now['classes']['PbValue']['img']['params'][] = ['name' => 'extra', 'type' => 'string', 'optional' => false];
        $now['functions']['pb_menu']['params'][0]['type'] = 'int';
        unset($now['standard']['templates.home.hero.title']);
        $now['functions']['e']['params'][0]['type'] = 'mixed'; // widening is fine

        $breaks = implode("\n", pb_api_breaks($frozen, $now));
        $this->assertStringContainsString('pb_slot() was removed', $breaks);
        $this->assertStringContainsString('$path was renamed', $breaks);
        $this->assertStringContainsString('$extra must be optional', $breaks);
        $this->assertStringContainsString("no longer accepts 'string'", $breaks);
        $this->assertStringContainsString('templates.home.hero.title was removed', $breaks);
        $this->assertStringNotContainsString('function e()', $breaks);
    }

    public function test_every_documented_hook_is_still_fired_by_the_core(): void
    {
        $source = implode("\n", array_map('file_get_contents', glob(PB_ROOT . '/core/*.php')));
        foreach (json_decode((string) file_get_contents(__DIR__ . '/fixtures/api-v1.json'), true)['hooks'] as $hook) {
            $this->assertMatchesRegularExpression("/pb_(apply_filters|do_action)\\('$hook'/", $source, "hook '$hook'");
        }
    }

    // ---------------------------------------------------------------- 2. a site built on 1.0

    public function test_the_frozen_site_was_not_edited(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::FROZEN_SITE, FilesystemIterator::SKIP_DOTS));
        $hashes = [];
        foreach ($files as $file) {
            $hashes[str_replace('\\', '/', substr($file->getPathname(), strlen(self::FROZEN_SITE)))] = hash_file('sha256', $file->getPathname());
        }
        ksort($hashes);
        $this->assertSame(self::FROZEN_SITE_SHA256, hash('sha256', json_encode($hashes)), 'tests/fixtures/sites/v1.0 must never change (see its README)');
    }

    /** The 1.0 site as its developer left it: their theme, their plugin, an official plugin and their own pages. */
    private function buildAgencySite(): ?int
    {
        pb_activate_theme('agencia');
        pb_activate_plugin('contact-form');
        pb_activate_plugin('agencia-extras');
        pb_save_plugin_settings('agencia-extras', ['banner' => 'Promoção de outubro', 'notify' => 'leads@agencia.example']);
        pb_save_settings(pb_collect_fields(pb_settings_fields(), [
            'contact' => ['whatsapp' => '(19) 99999-8888', 'address' => 'Rua A, 10', 'email' => 'oi@cliente.example', 'phone' => '(19) 3333-4444'],
            'agency' => ['slogan' => 'Feito com carinho', 'accent' => '#0a7c66'],
        ]) + ['agency' => ['slogan' => 'Feito com carinho', 'accent' => '#0a7c66']]);
        $landing = pb_page_create('Campanha', 'landing', [
            'offer' => '20% de desconto', 'deadline' => 'sexta', 'perks' => [['title' => 'Entrega grátis']],
            'cta' => ['label' => 'Quero', 'link' => 'page:' . pb_home_page_id()],
        ], 'published');
        return $landing;
    }

    public function test_a_site_built_on_1_0_still_works(): void
    {
        $landing = $this->buildAgencySite();
        $home = $this->visit('/');
        // (The frozen plugin uses strtoupper(), which only changes letters without accents: "padrão" becomes "PADRãO".)
        foreach (['AGENCIA-LAYOUT', 'Diga aqui, em uma frase', 'data-home="yes"', 'AGENCIA-SLOT Promoção de outubro PADRãO', 'wa.me/5519999998888',
                     'Feito com carinho', 'meta name="agencia"', 'AGENCIA-FOOTER', 'Rua A, 10', 'mailto:oi@cliente.example'] as $expected) {
            $this->assertStringContainsString($expected, $home);
        }
        $this->assertStringContainsString('OFERTA: 20% de desconto até sexta', $this->visit('/campanha'));
        $this->assertStringContainsString('Entrega grátis', $this->visit('/campanha'));
        $this->assertStringContainsString('id="contato-form"', $this->visit('/contato'), 'official plugin inside the agency theme');
        $this->assertStringContainsString('Um parágrafo de apresentação', $this->visit('/sobre'));
        $offer = $this->visit('/agencia/oferta');
        $this->assertStringContainsString('AGENCIA-OFERTA', $offer);
        $this->assertStringContainsString('<p>limpo</p>', $offer);
        $this->assertStringContainsString('AGENCIA-404', $this->visit('/nao-existe'));
        $this->assertStringContainsString('/agencia/oferta', pb_sitemap_xml());
        $this->assertSame('Oferta da agência', pb_apply_filters('link_targets', [])['/agencia/oferta']);
        $this->assertSame(2, pb_plugin_states()['agencia-extras']['db'], 'plugin migrations ran');

        ob_start();
        ($GLOBALS['pb_admin_pages']['agencia']['handler'])();
        $this->assertStringContainsString('ADMIN-AGENCIA', ob_get_clean());

        $this->assertNull(pb_theme_states()['agencia']['error'] ?? null);
        $this->assertTrue(pb_plugin_states()['agencia-extras']['active']);
        $this->assertNotNull($landing);
    }

    /**
     * Front-end developers must be able to trust that an update never changes what their site delivers: the HTML
     * of its pages and the JSON of the content API. tests/fixtures/sites/v1.0-output holds the exact output of the
     * frozen site; any difference fails here.
     */
    public function test_updates_never_change_what_a_1_0_site_delivers(): void
    {
        $this->buildAgencySite();
        $outputs = ['campanha.html' => '/campanha', 'oferta.html' => '/agencia/oferta', 'nao-encontrada.html' => '/nao-existe', 'sitemap.html' => null,
            'api-site.json' => '/api/v1/site', 'api-pages.json' => '/api/v1/pages', 'api-inicio.json' => '/api/v1/pages/inicio',
            'api-campanha.json' => '/api/v1/pages/campanha', 'api-nao-existe.json' => '/api/v1/pages/nao-existe'];
        foreach ($outputs as $name => $path) {
            $output = $path === null ? pb_sitemap_xml() : $this->visit($path);
            // What changes on every run: file times, today's date and time, upload names, the test's temporary folder, form tokens.
            $output = preg_replace(['/\?v=\d+/', '/\d{4}-\d{2}-\d{2}/', '/\d{2}:\d{2}:\d{2}/', '#uploads/\d{4}/\d{2}/[0-9a-f]+#', '#"/[^"]*/themes/#', '/\b\d{10}\b/', '/\b[0-9a-f]{64}\b/'],
                ['?v=N', 'AAAA-MM-DD', 'HH:MM:SS', 'uploads/AAAA/MM/ARQUIVO', '"/content/themes/', 'HORA', 'ASSINATURA'], $output);
            $file = __DIR__ . "/fixtures/sites/v1.0-output/$name";
            if (!is_file($file)) {
                file_put_contents($file, $output); // first run only: these files are then frozen like the site
            }
            $this->assertSame(file_get_contents($file), $output, "What \"$name\" delivers changed. Every site built on PageBrick would change with it: "
                . 'undo the change in the core. (Only if the difference is in demo text from core/standard.php, delete the file and run again.)');
        }
    }

    // ---------------------------------------------------------------- 3. refusing an update the site isn't ready for

    public function test_an_update_that_would_break_the_site_is_refused(): void
    {
        pb_activate_theme('agencia');
        pb_activate_plugin('agencia-extras');

        $this->assertSame([], pb_core_update_blockers(['version' => '1.1.0', 'api' => [1], 'requires_php' => '8.2']));
        $blockers = implode("\n", pb_core_update_blockers(['version' => '2.0.0', 'api' => [2], 'requires_php' => '99.0']));
        $this->assertStringContainsString('PHP 99.0', $blockers);
        $this->assertStringContainsString('Extras da Agência', $blockers);
        $this->assertStringContainsString('Agência Exemplo', $blockers);
        $this->assertStringNotContainsString('Formulário de contato', $blockers, 'inactive plugins do not block');
    }

    // ---------------------------------------------------------------- 4. checking the site after an update

    /** A fake PageBrick folder with a backup of "1.0.0", then "1.1.0" in place, waiting for its first request. */
    private function pretendUpdated(): void
    {
        $root = "$this->dir/site";
        mkdir("$root/core", 0777, true);
        file_put_contents("$root/core/bootstrap.php", '<?php');
        file_put_contents("$root/core/versao.txt", '1.0.0');
        file_put_contents("$root/index.php", '<?php // 1.0.0');
        $backup = pb_backup_core($root, '1.0.0');
        file_put_contents("$root/core/versao.txt", '1.1.0');
        pb_set_option('core_update', json_encode(['from' => '1.0.0', 'to' => '1.1.0', 'backup' => $backup, 'at' => time()]));
    }

    private function firstRequestAfterUpdate(): ?string
    {
        pb_test_new_request();
        $states = ['plugins' => pb_option('plugins', '{}'), 'themes' => pb_option('themes', '{}')];
        pb_load_plugins();
        return pb_verify_core_update($states);
    }

    public function test_a_good_update_is_kept(): void
    {
        pb_activate_theme('agencia');
        pb_activate_plugin('agencia-extras');
        $this->pretendUpdated();

        $this->assertNull($this->firstRequestAfterUpdate());
        $this->assertSame('1.1.0', file_get_contents("$this->dir/site/core/versao.txt"));
        $this->assertNull(pb_pending_core_update());
        $this->assertTrue(json_decode(pb_option('core_update_result'), true)['ok']);
    }

    public function test_an_update_that_breaks_a_plugin_is_undone_with_everything_as_before(): void
    {
        pb_activate_plugin('breaks-pages');
        $this->pretendUpdated();
        $GLOBALS['break_pages'] = true; // the new version and this plugin don't get along

        $problem = $this->firstRequestAfterUpdate();
        $this->assertStringContainsString('incompatível com a versão nova', $problem);
        $this->assertSame('1.0.0', file_get_contents("$this->dir/site/core/versao.txt"), 'the previous version is back');
        $state = pb_plugin_states()['breaks-pages'];
        $this->assertTrue($state['active'], 'the plugin was not switched off: the update was undone instead');
        $this->assertNull($state['error']);
        $result = json_decode(pb_option('core_update_result'), true);
        $this->assertFalse($result['ok']);
        $this->assertSame('1.0.0', $result['from']);
    }

    public function test_an_update_that_breaks_the_theme_is_undone(): void
    {
        pb_activate_theme('agencia');
        $this->pretendUpdated();
        file_put_contents("$this->dir/themes/agencia/templates/page.php", '<?php echo pb_funcao_que_a_versao_nova_removeu();');

        $this->assertStringContainsString('pb_funcao_que_a_versao_nova_removeu', $this->firstRequestAfterUpdate());
        $this->assertSame('1.0.0', file_get_contents("$this->dir/site/core/versao.txt"));
        $this->assertSame('agencia', pb_option('theme'), 'the site keeps its theme');
    }

    public function test_a_check_cut_short_by_a_fatal_error_also_undoes_the_update(): void
    {
        $this->pretendUpdated();
        $pending = pb_pending_core_update();
        pb_set_option('core_update', json_encode($pending + ['verifying' => true])); // the previous request died mid-check

        $this->assertStringContainsString('interrompida', $this->firstRequestAfterUpdate());
        $this->assertSame('1.0.0', file_get_contents("$this->dir/site/core/versao.txt"));
    }

    // ---------------------------------------------------------------- 5. the safety net in index.php

    public function test_index_php_restores_the_backup_when_the_new_version_cannot_start(): void
    {
        $root = "$this->dir/site";
        mkdir("$root/core", 0777, true);
        mkdir("$root/content/backups", 0777, true);
        copy(PB_ROOT . '/index.php', "$root/index.php");
        // The backup: a "previous version" that works.
        $previous = "$this->dir/previous";
        mkdir("$previous/pagebrick/core", 0777, true);
        file_put_contents("$previous/pagebrick/core/bootstrap.php", '<?php function pb_handle_request() { echo "VERSAO-ANTERIOR"; }');
        copy(PB_ROOT . '/index.php', "$previous/pagebrick/index.php");
        $zip = new ZipArchive();
        $zip->open("$root/content/backups/core_0-1-0_20260101000000.zip", ZipArchive::CREATE);
        pb_zip_add_folder($zip, "$previous/pagebrick", 'pagebrick');
        $zip->close();
        // The update: a core that doesn't even compile.
        file_put_contents("$root/core/bootstrap.php", '<?php function quebrado( {');
        file_put_contents("$root/content/backups/update-pending.json", json_encode(['backup' => "$root/content/backups/core_0-1-0_20260101000000.zip", 'until' => time() + 3600]));

        $run = fn() => shell_exec('php ' . escapeshellarg("$root/index.php") . ' 2>&1');
        $this->assertStringContainsString('versão anterior foi restaurada', $run());
        $this->assertSame('VERSAO-ANTERIOR', $run(), 'the next request runs the restored version');
        $this->assertFileDoesNotExist("$root/content/backups/update-pending.json");
        $this->assertSame([], glob("$root/.restore-*"), 'no leftovers');
    }

    public function test_index_php_does_not_blame_the_core_for_a_broken_plugin(): void
    {
        $root = "$this->dir/site";
        mkdir("$root/core", 0777, true);
        mkdir("$root/content/plugins/ruim", 0777, true);
        mkdir("$root/content/backups", 0777, true);
        copy(PB_ROOT . '/index.php', "$root/index.php");
        file_put_contents("$root/content/plugins/ruim/plugin.php", '<?php function ruim( {');
        file_put_contents("$root/core/bootstrap.php", '<?php function pb_handle_request() { require PB_ROOT . "/content/plugins/ruim/plugin.php"; }');
        file_put_contents("$root/content/backups/update-pending.json", json_encode(['backup' => "$root/content/backups/x.zip", 'until' => time() + 3600]));

        $output = (string) shell_exec('php ' . escapeshellarg("$root/index.php") . ' 2>&1');
        $this->assertStringNotContainsString('restaurada', $output);
        $this->assertFileExists("$root/content/backups/update-pending.json");
    }
}
