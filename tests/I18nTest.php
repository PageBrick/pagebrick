<?php

use PHPUnit\Framework\TestCase;

/** Portuguese, English and Spanish (decision 28): every text is translated, and a site works in the language it was installed in. */
final class I18nTest extends TestCase
{
    /** lang folder => code whose __() texts it translates */
    private const AREAS = [
        'core/lang' => ['core', 'index.php'],
        'content/themes/default/lang' => ['content/themes/default'],
        'content/plugins/contact-form/lang' => ['content/plugins/contact-form'],
        'content/plugins/blog/lang' => ['content/plugins/blog'],
    ];
    /** Addresses of the demo pages, translated with __($slug) by pb_seed_demo(). */
    private const DEMO_SLUGS = ['inicio', 'sobre', 'servicos', 'contato', 'politica-de-privacidade'];

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
        pb_set_locale('pt-BR'); // the other tests read Portuguese
    }

    /** Every literal given to __() in the PHP files under $paths. */
    private static function texts(array $paths): array
    {
        $texts = [];
        foreach ($paths as $path) {
            $files = is_file(PB_ROOT . "/$path") ? [PB_ROOT . "/$path"]
                : new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PB_ROOT . "/$path", FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (!str_ends_with((string) $file, '.php') || str_contains(str_replace('\\', '/', (string) $file), '/lang/')) {
                    continue;
                }
                $tokens = token_get_all(file_get_contents((string) $file));
                foreach ($tokens as $i => $t) {
                    if (is_array($t) && $t[0] === T_STRING && $t[1] === '__' && ($tokens[$i + 1] ?? null) === '('
                        && is_array($tokens[$i + 2] ?? null) && $tokens[$i + 2][0] === T_CONSTANT_ENCAPSED_STRING) {
                        $texts[] = eval('return ' . $tokens[$i + 2][1] . ';'); // a string literal from our own code
                    }
                }
            }
        }
        return array_unique($texts);
    }

    private function site(string $locale): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config();
        pb_set_locale($locale); // as the installer does, in the language picked on its first screen
        pb_migrate();
        pb_set_option('locale', $locale);
        pb_set_option('site_title', 'Acme');
        $_SESSION['user_id'] = pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
        pb_seed_demo();
        pb_activate_plugin('contact-form');
        pb_activate_plugin('blog');
        pb_set_option('catalog_cache', json_encode(['fetched_at' => time(), 'data' => ['format' => 1, 'plugins' => [], 'themes' => []]]));
    }

    private function visit(string $path): string
    {
        pb_test_new_request();
        pb_set_locale(pb_site_locale());
        pb_load_plugins();
        $_SERVER['REQUEST_URI'] = $path;
        ob_start();
        pb_public('GET', $path);
        return ob_get_clean();
    }

    private function panel(string $path, array $query = []): string
    {
        pb_test_new_request();
        pb_set_locale(pb_current_user()['locale'] ?? '' ?: pb_site_locale());
        pb_load_plugins();
        $_GET = $query;
        $_SERVER['REQUEST_URI'] = $path;
        ob_start();
        pb_admin('GET', $path);
        return ob_get_clean();
    }

    public function test_every_text_has_an_english_and_a_spanish_translation(): void
    {
        $count = fn(string $s) => preg_match_all('/%(\d\$)?[sd]/', $s, $m) ? array_count_values($m[0]) : [];
        foreach (self::AREAS as $dir => $paths) {
            $texts = self::texts($paths);
            if ($dir === 'core/lang') {
                $texts = array_merge($texts, self::DEMO_SLUGS);
            }
            foreach (['en', 'es'] as $locale) {
                $translations = require PB_ROOT . "/$dir/$locale.php";
                $this->assertSame([], array_values(array_diff($texts, array_keys($translations))),
                    "Texts without translation in $dir/$locale.php: add them as 'Portuguese text' => 'translation'.");
                foreach ($translations as $pt => $translation) {
                    $this->assertEquals($count($pt), $count($translation), "$dir/$locale.php: \"$translation\" must keep the %s and %d of \"$pt\".");
                }
            }
        }
    }

    public function test_a_site_installed_in_english_is_in_english_everywhere(): void
    {
        $this->site('en');
        foreach (['about', 'services', 'contact', 'privacy-policy'] as $slug) {
            $this->assertNotNull(pb_page_by_slug($slug), "the demo page /$slug");
        }
        pbb_save(pbb_create('First post'), ['title' => 'First post', 'slug' => '', 'status' => 'published', 'published_on' => date('Y-m-d'), 'f' => []]);
        $category = pbb_save_category(0, 'Tips', '', 0, '');

        $site = '';
        foreach (['/', '/about', '/services', '/contact', '/privacy-policy', '/blog', '/blog/first-post', '/blog/category/tips', '/nothing-here'] as $path) {
            $site .= $this->visit($path);
        }
        $this->assertStringContainsString('<html lang="en">', $site);
        $this->assertStringContainsString('<meta property="og:locale" content="en_US">', $site);

        $panel = '';
        $screens = [['/admin'], ['/admin/pages'], ['/admin/pages/edit', ['id' => (string) pb_home_page_id()]], ['/admin/media'], ['/admin/menus'],
            ['/admin/settings'], ['/admin/account'], ['/admin/users'], ['/admin/plugins'], ['/admin/plugins/settings', ['plugin' => 'blog']],
            ['/admin/themes'], ['/admin/updates'], ['/admin/email'], ['/admin/p/mensagens'], ['/admin/p/blog'],
            ['/admin/p/blog', ['aba' => 'categorias', 'editar' => (string) $category]], ['/admin/p/blog', ['editar' => '1']]];
        foreach ($screens as $screen) {
            $panel .= $this->panel(...$screen);
        }
        $_SESSION = [];
        $panel .= $this->panel('/admin/login');
        $this->assertStringContainsString('<html lang="en">', $panel);

        // English has no accents: a word with á, ã, ç… is Portuguese that escaped translation.
        // (Language pickers list each language in its own name, "Português (Brasil)", on purpose.)
        preg_match_all('/[^\s<>"]*[áàâãéêíóôõúç][^\s<>"]*/iu', str_replace(PB_LOCALES, '', $site . $panel), $portuguese);
        $this->assertSame([], array_values(array_unique($portuguese[0])), 'Portuguese left on English screens (wrap it in __()).');
    }

    public function test_each_user_can_see_the_panel_in_another_language(): void
    {
        $this->site('pt-BR');
        pb_set_user_locale($_SESSION['user_id'], 'es');
        pb_set_locale('es');
        $newPage = __('Nova página');
        $this->assertNotSame('Nova página', $newPage);

        $panel = $this->panel('/admin/pages');
        $this->assertStringContainsString('<html lang="es">', $panel);
        $this->assertStringContainsString(e($newPage), $panel);
        $this->assertStringContainsString('<html lang="pt-BR">', $this->visit('/sobre'), 'the site keeps its own language');
    }

    public function test_the_site_language_can_change_later(): void
    {
        $this->site('pt-BR');
        $this->assertSame('https://wa.me/5519999998888', pb_whatsapp_url('(19) 99999-8888'), 'Portuguese sites may leave out the country code');

        pb_set_site_locale('en');
        $this->assertSame('en', pb_site_locale());
        $this->assertStringContainsString('<html lang="en">', $this->visit('/sobre'), 'pages keep their addresses');
        pb_set_locale('en');
        $this->assertSame('https://wa.me/15551234567', pb_whatsapp_url('+1 555 123 4567'), 'no Brazilian code added');
        $this->assertSame('10/07/2026 3:30 PM', pb_date('2026-10-07 15:30:00', true));
        pb_set_locale('es');
        $this->assertSame('07/10/2026 15:30', pb_date('2026-10-07 15:30:00', true));
    }
}
