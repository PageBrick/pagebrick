<?php

use PHPUnit\Framework\TestCase;

/** The site settings are two screens: Contact (whoever edits the site) and Appearance (administrators). Saving one never touches the other. */
final class SettingsScreensTest extends TestCase
{
    private int $admin;
    private int $editor;

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config();
        pb_migrate();
        pb_set_option('site_title', 'Página inicial');
        $this->admin = pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
        $this->editor = pb_create_user('Bia', 'bia@example.com', 'senha-de-teste-123', 'editor');
        pb_seed_demo();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        unset($GLOBALS['pb_theme']);
        pb_test_new_request();
        http_response_code(200);
        pb_set_locale('pt-BR');
    }

    private function open(int $user, string $path, array $query = []): string
    {
        $_SESSION['user_id'] = $user;
        $_GET = $query;
        $_SERVER['REQUEST_URI'] = $path;
        pb_set_locale(pb_find_user($user)['locale'] ?: 'pt-BR');
        http_response_code(200);
        ob_start();
        pb_admin('GET', $path);
        return ob_get_clean();
    }

    private function saved(): array
    {
        return json_decode(pb_option('theme_settings', '{}'), true);
    }

    public function test_the_identity_is_the_appearance_and_everything_else_is_contact(): void
    {
        $screens = pb_settings_screens();
        $this->assertSame(['identity'], array_keys($screens['appearance']));
        $this->assertSame(['contact', 'social', 'footer'], array_keys($screens['contact']));
    }

    public function test_a_theme_group_goes_to_the_appearance_only_when_it_says_so(): void
    {
        pb_theme(); // loaded, so its settings can be given more groups
        $GLOBALS['pb_theme']['settings']['faixa'] = ['type' => 'group', 'label' => 'Faixa', 'fields' => ['text' => ['type' => 'text', 'label' => 'Texto']]];
        $GLOBALS['pb_theme']['settings']['cores'] = ['type' => 'group', 'label' => 'Cores', 'screen' => 'appearance', 'fields' => ['extra' => ['type' => 'color', 'label' => 'Cor']]];
        $screens = pb_settings_screens();
        $this->assertSame(['identity', 'cores'], array_keys($screens['appearance']));
        $this->assertSame(['contact', 'social', 'footer', 'faixa'], array_keys($screens['contact']), 'a group that says nothing stays where whoever edits the site finds it, as before');
    }

    public function test_saving_one_screen_keeps_what_the_other_one_holds(): void
    {
        pb_save_settings(['identity' => ['color' => '#112233'], 'contact' => ['phone' => '(19) 3333-4444'], 'footer' => ['text' => 'Rodapé']]);
        $contact = array_keys(pb_settings_screens()['contact']);
        $appearance = array_keys(pb_settings_screens()['appearance']);

        pb_save_settings(['contact' => ['phone' => '(19) 5555-6666'], 'footer' => ['text' => 'Outro rodapé']], $contact); // only the contact screen was sent
        $this->assertSame('#112233', $this->saved()['identity']['color'], 'the look is as it was');
        $this->assertSame('(19) 5555-6666', $this->saved()['contact']['phone']);
        $this->assertSame('Outro rodapé', $this->saved()['footer']['text']);

        pb_save_settings(['identity' => ['color' => '#445566']], $appearance); // and now only the appearance
        $this->assertSame('#445566', $this->saved()['identity']['color']);
        $this->assertSame('(19) 5555-6666', $this->saved()['contact']['phone'], 'the contact details are as they were');
        $this->assertSame('Outro rodapé', $this->saved()['footer']['text']);
    }

    public function test_saving_without_a_screen_still_saves_every_group_as_it_always_did(): void
    {
        pb_save_settings(['identity' => ['color' => '#112233'], 'contact' => ['phone' => '(19) 3333-4444']]);
        $this->assertSame('#112233', $this->saved()['identity']['color']);
        $this->assertSame('(19) 3333-4444', $this->saved()['contact']['phone']);
        pb_save_settings(['contact' => ['phone' => '(19) 5555-6666']]); // the identity was not sent: blank, as in every version before
        $this->assertSame(0, $this->saved()['identity']['logo']);
    }

    public function test_the_translations_of_one_screen_do_not_wipe_the_other(): void
    {
        pb_set_site_locales(['en']);
        pb_theme();
        $GLOBALS['pb_theme']['settings']['visual'] = ['type' => 'group', 'label' => 'Visual', 'screen' => 'appearance', 'fields' => ['slogan' => ['type' => 'text', 'label' => 'Slogan']]];
        $contact = array_keys(pb_settings_screens()['contact']);
        $appearance = array_keys(pb_settings_screens()['appearance']);

        pb_save_settings_translation('en', ['footer' => ['text' => 'Footer in English']], $contact);
        pb_save_settings_translation('en', ['visual' => ['slogan' => 'Our slogan']], $appearance);
        $this->assertSame('Footer in English', pb_settings_translation('en')['footer']['text'], 'what the first screen translated is still there');
        $this->assertSame('Our slogan', pb_settings_translation('en')['visual']['slogan']);

        pb_save_settings_translation('en', ['footer' => ['text' => 'New footer']], $contact);
        $this->assertSame('Our slogan', pb_settings_translation('en')['visual']['slogan']);
    }

    public function test_the_contact_screen_is_open_to_editors_and_shows_only_what_is_not_the_look(): void
    {
        $html = $this->open($this->editor, '/admin/settings');
        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('<h1>Contato</h1>', $html);
        foreach (['f[contact][whatsapp]', 'f[contact][email]', 'f[social][instagram]', 'f[footer][text]'] as $name) {
            $this->assertStringContainsString('name="' . $name . '"', $html);
        }
        $this->assertStringNotContainsString('f[identity]', $html, 'no logo, color or letters here');
        $this->assertStringNotContainsString('name="site_title"', $html);
    }

    public function test_the_appearance_screen_is_for_administrators_only(): void
    {
        $html = $this->open($this->admin, '/admin/appearance');
        $this->assertSame(200, http_response_code());
        $this->assertStringContainsString('<h1>Aparência</h1>', $html);
        $this->assertStringContainsString('name="site_title"', $html, 'the name of the site lives with its look');
        $this->assertStringContainsString('name="f[identity][color]"', $html);
        $this->assertStringContainsString('name="f[identity][fonts]"', $html);
        $this->assertStringNotContainsString('f[contact]', $html);
        $this->assertStringContainsString('href="/admin/appearance" aria-current="page"', $html, 'under Configurações, in the list');

        $this->assertStringContainsString('Acesso negado', $this->open($this->editor, '/admin/appearance'));
        $this->assertSame(403, http_response_code());
    }

    public function test_the_language_tabs_are_only_on_the_screen_that_has_texts(): void
    {
        pb_set_site_locales(['en']);
        $this->assertStringContainsString('class="tabs"', $this->open($this->editor, '/admin/settings'));
        $this->assertStringNotContainsString('class="tabs"', $this->open($this->admin, '/admin/appearance'), 'a look has no texts to translate');
        $this->assertStringContainsString('Textos em English', $this->open($this->editor, '/admin/settings', ['idioma' => 'en']));
        $this->assertStringNotContainsString('Textos em English', $this->open($this->admin, '/admin/appearance', ['idioma' => 'en']));
    }

    public function test_the_dashboard_points_to_the_two_screens_by_role(): void
    {
        $admin = $this->open($this->admin, '/admin');
        $this->assertStringContainsString('href="/admin/appearance"', $admin);
        $this->assertStringContainsString('<strong>Contato</strong>', $admin);
        $editor = $this->open($this->editor, '/admin');
        $this->assertStringNotContainsString('/admin/appearance', $editor);
        $this->assertStringContainsString('<strong>Contato</strong>', $editor);
    }
}
