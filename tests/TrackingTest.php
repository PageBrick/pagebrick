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

    // ---------------------------------------------------------------- the Path Ranker collector

    private const COLLECTOR = '<script src="https://dash.pathranker.com/pr-collect.js" data-site="www.exemplo.com.br" data-key="abc123DEF-456" defer></script>';

    private function collector(string $snippet): void
    {
        pb_tracking_save('', '', true, $snippet);
    }

    private function refused(string $snippet, string $why = ''): string
    {
        $before = pb_option('track_pathranker', '');
        try {
            $this->collector($snippet);
            $this->fail('accepted: ' . $snippet);
        } catch (InvalidArgumentException $e) {
            $this->assertSame($before, pb_option('track_pathranker', ''), 'a refused snippet changes nothing');
            if ($why !== '') {
                $this->assertStringContainsString($why, $e->getMessage(), $snippet);
            }
            return $e->getMessage();
        }
        return '';
    }

    public function test_the_snippet_of_path_ranker_is_written_again_and_goes_into_the_head_of_public_pages(): void
    {
        $this->collector(self::COLLECTOR);
        $this->assertSame(['src' => 'https://dash.pathranker.com/pr-collect.js', 'site' => 'www.exemplo.com.br', 'key' => 'abc123DEF-456'], pb_pathranker_saved());
        $html = $this->home();
        $this->assertStringContainsString(self::COLLECTOR, $this->head($html), 'in <head>, deferred');
        $this->assertSame(1, substr_count($html, 'pr-collect.js'));
        $this->assertStringNotContainsString('pb-consent', $html, 'it uses no cookies, so no notice is asked for it');
        $this->assertStringNotContainsString('googletagmanager', $html);
    }

    public function test_it_comes_with_the_other_tools_without_waiting_for_the_notice(): void
    {
        pb_tracking_save('G-AB12CD34EF', '1234567890123456', true, self::COLLECTOR);
        $html = $this->home();
        $this->assertStringContainsString('pr-collect.js', $this->head($html));
        $this->assertStringNotContainsString('googletagmanager', $this->head($html), 'Google still waits for "Accept"');
        $this->assertStringContainsString('id="pb-consent"', $html);
    }

    public function test_the_ways_the_same_snippet_may_be_typed_are_all_taken_and_written_the_same(): void
    {
        $same = ['src' => 'https://dash.pathranker.com/pr-collect.js', 'site' => 'www.exemplo.com.br', 'key' => 'abc123DEF-456'];
        foreach ([
            "  \n<SCRIPT SRC='https://dash.pathranker.com/pr-collect.js' DATA-SITE='WWW.Exemplo.com.br' data-key=abc123DEF-456 async></SCRIPT>\n",
            "<script\n  src=\"https://dash.pathranker.com/pr-collect.js\"\n  data-site=\"www.exemplo.com.br\"\n  data-key=\"abc123DEF-456\"\n  defer\n></script>",
            '<script defer data-key="abc123DEF-456" data-site="www.exemplo.com.br" src="https://dash.pathranker.com/pr-collect.js"> </script>',
        ] as $typed) {
            pb_set_option('track_pathranker', '');
            $this->collector($typed);
            $this->assertSame($same, pb_pathranker_saved(), $typed);
            $this->assertStringContainsString(self::COLLECTOR, $this->head($this->home()), 'always written with defer, whatever was typed');
        }
        $this->collector('<script src="http://localhost:3000/pr-collect.js" data-key="k1" defer></script>');
        $this->assertSame(['src' => 'http://localhost:3000/pr-collect.js', 'site' => '', 'key' => 'k1'], pb_pathranker_saved(), 'http is for localhost, and data-site is optional');
        $this->assertStringContainsString('<script src="http://localhost:3000/pr-collect.js" data-key="k1" defer></script>', $this->head($this->home()));
        $this->collector('<script src="https://dash.pathranker.com/assets/pr-collect.js" data-key="a+b/c=:d.e_f-g" defer></script>');
        $this->assertSame('a+b/c=:d.e_f-g', pb_pathranker_saved()['key'], 'a key may use the characters of base64 and of URLs');
    }

    public function test_an_empty_field_puts_nothing_in_the_pages_and_leaving_it_out_changes_nothing(): void
    {
        $this->collector(self::COLLECTOR);
        pb_tracking_save('G-AB12CD34EF', '', true);
        $this->assertNotNull(pb_pathranker_saved(), 'saving the other codes without the field leaves the collector as it was');
        $this->collector('   ');
        $this->assertNull(pb_pathranker_saved());
        $this->assertStringNotContainsString('pr-collect.js', $this->home());
        $this->assertSame('', pb_tracking_head(), 'and the head has nothing of it');
    }

    public function test_a_script_from_somewhere_else_is_refused(): void
    {
        $this->collector(self::COLLECTOR);
        $address = 'O endereço do script (src)';
        foreach ([
            'https://evil.example/collect.js', 'https://dash.pathranker.com/pr-collect.json', 'https://dash.pathranker.com/pr-collect.js/x', 'https://dash.pathranker.com/',
            'http://dash.pathranker.com/pr-collect.js', 'ftp://dash.pathranker.com/pr-collect.js', '//dash.pathranker.com/pr-collect.js', '/pr-collect.js', 'pr-collect.js',
            'javascript:alert(1)/pr-collect.js', 'data:text/javascript,alert(1)//pr-collect.js', 'https://dash.pathranker.com/pr-collect.js?x=1', 'https://dash.pathranker.com/pr-collect.js#x',
            'https://user:pass@dash.pathranker.com/pr-collect.js', 'https://dash.pathranker.com:99999/pr-collect.js', 'https://da sh.pathranker.com/pr-collect.js', 'https://dash.pathranker.com/a b/pr-collect.js',
        ] as $src) {
            $this->refused('<script src="' . $src . '" data-key="k1" defer></script>', $address);
        }
        $this->assertSame('abc123DEF-456', pb_pathranker_saved()['key'], 'and the one saved before is still there');
    }

    public function test_the_key_is_needed_and_has_to_look_like_a_key(): void
    {
        $this->refused('<script src="https://dash.pathranker.com/pr-collect.js" data-site="www.exemplo.com.br" defer></script>', 'data-key');
        $this->refused('<script src="https://dash.pathranker.com/pr-collect.js" data-key="" defer></script>', 'data-key');
        $this->refused('<script src="https://dash.pathranker.com/pr-collect.js" data-key="a b" defer></script>', 'caracteres');
        $this->refused('<script src="https://dash.pathranker.com/pr-collect.js" data-key=\'a"b\' defer></script>', 'caracteres');
        $this->refused('<script src="https://dash.pathranker.com/pr-collect.js" data-key="' . str_repeat('a', 257) . '" defer></script>', 'caracteres');
        $this->refused('<script src="https://dash.pathranker.com/pr-collect.js" data-key="k1" data-site="x onerror=alert(1)" defer></script>', 'data-site');
        $this->refused('<script src="https://dash.pathranker.com/pr-collect.js" data-key="k1" data-site="exemplo.com/caminho" defer></script>', 'data-site');
    }

    public function test_anything_around_or_inside_the_script_is_refused(): void
    {
        $tag = '<script src="https://dash.pathranker.com/pr-collect.js" data-key="k1" defer></script>';
        foreach ([
            $tag . '<script>alert(1)</script>', '<script>alert(1)</script>' . $tag, $tag . $tag, '<div>oi</div>' . $tag, $tag . '<img src=x onerror=alert(1)>',
            '<!-- um comentário -->' . $tag, "<style>body{display:none}</style>\n" . $tag, 'texto solto ' . $tag, $tag . ' texto solto',
            '<script src="https://dash.pathranker.com/pr-collect.js" data-key="k1" defer>alert(1)</script>', '<script src="https://dash.pathranker.com/pr-collect.js" data-key="k1" defer/>',
            '<script src="https://dash.pathranker.com/pr-collect.js" data-key="k1" defer>', '</script>' . $tag, '<script data-key="k1"></script><script src="https://dash.pathranker.com/pr-collect.js"></script>',
        ] as $snippet) {
            $this->refused($snippet);
        }
        $this->assertStringContainsString('Só um <script>', $this->refused($tag . $tag));
        $this->assertStringContainsString('Cole o código do Path Ranker inteiro', $this->refused('algum texto'));
        $this->assertStringContainsString('Cole o código do Path Ranker inteiro', $this->refused('<img src=x onerror=alert(1)>'));
    }

    public function test_attributes_that_run_code_or_change_the_script_are_refused_and_nothing_typed_is_printed_back(): void
    {
        foreach ([
            'onload="alert(1)"', 'onerror="alert(1)"', 'type="module"', 'crossorigin="anonymous"', 'nonce="x"', 'integrity="sha256-x"', 'referrerpolicy="no-referrer"', 'id="x"', 'class="x"',
            'style="display:none"', 'data-other="x"', 'src="https://evil.example/pr-collect.js"', '"', '=', 'data-key="k2"', 'defer defer',
        ] as $extra) {
            $this->refused('<script src="https://dash.pathranker.com/pr-collect.js" data-key="k1" ' . $extra . '></script>');
        }
        $this->assertStringContainsString('(onload)', $this->refused('<script src="https://dash.pathranker.com/pr-collect.js" data-key="k1" onload="alert(1)"></script>'));
        $this->assertStringContainsString('(type)', $this->refused('<script type="text/javascript" src="https://dash.pathranker.com/pr-collect.js" data-key="k1"></script>'));
        $this->assertStringContainsString('(data-other)', $this->refused('<script data-other="x" src="https://dash.pathranker.com/pr-collect.js" data-key="k1"></script>'));

        $this->collector('<script src="https://dash.pathranker.com/pr-collect.js" data-key=k1 defer></script>');
        $html = $this->home();
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringContainsString('data-key="k1"', $html);
    }

    public function test_a_stored_value_that_was_tampered_with_prints_nothing_and_what_is_printed_is_escaped(): void
    {
        foreach ([
            ['src' => 'https://evil.example/x.js', 'site' => '', 'key' => 'k1'],
            ['src' => 'https://dash.pathranker.com/pr-collect.js', 'site' => '', 'key' => 'k1"><script>alert(1)</script>'],
            ['src' => 'https://dash.pathranker.com/pr-collect.js"><script>alert(1)</script>', 'site' => '', 'key' => 'k1'],
            ['src' => 'https://dash.pathranker.com/pr-collect.js', 'site' => 'a" onload="alert(1)', 'key' => 'k1'],
        ] as $tampered) {
            pb_set_option('track_pathranker', json_encode($tampered));
            $this->assertNull(pb_pathranker_saved());
            $this->assertStringNotContainsString('alert(1)', $this->home());
            $this->assertSame('', pb_tracking_head());
        }
        pb_set_option('track_pathranker', 'not json');
        $this->assertNull(pb_pathranker_saved());
        $this->assertSame('<script src="https://h.example/pr-collect.js" data-key="a&amp;b" defer></script>', pb_pathranker_html(['src' => 'https://h.example/pr-collect.js', 'site' => '', 'key' => 'a&b']), 'the writer escapes by itself');
    }

    public function test_the_collector_is_only_in_public_pages_of_a_live_site_for_people_who_are_not_signed_in(): void
    {
        $this->collector(self::COLLECTOR);
        $this->assertStringContainsString('pr-collect.js', $this->home());

        pb_set_site_mode('construction', '');
        $this->assertStringNotContainsString('pr-collect.js', $this->home());
        pb_set_site_mode('live', '');

        $_SESSION['user_id'] = $this->admin;
        $this->assertStringNotContainsString('pr-collect.js', $this->home(), 'not for the person editing the site');
        $_SERVER['REQUEST_URI'] = '/admin/pages';
        ob_start();
        pb_admin('GET', '/admin/pages');
        $this->assertStringNotContainsString('pr-collect.js', ob_get_clean(), 'and never in the panel');
        $_SERVER['REQUEST_URI'] = '/';
        $_SESSION = [];

        $this->assertStringNotContainsString('pr-collect.js', $this->home(true), 'nor in a preview');
        $this->assertStringContainsString('pr-collect.js', $this->home());
    }

    public function test_the_screen_saves_it_shows_it_as_written_and_explains_a_refusal(): void
    {
        $_SESSION['user_id'] = $this->admin;
        $open = function (): string {
            ob_start();
            pb_admin('GET', '/admin/tracking');
            return ob_get_clean();
        };
        $screen = $open();
        $this->assertStringContainsString('Coletor de comportamento (Path Ranker)', $screen);
        $this->assertStringContainsString('name="pathranker"', $screen);
        $this->assertStringContainsString('Integrações', $screen, 'it says where to copy the snippet');

        pb_tracking_save('', '', true, "<SCRIPT SRC='https://dash.pathranker.com/pr-collect.js' data-key=abc123DEF-456></SCRIPT>");
        $this->assertStringContainsString('&lt;script src=&quot;https://dash.pathranker.com/pr-collect.js&quot; data-key=&quot;abc123DEF-456&quot; defer&gt;&lt;/script&gt;', $open(), 'the box shows what PageBrick wrote, not what was typed');

        $_POST = ['ga4' => '', 'meta' => '', 'consent' => '1', 'pathranker' => '<script src="https://evil.example/pr-collect.js" data-key="k1" onload="x()"></script>'];
        ob_start();
        pb_admin_tracking_save();
        $error = ob_get_clean();
        $this->assertStringContainsString('role="alert"', $error);
        $this->assertStringContainsString('(onload)', $error);
        $this->assertStringContainsString('evil.example', $error, 'the box keeps what was typed so it can be fixed');
        $this->assertSame('abc123DEF-456', pb_pathranker_saved()['key'], 'and the saved one is untouched');
    }
}
