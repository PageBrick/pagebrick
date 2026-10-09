<?php

use PHPUnit\Framework\TestCase;

/** The panel has to fit a phone: these are the layout rules a real phone run showed were needed (the browser can't be run here). */
final class PanelLayoutTest extends TestCase
{
    private string $css;

    protected function setUp(): void
    {
        $this->css = (string) file_get_contents(PB_ROOT . '/core/assets/admin.css');
    }

    public function test_the_page_editor_is_a_column_that_can_shrink_to_the_screen(): void
    {
        // A plain "1fr" grows to the widest field (the rich text toolbar) and the page becomes wider than the phone.
        $this->assertMatchesRegularExpression('~@media \(max-width: 860px\) \{ \.editor \{ grid-template-columns: minmax\(0, 1fr\); \} \}~', $this->css);
        $this->assertDoesNotMatchRegularExpression('~\.editor \{ grid-template-columns: 1fr; \}~', $this->css);
    }

    public function test_the_rich_text_toolbar_wraps_instead_of_widening_the_page(): void
    {
        $this->assertMatchesRegularExpression('~trix-toolbar \.trix-button-row \{[^}]*flex-wrap: wrap~', $this->css);
    }
}
