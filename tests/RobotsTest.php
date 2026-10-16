<?php

use PHPUnit\Framework\TestCase;

/** /robots.txt: the panel is kept out of every site, and themes and plugins can add to it. */
final class RobotsTest extends TestCase
{
    protected function setUp(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config();
        pb_migrate();
        pb_set_option('site_title', 'Site');
        pb_set_option('site_url', 'https://exemplo.test');
        pb_test_new_request();
    }

    protected function tearDown(): void
    {
        pb_test_new_request();
    }

    private function robots(): string
    {
        $_SERVER['REQUEST_URI'] = '/robots.txt';
        ob_start();
        pb_public('GET', '/robots.txt');
        return ob_get_clean();
    }

    public function test_the_panel_is_kept_out_before_everything_else_is_allowed(): void
    {
        $this->assertSame("User-agent: *\nDisallow: /admin/\nDisallow: /admin\$\nAllow: /\nSitemap: https://exemplo.test/sitemap.xml\n", $this->robots());
    }

    public function test_the_disallow_comes_first_for_readers_that_take_the_first_matching_rule(): void
    {
        $lines = explode("\n", $this->robots());
        $this->assertLessThan(array_search('Allow: /', $lines, true), array_search('Disallow: /admin/', $lines, true));
    }

    public function test_a_page_whose_address_only_starts_with_admin_is_not_shut_out(): void
    {
        $robots = $this->robots();
        $this->assertStringNotContainsString("Disallow: /admin\n", $robots, 'a plain "/admin" would also close /administracao');
    }

    public function test_a_theme_or_plugin_adds_lines_with_the_robots_txt_filter(): void
    {
        pb_add_filter('robots_txt', fn(string $text) => $text . "\nUser-agent: GPTBot\nDisallow: /admin/\nDisallow: /admin\$\nAllow: /\n");
        $robots = $this->robots();
        $this->assertStringContainsString("User-agent: GPTBot\nDisallow: /admin/", $robots);
        $this->assertStringStartsWith("User-agent: *\nDisallow: /admin/", $robots);
    }

    public function test_a_filter_that_returns_nothing_usable_leaves_the_standard_text(): void
    {
        pb_add_filter('robots_txt', fn() => null);
        $this->assertStringStartsWith("User-agent: *\nDisallow: /admin/", $this->robots());
        pb_test_new_request();
        pb_add_filter('robots_txt', fn() => '');
        $this->assertStringContainsString('Sitemap: https://exemplo.test/sitemap.xml', $this->robots());
    }
}
