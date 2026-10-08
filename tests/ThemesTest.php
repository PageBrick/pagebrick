<?php

use PHPUnit\Framework\TestCase;

/** Themes as packages: switching keeps content, broken themes are refused or replaced on the fly, preview is private. */
final class ThemesTest extends TestCase
{
    private static string $themes;

    public static function setUpBeforeClass(): void
    {
        self::$themes = sys_get_temp_dir() . '/pb-themes-' . bin2hex(random_bytes(4));
        pb_test_copy_dir(PB_ROOT . '/content/themes/default', self::$themes . '/default');
        foreach (['second', 'broken', 'flaky'] as $theme) {
            pb_test_copy_dir(__DIR__ . "/fixtures/themes/$theme", self::$themes . "/$theme");
        }
    }

    public static function tearDownAfterClass(): void
    {
        pb_rmtree(self::$themes);
    }

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config(['themes_dir' => self::$themes, 'backups_dir' => sys_get_temp_dir() . '/pb-themes-backups']);
        pb_migrate();
        pb_set_option('site_title', 'Padaria Exemplo');
        $this->adminId = pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
        pb_seed_demo();
        unset($GLOBALS['flaky_break']);
    }

    private int $adminId;

    private function visit(string $path): string
    {
        pb_test_new_request();
        $_SERVER['REQUEST_URI'] = $path;
        ob_start();
        pb_public('GET', $path);
        return ob_get_clean();
    }

    private function form(array $page, array $changes = []): array
    {
        return array_replace_recursive(['title' => $page['title'], 'slug' => $page['slug'], 'status' => $page['status'],
            'seo_title' => '', 'seo_description' => '', 'f' => []], $changes);
    }

    public function test_installed_themes_are_listed(): void
    {
        $themes = pb_themes_available();
        $this->assertSame(['broken', 'default', 'flaky', 'second'], array_keys($themes));
        $this->assertSame('PageBrick Padrão', $themes['default']['name']);
        $this->assertArrayNotHasKey('problem', $themes['second']);
    }

    public function test_switching_themes_keeps_every_piece_of_content(): void
    {
        pb_activate_theme('second');
        $about = $this->visit('/sobre');
        $this->assertStringContainsString('TEMA-SEGUNDO', $about);
        $this->assertStringContainsString('Um parágrafo de apresentação', $about);
        $this->assertStringContainsString('<h1>Início</h1>', $this->visit('/'));

        // The second theme adds a field of its own to simple pages.
        pb_test_new_request();
        $about = pb_page_by_slug('sobre');
        $this->assertArrayHasKey('extra', pb_template_fields('page'), "the theme's extra field shows up in the panel");
        pb_page_save($about['id'], $this->form($about, ['f' => $about['data'] + ['extra' => 'só do segundo tema']]), null);

        pb_activate_theme('default');
        $this->assertStringContainsString('Diga aqui, em uma frase', $this->visit('/'), 'standard content is all there');

        // Editing in a theme that doesn't know the extra field must not wipe it.
        pb_test_new_request();
        $about = pb_page_by_slug('sobre');
        $this->assertArrayNotHasKey('extra', pb_template_fields('page'));
        pb_page_save($about['id'], $this->form($about, ['f' => ['intro' => 'Nova introdução']]), null);

        pb_activate_theme('second');
        $html = $this->visit('/sobre');
        $this->assertStringContainsString('Nova introdução', $html);
        $this->assertStringContainsString('só do segundo tema', $html, 'the extra content came back with its theme');
    }

    public function test_a_theme_cannot_remove_or_change_standard_fields(): void
    {
        $merged = pb_merge_theme_definitions(['templates' => [
            'home' => ['label' => 'Outro nome', 'fields' => ['hero' => ['type' => 'text'], 'video' => ['type' => 'url']]],
            'landing' => ['label' => 'Campanha', 'fields' => ['offer' => ['type' => 'text']]],
        ]]);
        $this->assertSame('group', $merged['templates']['home']['fields']['hero']['type'], 'standard field wins');
        $this->assertSame('Página inicial', $merged['templates']['home']['label']);
        $this->assertArrayHasKey('video', $merged['templates']['home']['fields'], 'extra field added');
        $this->assertArrayHasKey('landing', $merged['templates'], 'extra page type added');
        $this->assertArrayHasKey('identity', $merged['settings']);
    }

    public function test_a_theme_missing_a_required_template_cannot_be_activated(): void
    {
        unlink(self::$themes . '/flaky/templates/contact.php');
        try {
            $this->assertStringContainsString('"contact"', pb_themes_available()['flaky']['problem']);
            $this->expectException(InvalidArgumentException::class);
            pb_activate_theme('flaky');
        } finally {
            file_put_contents(self::$themes . '/flaky/templates/contact.php', '<h1><?= $title ?></h1>');
        }
    }

    public function test_a_theme_that_cannot_show_the_site_is_not_activated(): void
    {
        try {
            pb_activate_theme('broken');
            $this->fail('broken theme activated');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('layout quebrado', $e->getMessage());
        }
        $this->assertSame('default', pb_option('theme', 'default'));
    }

    public function test_a_theme_that_breaks_later_is_replaced_by_the_default_for_that_page(): void
    {
        pb_activate_theme('flaky');
        $GLOBALS['flaky_break'] = true;

        $html = $this->visit('/sobre');
        $this->assertStringNotContainsString('TEMA-INSTAVEL', $html);
        $this->assertStringContainsString('class="site-header"', $html, 'the visitor gets the page in the default theme');
        $this->assertStringContainsString('Um parágrafo de apresentação', $html);
        $this->assertStringContainsString('tema instavel', pb_theme_states()['flaky']['error']);
        $this->assertSame('flaky', pb_option('theme'), 'the choice stays; the panel tells the owner');
    }

    public function test_preview_is_seen_only_by_administrators_and_only_on_the_site(): void
    {
        $_SESSION['pb_preview_theme'] = 'second';
        $GLOBALS['pb_public_request'] = true;
        $this->assertSame('default', pb_active_theme_slug(), 'visitors (not logged in) never see it');

        $_SESSION['user_id'] = $this->adminId;
        $this->assertSame('second', pb_active_theme_slug());
        $this->assertStringContainsString('Pré-visualizando', $this->visit('/sobre'));

        $GLOBALS['pb_public_request'] = false;
        $this->assertSame('default', pb_active_theme_slug(), 'the panel keeps the active theme');
    }

    public function test_a_theme_can_bring_ready_made_content(): void
    {
        $this->expectExceptionMessage('não tem conteúdo para importar');
        try {
            pb_admin_import_theme_demo('second'); // only the active theme's content can be imported
        } catch (InvalidArgumentException) {
        }
        pb_activate_theme('second');
        @mkdir(self::$themes . '/second/demo');
        imagepng(imagecreatetruecolor(8, 8), self::$themes . '/second/demo/foto.png');
        $before = pb_page_by_slug('inicio');

        pb_test_new_request();
        $_SESSION = ['user_id' => $this->adminId];
        pb_admin_import_theme_demo('second');

        $home = pb_page_by_slug('inicio');
        $this->assertSame($before['id'], $home['id'], 'an existing address gets the new content');
        $this->assertSame('Início do tema', $home['title']);
        $this->assertSame('TEMA-SEGUNDO-HERO', $home['data']['hero']['title']);
        $this->assertNotSame('', $home['data']['hero']['image'], 'the photo came along');
        $this->assertCount(1, pb_page_revisions($home['id']), 'what it had before is in the history');
        $news = pb_page_by_slug('novidades');
        $this->assertSame('published', $news['status']);
        $this->assertSame('page:' . $news['id'], $home['data']['hero']['button_link']);
        $this->assertSame([['label' => '', 'link' => 'page:' . $news['id']]], pb_menu_items_raw('main'));
        $this->assertSame('#123456', pb_settings()->identity->color->raw());
        $this->assertNotSame('', pb_settings()->contact->whatsapp_message->raw(), 'settings the content does not mention stay');
        $photos = count(pb_media_list());
        pb_admin_import_theme_demo('second');
        $this->assertSame($photos, count(pb_media_list()), 'importing again reuses the photos instead of copying them');
        $_SESSION = [];
        pb_admin_import_theme_demo('default');
    }

    public function test_a_broken_theme_update_is_rolled_back_by_itself(): void
    {
        pb_activate_theme('second');
        $zip = sys_get_temp_dir() . '/second-2.zip';
        $archive = new ZipArchive();
        $archive->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        pb_zip_add_folder($archive, self::$themes . '/second', 'second');
        $archive->addFromString('second/theme.json', json_encode(['name' => 'Tema second', 'version' => '2.0.0', 'api' => 1]));
        $archive->addFromString('second/layout.php', '<?php throw new RuntimeException("versão 2 quebrada");');
        $archive->close();

        pb_install_package($zip, 'theme');
        pb_set_theme_state('second', ['rollback_until' => time() + PB_ROLLBACK_WINDOW]);

        $this->assertStringContainsString('class="site-header"', $this->visit('/sobre'), 'this visit falls back to the default theme');
        $this->assertSame('1.0.0', pb_package_version('theme', 'second'), 'and the previous version is back');
        $this->assertStringContainsString('TEMA-SEGUNDO', $this->visit('/sobre'));
        unlink($zip);
    }
}
