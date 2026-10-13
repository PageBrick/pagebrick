<?php

use PHPUnit\Framework\TestCase;

/** What each person prefers in the panel (light or dark, the language) is kept with their account and follows them. */
final class PreferencesTest extends TestCase
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
        $_POST = [];
        http_response_code(200);
        pb_set_locale('pt-BR');
    }

    private function open(int $user, string $path = '/admin', string $method = 'GET'): string
    {
        $_SESSION['user_id'] = $user;
        pb_set_locale(pb_find_user($user)['locale'] ?: 'pt-BR'); // as a request does
        ob_start();
        pb_admin($method, $path);
        return ob_get_clean();
    }

    public function test_a_theme_is_kept_for_the_person_only(): void
    {
        $this->assertSame([], pb_user_prefs(pb_find_user($this->editor)), 'nothing chosen: the panel follows the system');
        pb_set_user_pref($this->editor, 'theme', 'dark');
        $this->assertSame(['theme' => 'dark'], pb_user_prefs(pb_find_user($this->editor)));
        $this->assertSame([], pb_user_prefs(pb_find_user($this->admin)), 'someone else is not affected');

        pb_set_user_pref($this->editor, 'theme', 'light');
        $this->assertSame('light', pb_user_prefs(pb_find_user($this->editor))['theme']);
        pb_set_user_pref($this->editor, 'theme', null);
        $this->assertSame([], pb_user_prefs(pb_find_user($this->editor)));
        $this->assertNull(pb_find_user($this->editor)['prefs'], 'forgotten, not an empty record');
    }

    public function test_only_known_preferences_with_known_values_are_kept(): void
    {
        foreach ([['theme', 'purple'], ['theme', '<script>'], ['language', 'dark'], ['', 'dark']] as [$key, $value]) {
            try {
                pb_set_user_pref($this->editor, $key, $value);
                $this->fail("$key=$value was accepted");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('inválida', $e->getMessage());
            }
        }
        $this->assertNull(pb_find_user($this->editor)['prefs']);
    }

    public function test_the_panel_opens_in_the_saved_theme_on_any_browser(): void
    {
        $this->assertStringNotContainsString('data-theme', explode('<head>', $this->open($this->editor))[0], 'no choice: no theme forced');
        pb_set_user_pref($this->editor, 'theme', 'dark');
        $html = $this->open($this->editor);
        $this->assertMatchesRegularExpression('~<html lang="pt-BR" data-theme="dark" data-theme-saved>~', $html, 'already dark when the page arrives, no flash');
        $this->assertStringContainsString('<meta name="pb-csrf"', $html, 'so the switch can save a new choice');
        $this->assertStringNotContainsString('data-theme="dark"', explode('<head>', $this->open($this->admin))[0], 'another person keeps their own');
    }

    public function test_a_signed_in_person_never_inherits_what_the_browser_remembers(): void
    {
        // The browser remembers the theme of whoever used it last (for the sign-in screen). On a shared computer that is
        // someone else's choice: the next person must not wear it nor have it saved on their account.
        $this->assertStringNotContainsString("getItem('pb-theme')", $this->open($this->editor), 'the panel ignores it');
        $this->assertStringNotContainsString("getItem('pb-theme')", file_get_contents(PB_ROOT . '/core/assets/admin.js'), 'and so does the theme control');

        $_SESSION = [];
        ob_start();
        pb_admin('GET', '/admin/login');
        $this->assertStringContainsString("localStorage.getItem('pb-theme')", ob_get_clean(), 'only the sign-in screen, where nobody is known, uses it');
    }

    public function test_the_theme_control_starts_on_the_system_and_shows_what_was_chosen(): void
    {
        $pressed = function (string $html): array {
            preg_match_all('~data-theme-choice="(\w+)" aria-pressed="(true|false)"~', $html, $m);
            return array_keys(array_filter(array_combine($m[1], $m[2]), fn($state) => $state === 'true'));
        };
        $this->assertSame(['system'], $pressed($this->open($this->editor)), 'a new account follows the system (the first access): the screen is the one pressed');
        pb_set_user_pref($this->editor, 'theme', 'dark');
        $this->assertSame(['dark'], $pressed($this->open($this->editor)));
        pb_set_user_pref($this->editor, 'theme', 'light');
        $this->assertSame(['light'], $pressed($this->open($this->editor)));
        pb_set_user_pref($this->editor, 'theme', null);
        $this->assertSame(['system'], $pressed($this->open($this->editor)), 'choosing the system again forgets the choice');

        $html = $this->open($this->editor);
        foreach (['Claro', 'Escuro', 'Sistema'] as $label) {
            $this->assertStringContainsString('aria-label="' . $label . '"', $html, 'every choice has a name for who cannot see the icon');
        }
        $this->assertStringContainsString('<rect width="20" height="14" x="2" y="3" rx="2"/>', $html, 'the system is drawn as a computer screen');
        $this->assertStringContainsString("localStorage.removeItem('pb-theme')", $html, 'whoever follows the system leaves no theme behind for the sign-in screen');
    }

    public function test_a_new_account_starts_on_the_system(): void
    {
        $id = pb_create_user('Carla', 'carla@example.com', 'senha-de-teste-123', 'editor');
        $this->assertNull(pb_find_user($id)['prefs'], 'nothing chosen yet');
        $this->assertSame([], pb_user_prefs(pb_find_user($id)));
    }

    public function test_the_switch_saves_through_the_preferences_route(): void
    {
        $_POST = ['theme' => 'dark'];
        $this->assertSame(['ok' => true], json_decode($this->open($this->editor, '/admin/preferences', 'POST'), true));
        $this->assertSame('dark', pb_user_prefs(pb_find_user($this->editor))['theme']);

        $_POST = ['theme' => 'system'];
        $this->open($this->editor, '/admin/preferences', 'POST');
        $this->assertSame([], pb_user_prefs(pb_find_user($this->editor)), '"system" forgets the choice');

        $_POST = ['theme' => 'purple'];
        $this->assertFalse(json_decode($this->open($this->editor, '/admin/preferences', 'POST'), true)['ok']);
        $this->assertSame(422, http_response_code());
    }

    public function test_the_language_is_kept_with_the_account_too(): void
    {
        $_POST = ['locale' => 'es', 'back' => '/admin'];
        // The globe saves the language and goes back (the redirect ends the request, so the function that saves is called directly).
        pb_set_user_locale($this->editor, 'es');
        $this->assertSame('es', pb_find_user($this->editor)['locale']);
        $html = $this->open($this->editor);
        $this->assertStringContainsString('<html lang="es"', $html, 'the next sign-in opens in the language they chose');
        $this->assertSame('pt-BR', pb_find_user($this->admin)['locale'], 'and another account keeps its own');
    }

    public function test_the_preferences_column_comes_with_the_update(): void
    {
        pb_db()->exec('ALTER TABLE ' . pb_table('users') . ' DROP COLUMN prefs');
        pb_set_option('db_version', '6');
        pb_migrate();
        pb_set_user_pref($this->editor, 'theme', 'dark');
        $this->assertSame('dark', pb_user_prefs(pb_find_user($this->editor))['theme'], 'a site updated from before keeps working and gets the column');
    }
}
