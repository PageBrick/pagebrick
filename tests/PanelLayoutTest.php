<?php

use PHPUnit\Framework\TestCase;

/** The panel has to fit a phone, and look the same in dark whether it was chosen or comes from the system: rules a real run showed were needed. */
final class PanelLayoutTest extends TestCase
{
    private string $css;

    protected function setUp(): void
    {
        $this->css = (string) file_get_contents(PB_ROOT . '/core/assets/admin.css');
    }

    /** The declarations inside the block that opens with $selector (it holds no nested braces), without the comments. */
    private function block(string $selector): array
    {
        $start = strpos($this->css, $selector . ' {');
        $this->assertNotFalse($start, "$selector is missing from admin.css");
        $from = $start + strlen($selector) + 2;
        $body = substr($this->css, $from, strpos($this->css, '}', $from) - $from);
        return array_values(array_filter(array_map(fn($line) => trim(preg_replace('~\s+~', ' ', $line)), explode(';', preg_replace('~/\*.*?\*/~s', '', $body)))));
    }

    public function test_the_page_editor_is_a_column_that_can_shrink_to_the_screen(): void
    {
        // A plain "1fr" grows to the widest field (the rich text toolbar) and the page becomes wider than the phone.
        // The bar and the list of screens beside the page take about 18 rem, so the editor stacks earlier than the phone breakpoint.
        $this->assertMatchesRegularExpression('~@media \(max-width: 1100px\) \{ \.editor \{ grid-template-columns: minmax\(0, 1fr\); \}~', $this->css);
        $this->assertDoesNotMatchRegularExpression('~\.editor \{ grid-template-columns: 1fr; \}~', $this->css);
    }

    public function test_the_rich_text_toolbar_wraps_instead_of_widening_the_page(): void
    {
        $this->assertMatchesRegularExpression('~trix-toolbar \.trix-button-row \{[^}]*flex-wrap: wrap~', $this->css);
    }

    public function test_dark_is_the_same_whether_it_was_chosen_or_comes_from_the_system(): void
    {
        $chosen = $this->block(':root[data-theme="dark"]');
        $system = $this->block(':root:not([data-theme="light"])');
        $this->assertNotEmpty($chosen);
        $this->assertSame($chosen, $system, 'the two dark blocks of admin.css must stay equal: forcing dark and following a dark system paint the same panel');
    }

    public function test_dark_only_changes_what_the_light_theme_defines(): void
    {
        $names = fn(array $declarations) => array_map(fn($d) => trim(explode(':', $d)[0]), $declarations);
        $unknown = array_diff($names($this->block(':root[data-theme="dark"]')), $names($this->block(':root')));
        $this->assertSame([], array_values($unknown), 'a token that only dark defines is a typo, and the light panel would lack it');
    }

    public function test_the_narrow_screen_has_a_drawer_and_a_way_through_without_javascript(): void
    {
        $this->assertMatchesRegularExpression('~@media \(max-width: 860px\) \{[^@]*\.js \.nav \{ position: fixed;~s', $this->css);
        $this->assertStringContainsString('.js.nav-open .nav {', $this->css);
        $this->assertStringContainsString('html:not(.js) .nav {', $this->css, 'the list stays on top of the page when scripts do not run');
        $this->assertStringContainsString('.nav-toggle { display: none; }', $this->css, 'the button only exists on a narrow screen');
    }

    public function test_every_color_of_the_panel_comes_from_a_token_the_two_themes_share(): void
    {
        // The plugins draw their screens with these names: they must keep existing, with the same meaning.
        foreach (['--bg', '--card', '--text', '--muted', '--line', '--accent', '--on-accent', '--brick', '--ok', '--ok-bg', '--err', '--err-bg'] as $token) {
            $this->assertContains($token, array_map(fn($d) => trim(explode(':', $d)[0]), $this->block(':root')), "$token is part of what plugins can rely on");
        }
    }

