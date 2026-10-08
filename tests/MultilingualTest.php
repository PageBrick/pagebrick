<?php

use PHPUnit\Framework\TestCase;

/** A site in more than one language: the main one at the root, extra ones under /en-us, /es-es or /pt-br. */
final class MultilingualTest extends TestCase
{
    private int $admin;

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config();
        pb_migrate();
        pb_set_option('site_title', 'Padaria Exemplo');
        $this->admin = pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
        pb_seed_demo();
        pb_set_site_locales(['en', 'es']);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($GLOBALS['pb_content_locale']);
        pb_set_locale('pt-BR');
        http_response_code(200);
    }

    /** @return array{int, string} */
    private function visit(string $path): array
    {
        pb_test_new_request();
        unset($GLOBALS['pb_content_locale'], $GLOBALS['pbcf_used']);
        pb_set_locale(pb_site_locale());
        pb_load_plugins();
        $_SERVER['REQUEST_URI'] = $path;
        http_response_code(200);
        ob_start();
        pb_public('GET', $path);
        return [http_response_code(), ob_get_clean()];
    }

    /** Translates a page into English and publishes it. */
    private function translate(string $slug, string $title, array $data = [], string $newSlug = ''): array
    {
        $page = pb_page_by_slug($slug);
        $id = pb_page_translate($page['id'], 'en');
        $translation = pb_page_find($id);
        pb_page_save($id, ['title' => $title, 'slug' => $newSlug ?: pb_slugify($title), 'status' => 'published', 'seo_title' => '', 'seo_description' => '',
            'f' => array_replace_recursive($translation['data'], $data)], $this->admin);
        return pb_page_find($id);
    }

    public function test_languages_are_switched_on_in_the_panel(): void
    {
        $this->assertSame(['pt-BR', 'en', 'es'], pb_site_locales());
        pb_set_site_locales(['pt-BR', 'xx', 'es']);
        $this->assertSame(['pt-BR', 'es'], pb_site_locales(), 'the main language and unknown codes are ignored');
        $this->assertSame('/es-es/servicios', pb_locale_path('es', '/servicios'));
        $this->assertSame('/sobre', pb_locale_path('pt-BR', '/sobre'));
    }

    public function test_a_translated_page_lives_under_its_language_prefix(): void
    {
        $home = $this->translate('inicio', 'Home', ['hero' => ['title' => 'Say it in one sentence', 'button_link' => 'page:' . pb_page_by_slug('servicos')['id']]]);
        $about = $this->translate('sobre', 'About us', ['intro' => 'A short English intro.'], 'about');
        $this->assertSame('/en-us', pb_page_path($home));
        $this->assertSame('/en-us/about', pb_page_path($about));

        [$status, $html] = $this->visit('/en-us');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('<html lang="en">', $html);
        $this->assertStringContainsString('Say it in one sentence', $html);
        $this->assertStringContainsString('Skip to content', $html, "the theme's fixed texts follow the language");
        $this->assertStringContainsString('href="/en-us/about"', $html, 'the menu leads to the translation');
        $this->assertStringContainsString('href="/servicos"', $html, 'a page not translated yet keeps its original address');
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/en-us">', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="pt-BR" href="http://localhost/">', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="en" href="http://localhost/en-us">', $html);

        $this->assertStringContainsString('A short English intro.', $this->visit('/en-us/about')[1]);
        $this->assertStringContainsString('Diga aqui, em uma frase', $this->visit('/')[1], 'the main language is untouched');
        $this->assertSame(404, $this->visit('/en-us/sobre')[0]);
        $this->assertSame(404, $this->visit('/es-es')[0], 'a language with no translated home page');
        $this->assertStringContainsString('/en-us/about', pb_sitemap_xml());

        pb_set_site_locales(['es']);
        $this->assertSame(404, $this->visit('/en-us/about')[0], 'a language switched off is not shown');
        $this->assertStringNotContainsString('/en-us/about', pb_sitemap_xml());
    }

    public function test_the_language_switcher_leads_to_the_same_page(): void
    {
        $this->translate('inicio', 'Home');
        $this->translate('sobre', 'About us', [], 'about');
        $this->visit('/sobre');
        $links = array_column(pb_language_links(), 'url', 'locale');
        $this->assertSame(['pt-BR' => '/sobre', 'en' => '/en-us/about'], $links, 'Spanish has no translation of this page nor a home page');

        $this->visit('/contato');
        $this->assertSame('/en-us', array_column(pb_language_links(), 'url', 'locale')['en'], 'no translation: that language\'s home page');
    }

    public function test_texts_of_appearance_and_contact_can_be_translated(): void
    {
        pb_save_settings(['footer' => ['text' => 'Rodapé em português'], 'contact' => ['phone' => '(19) 3333-4444']]);
        pb_save_settings_translation('en', ['footer' => ['text' => 'Footer in English'], 'contact' => ['phone' => 'nope', 'hours' => '']]);
        $this->assertArrayNotHasKey('phone', pb_settings_translation('en')['contact'] ?? [], 'only texts are translated');

        $GLOBALS['pb_content_locale'] = 'en';
        $this->assertSame('Footer in English', pb_settings()->footer->text->raw());
        $this->assertSame('(19) 3333-4444', pb_settings()->contact->phone->raw());
        unset($GLOBALS['pb_content_locale']);
        $this->assertSame('Rodapé em português', pb_settings()->footer->text->raw());
    }

    public function test_deleting_a_page_takes_its_translations_and_the_main_language_is_protected(): void
    {
        $about = $this->translate('sobre', 'About us', [], 'about');
        pb_page_delete(pb_page_by_slug('sobre')['id']);
        $this->assertNull(pb_page_find($about['id']));

        $this->expectExceptionMessage('já tem páginas traduzidas para English');
        $this->translate('servicos', 'Services');
        pb_set_site_locale('en');
    }

    public function test_the_panel_lists_translations_and_starts_new_ones(): void
    {
        $this->translate('sobre', 'About us', [], 'about');
        pb_test_new_request();
        $_SESSION = ['user_id' => $this->admin];
        ob_start();
        pb_admin('GET', '/admin/pages');
        $html = ob_get_clean();
        $this->assertStringContainsString('Traduções', $html);
        $this->assertStringContainsString('>EN</a>', $html, 'the existing English translation');
        $this->assertStringContainsString('+ ES', $html, 'a button to start the Spanish one');
        $this->assertStringNotContainsString('>About us<', $html, 'translations are not listed as pages of their own');

        ob_start();
        $_GET = ['idioma' => 'en'];
        pb_admin('GET', '/admin/settings');
        $_GET = [];
        $this->assertStringContainsString('Textos em English', ob_get_clean());
    }

    public function test_a_translated_page_keeps_working_parts_of_the_main_language(): void
    {
        pb_save_settings(['contact' => ['whatsapp' => '(19) 99999-8888']]);
        $this->translate('inicio', 'Home');
        $this->assertStringContainsString('wa.me/5519999998888', $this->visit('/en-us')[1], 'the number typed on a Brazilian site keeps +55 in English');

        $this->translate('politica-de-privacidade', 'Privacy policy', [], 'privacy-policy');
        $contact = pb_page_find($this->translate('contato', 'Contact', [], 'contact')['id']);
        pb_activate_plugin('contact-form');
        $form = $this->visit('/en-us/contact')[1];
        $this->assertStringContainsString('<a href="/en-us/privacy-policy">Privacy policy</a></p>', $form, 'the form\'s privacy link in the page\'s language');
        $this->assertStringContainsString('action="/en-us/contato/enviar"', $form, 'the form is sent in the page\'s language');

        // A form with errors comes back in English: messages, menus and fixed texts.
        $send = function (string $path) use ($contact): string {
            pb_test_new_request();
            unset($GLOBALS['pb_content_locale']);
            pb_set_locale('pt-BR');
            pb_load_plugins();
            $_POST = ['page' => (string) $contact['id'], 'name' => '', 'email' => 'x', 'message' => '', '_t' => '1', '_s' => 'x', 'website' => ''];
            ob_start();
            pb_public('POST', $path);
            $_POST = [];
            return ob_get_clean();
        };
        $html = $send('/en-us/contato/enviar');
        $this->assertStringContainsString('<html lang="en">', $html);
        $this->assertStringContainsString('Enter your name.', $html);
        // A theme's older copy of the form may still send without the prefix: the page keeps its own language.
        $this->assertStringContainsString('<html lang="en">', $send('/contato/enviar'));
        $this->assertSame('pt-BR', pb_locale(), 'after rendering, the request is back in its own language');
    }

    public function test_link_pickers_and_reserved_addresses(): void
    {
        $this->translate('sobre', 'About us', [], 'about');
        $picker = pb_link_input('f-x', 'f[x]', '');
        $this->assertStringContainsString('>Sobre</option>', $picker);
        $this->assertStringNotContainsString('>About us</option>', $picker, 'translations are reached through their original');

        $id = pb_page_create('Admin', 'page');
        $this->assertSame('admin-2', pb_page_find($id)['slug'], 'never the panel\'s address');
        $this->assertSame('docs-2', pb_page_find(pb_page_create('Docs', 'page'))['slug'], '.htaccess blocks /docs');
        $this->expectExceptionMessage('é reservado');
        pb_page_save($id, ['title' => 'Admin', 'slug' => 'en-us', 'status' => 'published', 'seo_title' => '', 'seo_description' => '', 'f' => []], $this->admin);
    }

    public function test_ready_made_content_can_bring_translations(): void
    {
        pb_set_site_locales([]);
        pb_import_content([
            'pages' => [['title' => 'Novidades', 'slug' => 'novidades', 'template' => 'page', 'data' => ['intro' => 'Em português.'],
                'translations' => ['es' => ['title' => 'Novedades', 'slug' => 'novedades', 'data' => ['intro' => 'En español.']]]]],
            'settings_translations' => ['es' => ['footer' => ['text' => 'Pie en español']]],
        ], PB_ROOT . '/core/demo');
        $this->assertSame(['pt-BR', 'es'], pb_site_locales(), 'the language of the translations was switched on');
        $this->assertStringContainsString('En español.', $this->visit('/es-es/novedades')[1]);
        $this->assertSame('Pie en español', pb_settings_translation('es')['footer']['text']);
    }
}
