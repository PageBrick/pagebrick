<?php

use PHPUnit\Framework\TestCase;

/** Google Analytics 4 and the Meta pixel, from Settings → Tracking: what is printed, to whom, and what the owner may type. */
final class TrackingTest extends TestCase
{
    private int $admin;
    private int $editor;

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config();
        pb_migrate();
        pb_set_option('site_title', 'Site');
        $this->admin = pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
        $this->editor = pb_create_user('Bia', 'bia@example.com', 'senha-de-teste-123', 'editor');
        pb_seed_demo();
    }

    protected function tearDown(): void
    {
        $_SESSION = $_GET = $_POST = [];
        unset($GLOBALS['pb_track_events'], $GLOBALS['pb_current_page']);
        pb_test_new_request();
        pb_set_locale('pt-BR');
    }

    /** The home page as a visitor gets it: the whole HTML with the theme's layout. */
    private function home(bool $preview = false): string
    {
        unset($GLOBALS['pb_track_events']);
        return pb_render_page(pb_page_find(pb_home_page_id()), $preview);
    }

    private function head(string $html): string
    {
        return explode('</head>', $html)[0];
    }

    private function codes(bool $consent = true): void
    {
        pb_tracking_save('G-AB12CD34EF', '1234567890123456', $consent);
    }

    public function test_a_site_with_no_code_saved_prints_nothing_of_it(): void
    {
        $html = $this->home();
        foreach (['googletagmanager', 'fbevents', 'pbTrack', 'pb-consent', 'data-pb-consent'] as $part) {
            $this->assertStringNotContainsString($part, $html, $part);
        }
        $this->assertSame('', pb_tracking_head());
        $this->assertSame('', pb_tracking_footer());
    }

    public function test_by_default_the_visitor_is_asked_first_and_nothing_starts_in_the_head(): void
    {
        $this->codes();
        $this->assertTrue(pb_tracking_consent_required());
        $html = $this->home();
        $this->assertStringNotContainsString('googletagmanager', $this->head($html), 'no tool in <head> before "Accept"');
        $this->assertStringNotContainsString('pbTrackStart();', $this->head($html));
        $this->assertStringContainsString('id="pb-consent"', $html);
        $this->assertStringContainsString('data-pb-consent="yes"', $html);
        $this->assertStringContainsString('data-pb-consent="no"', $html);
        $this->assertStringContainsString('Google Analytics + Meta', $html);
        $this->assertStringContainsString('"G-AB12CD34EF"', $html);
        $this->assertStringContainsString('"1234567890123456"', $html);
        $this->assertStringContainsString('localStorage', $html, 'the choice is remembered in the browser');
        $this->assertStringContainsString('politica-de-privacidade', $html, 'the notice links to the privacy policy');
    }

    public function test_with_the_switch_off_the_tools_start_in_the_head_and_there_is_no_notice(): void
    {
        $this->codes(false);
        $html = $this->home();
        $this->assertStringContainsString('pbTrackStart();', $this->head($html));
        $this->assertStringContainsString('googletagmanager.com/gtag/js', $this->head($html));
        $this->assertStringNotContainsString('pb-consent', $html);
    }

    public function test_only_the_tool_that_has_a_code_is_started(): void
    {
        pb_tracking_save('G-AB12CD34EF', '', false);
        $head = $this->head($this->home());
        $this->assertStringContainsString('var ga="G-AB12CD34EF",px="",', $head);
        pb_tracking_save('', '1234567890123456', true);
        $html = $this->home();
        $this->assertStringContainsString('var ga="",px="1234567890123456",', $html);
        $this->assertStringContainsString('(Meta)', $html, 'the notice names only what is used');
        $this->assertStringNotContainsString('Google Analytics', $html);
    }

    public function test_nobody_is_counted_while_the_site_is_not_live_or_when_somebody_is_signed_in_or_in_a_preview(): void
    {
        $this->codes(false);
        $this->assertStringContainsString('pbTrackStart', $this->home());

        pb_set_site_mode('construction', '');
        $this->assertStringNotContainsString('googletagmanager', $this->home(), 'under construction');
        pb_set_site_mode('maintenance', '');
        $this->assertStringNotContainsString('googletagmanager', $this->home(), 'in maintenance');
        pb_set_site_mode('live', '');

        $_SESSION['user_id'] = $this->editor;
        $this->assertStringNotContainsString('googletagmanager', $this->home(), 'whoever is signed in to the panel is never counted');
        $_SESSION = [];

        $this->assertStringNotContainsString('googletagmanager', $this->home(true), 'a preview');
        $this->assertStringContainsString('googletagmanager', $this->home(), 'and a plain visit again is');
    }

    public function test_what_is_typed_in_the_option_can_not_break_out_of_the_script(): void
    {
        pb_set_option('track_ga4', 'G-A"</script><script>alert(1)</script>');
        pb_set_option('track_meta', '1\'; alert(2); //');
        pb_set_option('track_consent', '0');
        $html = $this->home();
        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
        $slash = chr(92); // JSON writes a quote as slash-u0027 and "<" as slash-u003C: they can not close the string or the tag
        $this->assertStringContainsString('px="1' . $slash . 'u0027; alert(2); ' . $slash . '/' . $slash . '/"', $html);
        $this->assertStringContainsString($slash . 'u003C' . $slash . '/script' . $slash . 'u003E', $html);
    }

    public function test_an_event_is_printed_once_for_the_page_only_when_the_page_is_counted(): void
    {
        $this->codes(false);
        pb_track('lead', 'abc.def');
        pb_track('nonsense');
        $footer = pb_tracking_footer();
        $this->assertStringContainsString('pbTrack("lead","abc.def")', $footer);
        $this->assertSame(1, substr_count($footer, 'pbTrack('), 'an event that does not exist is ignored');

        pb_set_site_mode('construction', '');
        $this->assertSame('', pb_tracking_footer(), 'nothing when the page is not counted');
    }

    public function test_an_event_waits_for_the_notice_when_the_visitor_is_asked(): void
    {
        $this->codes(true);
        pb_track('lead', 'tok');
        $footer = pb_tracking_footer();
        $this->assertGreaterThan(strpos($footer, 'pbTrackStart'), strpos($footer, 'pbTrack("lead","tok")'), 'the loader is defined before the event is announced');
        $this->assertStringContainsString('q.push(e)', pb_tracking_script(), 'and until "Accept" the event is only kept');
        $this->assertStringContainsString('q.splice(0).forEach(send)', pb_tracking_script(), 'then it is sent');
    }

    public function test_a_theme_or_plugin_can_replace_the_notice(): void
    {
        $this->codes(true);
        $seen = null;
        pb_add_filter('consent_html', function (string $html, array $tools) use (&$seen) {
            $seen = $tools;
            return '<div id="pb-consent" style="display:none">Nosso aviso <button data-pb-consent="yes">Ok</button></div>';
        });
        $html = $this->home();
        $this->assertStringContainsString('Nosso aviso', $html);
        $this->assertStringNotContainsString('Recusar', $html);
        $this->assertSame(['Google Analytics', 'Meta'], $seen);
    }

    public function test_the_codes_are_checked_and_kept_in_a_clean_form(): void
    {
        pb_tracking_save(' g-ab12cd34ef ', ' 1234567890123456 ', true);
        $this->assertSame(['ga4' => 'G-AB12CD34EF', 'meta' => '1234567890123456'], pb_tracking_ids(), 'trimmed, and the G- in capitals');

        foreach ([['UA-123456-1', 'antigo'], ['GTM-ABC123', 'Tag Manager'], ['ABC', 'começa com G-'], ['G-AB', 'começa com G-']] as [$bad, $word]) {
            try {
                pb_tracking_save($bad, '', true);
                $this->fail("$bad was accepted");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($word, $e->getMessage(), $bad);
            }
        }
        foreach (['12AB', 'abc', '1234567', '1234 5678'] as $bad) {
            try {
                pb_tracking_save('', $bad, true);
                $this->fail("$bad was accepted");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('só um número', $e->getMessage());
            }
        }
        $this->assertSame('G-AB12CD34EF', pb_tracking_ids()['ga4'], 'a refused save changes nothing');

        pb_tracking_save('', '', false);
        $this->assertSame(['ga4' => '', 'meta' => ''], pb_tracking_ids(), 'empty turns them off');
        $this->assertFalse(pb_tracking_consent_required());
        $this->assertFalse(pb_tracking_active());
    }

    public function test_the_screen_shows_what_is_saved_and_only_administrators_open_it(): void
    {
        $this->codes(false);
        $_SESSION['user_id'] = $this->admin;
        ob_start();
        pb_admin('GET', '/admin/tracking');
        $screen = ob_get_clean();
        $this->assertStringContainsString('value="G-AB12CD34EF"', $screen);
        $this->assertStringContainsString('value="1234567890123456"', $screen);
        $this->assertStringContainsString('action="/admin/tracking"', $screen);
        $this->assertStringNotContainsString('name="consent" value="1" checked', $screen, 'the switch is off');
        $this->assertStringContainsString('Sem o aviso', $screen, 'and it warns about that');
        $this->assertStringContainsString('Atualize a sua política de privacidade', $screen);
        $this->assertStringContainsString('/admin/pages/edit?id=', $screen, 'with a link to the page');

        $items = array_column(array_values(array_filter(pb_admin_nav(pb_find_user($this->admin), '/admin/tracking')['sections'], fn($s) => $s['id'] === 'settings'))[0]['items'], 'url');
        $this->assertContains('/admin/tracking', $items);
        $editor = pb_admin_nav(pb_find_user($this->editor), '/admin')['sections'];
        $this->assertSame(['content'], array_column($editor, 'id'), 'editors have no Settings at all');

        $_SESSION['user_id'] = $this->editor;
        http_response_code(200);
        ob_start();
        pb_admin('GET', '/admin/tracking');
        ob_end_clean();
        $this->assertSame(403, http_response_code(), 'an editor is refused');
    }

    public function test_a_screen_with_nothing_saved_has_the_switch_on_and_no_warnings(): void
    {
        $_SESSION['user_id'] = $this->admin;
        ob_start();
        pb_admin('GET', '/admin/tracking');
        $screen = ob_get_clean();
        $this->assertStringContainsString('name="consent" value="1" checked', $screen);
        $this->assertStringNotContainsString('Sem o aviso', $screen);
        $this->assertStringNotContainsString('Atualize a sua política', $screen);
    }

    public function test_the_notice_and_the_screen_speak_the_language_of_the_site_and_of_the_panel(): void
    {
        $this->codes(true);
        pb_set_locale('en');
        $this->assertStringContainsString('This site uses cookies (Google Analytics + Meta) to measure visits and ads. Do you accept?', pb_consent_banner());
        $this->assertStringContainsString('Accept', pb_consent_banner());
        pb_set_locale('es');
        $this->assertStringContainsString('¿Aceptas?', pb_consent_banner());
    }
}