    public function test_the_steps_of_an_update_end_in_a_main_button_that_takes_the_focus(): void
    {
        // A bare link under the log looked like a leftover of the previous panel; the form that held the focus is gone.
        $js = (string) file_get_contents(PB_ROOT . '/core/assets/admin.js');
        $this->assertMatchesRegularExpression("~done\(\) \{[^}]*next\.className = 'button';[^}]*next\.focus\(\);~s", $js);
        $this->assertStringContainsString('a.button { margin-top: 0; }', $this->css, 'a link made a button has no top margin: it sits right under the log');
        $this->assertStringContainsString('.update-log + a.button, .action-row a.button { scroll-margin-bottom: 2rem; }', $this->css, 'and when it takes the focus the page scrolls to leave some air under it');
    }

    public function test_continue_and_go_back_sit_side_by_side_on_the_updates_screen(): void
    {
        $js = (string) file_get_contents(PB_ROOT . '/core/assets/admin.js');
        $view = (string) file_get_contents(PB_ROOT . '/core/views/updates.php');
        // The buttons of the screen share one row: the log goes above it and "Continuar" opens it, next to "Voltar para a versão X".
        $this->assertStringContainsString('<div class="action-row"><?= $updateForm ?? \'\' ?><?= $restoreForm ?></div>', $view);
        $this->assertStringContainsString("row ? row.before(log) : form.after(log);", $js);
        $this->assertStringContainsString('row ? row.prepend(next) : log.after(next);', $js);
        $this->assertMatchesRegularExpression('~\.action-row \{ display: flex; flex-wrap: wrap;[^}]*gap:~', $this->css, 'one row that wraps on a phone');
        $this->assertStringContainsString('.action-row form, .action-row button, .action-row a.button { margin: 0; }', $this->css, 'the buttons carry no margin of their own: the row spaces them');
        $this->assertStringContainsString('.action-row form[hidden] { display: none; }', $this->css, 'the form that ran leaves no gap in the row');
        // After an update, "Voltar" names the version this page was opened with, not the older one it still shows.
        $this->assertStringContainsString("text.back.replace('%s', text.from)", $js);
        $this->assertStringContainsString('data-back="', $view);
    }

    public function test_a_zip_travels_once_and_a_stopped_install_keeps_the_form(): void
    {
        $js = (string) file_get_contents(PB_ROOT . '/core/assets/admin.js');
        // Receiving is the only step that needs the file; the others only say what to do with the one that is waiting.
        $this->assertStringContainsString("if (!withFile) body.delete('package');", $js);
        $this->assertStringContainsString("post(text.url, form, {type, slug, step}, step === 'receive')", $js);
        // An answer that is not JSON (a firewall page, a timeout) names its HTTP status instead of a bare "stopped".
        $this->assertStringContainsString('(HTTP ${response.status})', $js);
        // After a failed install the form is back with its file still chosen (the update of PageBrick has no file to keep),
        // and a new run starts from a clean log.
        $this->assertSame(1, substr_count($js, 'form.hidden = false;'));
        $this->assertMatchesRegularExpression('~\[data-package-steps\].*?catch \(error\) \{\s*log\.failed\(error\);\s*form\.hidden = false;~s', $js);
        $this->assertStringContainsString("document.querySelectorAll('.update-log, a.button[data-next]').forEach((old) => old.remove());", $js);
    }

    public function test_every_way_back_is_a_secondary_button_with_the_same_icon(): void
    {
        foreach (['updates', 'themes', 'plugins'] as $screen) {
            $view = (string) file_get_contents(PB_ROOT . "/core/views/$screen.php");
            $this->assertMatchesRegularExpression("~'secondary', [^\\n]*'undo'\\)~", $view, "$screen: \"Voltar\" is a button like the others, not an underlined link");
        }
        $this->assertStringContainsString("'undo' =>", (string) file_get_contents(PB_ROOT . '/core/panel.php'));
    }
}
