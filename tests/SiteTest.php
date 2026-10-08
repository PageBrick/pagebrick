<?php

use PHPUnit\Framework\TestCase;

/** Pages, revisions, the example site, the default theme, public routes and media, on a real database. */
final class SiteTest extends TestCase
{
    protected function setUp(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config();
        pb_migrate();
        pb_set_option('site_title', 'Padaria Exemplo');
        pb_set_option('site_url', 'https://padaria.example');
        pb_seed_demo();
    }

    private function page(string $slug): array
    {
        return pb_page_by_slug($slug) ?? throw new LogicException("No page $slug");
    }

    private function visit(string $path): string
    {
        $_SERVER['REQUEST_URI'] = $path;
        ob_start();
        pb_public('GET', $path);
        return ob_get_clean();
    }

    /** The form the panel would send for a page, with some changes. */
    private function form(array $page, array $changes = []): array
    {
        return array_replace_recursive([
            'title' => $page['title'], 'slug' => $page['slug'], 'status' => $page['status'],
            'seo_title' => $page['seo_title'], 'seo_description' => $page['seo_description'], 'f' => $page['data'],
        ], $changes);
    }

    public function test_example_site_is_ready_to_publish(): void
    {
        $home = $this->page('inicio');
        $this->assertSame($home['id'], pb_home_page_id());
        foreach (['sobre', 'servicos', 'contato'] as $slug) {
            $this->assertSame('published', $this->page($slug)['status']);
        }
        $this->assertSame(['Início', 'Sobre', 'Serviços', 'Contato'], array_column(pb_menu('main'), 'label'));
        $this->assertSame('page:' . $this->page('servicos')['id'], $home['data']['hero']['button_link']);
        $this->assertFalse($home['data']['numbers']['_visible'], 'example numbers start hidden');
        $this->assertFalse($home['data']['testimonials']['_visible'], 'example testimonials start hidden');
    }

    public function test_every_example_page_renders_through_the_default_theme(): void
    {
        $home = $this->visit('/');
        $this->assertStringContainsString('<h1>Diga aqui, em uma frase, o que sua empresa faz</h1>', $home);
        $this->assertStringContainsString('Primeiro serviço', $home);
        $this->assertStringContainsString('Vamos conversar?', $home);
        $this->assertStringNotContainsString('Em números', $home);
        $this->assertStringNotContainsString('O que dizem nossos clientes', $home);
        $this->assertStringContainsString('<link rel="canonical" href="https://padaria.example/">', $home);
        $this->assertStringContainsString('Feito com PageBrick', $home);

        foreach (['/sobre' => 'Nossa história', '/servicos' => 'Pedir orçamento', '/contato' => 'Contato'] as $path => $text) {
            $this->assertStringContainsString($text, $this->visit($path), $path);
        }
        $this->assertStringContainsString('Página não encontrada', $this->visit('/nao-existe'));
    }

    public function test_switched_off_sections_and_drafts_disappear_from_the_site(): void
    {
        $home = $this->page('inicio');
        pb_page_save($home['id'], $this->form($home, ['f' => ['about' => ['_visible' => '0']]]), null);
        $this->assertStringNotContainsString('Quem somos', $this->visit('/'));

        $about = $this->page('sobre');
        pb_page_save($about['id'], $this->form($about, ['status' => 'draft']), null);
        $this->assertStringContainsString('Página não encontrada', $this->visit('/sobre'));
        $this->assertNotContains('Sobre', array_column(pb_menu('main'), 'label'));
        $this->assertStringNotContainsString('/sobre', pb_sitemap_xml());
    }

    public function test_user_content_is_escaped_on_the_site(): void
    {
        pb_set_option('site_title', '<script>alert(1)</script>');
        $home = $this->page('inicio');
        pb_page_save($home['id'], $this->form($home, ['f' => ['hero' => ['title' => '<img src=x onerror=alert(1)>']]]), null);

        $html = $this->visit('/');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    public function test_settings_feed_the_layout(): void
    {
        pb_save_settings([
            'identity' => ['color' => '#ffe600', 'fonts' => 'elegante'],
            'contact' => ['whatsapp' => '(19) 99999-8888', 'email' => 'oi@padaria.example', 'address' => 'Rua A, 10'],
            'footer' => ['credit' => 'hide'],
        ]);
        $html = $this->visit('/contato');

        $this->assertStringContainsString('https://wa.me/5519999998888', $html);
        $this->assertStringContainsString('mailto:oi@padaria.example', $html);
        $this->assertStringContainsString('--on-brand: #171923', $html, 'dark text on a yellow brand');
        $this->assertStringContainsString('class="fonts-elegante"', $html);
        $this->assertStringNotContainsString('Feito com PageBrick', $html);
    }

    public function test_page_rules(): void
    {
        $second = pb_page_create('Serviços', 'page');
        $this->assertSame('servicos-2', pb_page_find($second)['slug'], 'slugs never collide');

        $about = $this->page('sobre');
        try {
            pb_page_save($about['id'], $this->form($about, ['slug' => 'contato']), null);
            $this->fail('Duplicate slug accepted');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('já é usado', $e->getMessage());
        }

        $home = $this->page('inicio');
        $this->expectExceptionMessage('precisa ficar publicada');
        pb_page_save($home['id'], $this->form($home, ['status' => 'draft']), null);
    }

    public function test_home_page_cannot_be_deleted(): void
    {
        $this->expectException(InvalidArgumentException::class);
        pb_page_delete(pb_home_page_id());
    }

    public function test_revisions_keep_the_last_ten_and_restore_content(): void
    {
        $page = $this->page('sobre');
        for ($i = 1; $i <= 12; $i++) {
            pb_page_save($page['id'], $this->form($page, ['f' => ['intro' => "Versão $i"]]), null);
        }
        $revisions = pb_page_revisions($page['id']);
        $this->assertCount(PB_REVISIONS_KEPT, $revisions);

        // The newest revision holds "Versão 11" (saved right before "Versão 12").
        pb_page_restore((int) $revisions[0]['id'], null);
        $this->assertSame('Versão 11', $this->page('sobre')['data']['intro']);
        $this->assertCount(PB_REVISIONS_KEPT, pb_page_revisions($page['id']), 'restoring also saves a revision');
    }

    public function test_menu_skips_bad_links_and_uses_page_titles(): void
    {
        pb_save_menu('main', [
            ['label' => '', 'link' => ['page' => 'page:' . $this->page('sobre')['id']]],
            ['label' => 'Instagram', 'link' => ['page' => '', 'url' => 'instagram.com/padaria']],
            ['label' => 'Perigo', 'link' => ['page' => '', 'url' => 'javascript:alert(1)']],
            ['label' => 'Sumiu', 'link' => ['page' => 'page:9999']],
        ]);
        $this->assertSame([
            ['label' => 'Sobre', 'url' => '/sobre', 'current' => false],
            ['label' => 'Instagram', 'url' => 'https://instagram.com/padaria', 'current' => false],
        ], pb_menu('main'));
    }

    public function test_sitemap_lists_published_pages(): void
    {
        $xml = pb_sitemap_xml();
        $this->assertStringContainsString('<loc>https://padaria.example/</loc>', $xml);
        $this->assertStringContainsString('<loc>https://padaria.example/contato</loc>', $xml);
        $this->assertStringNotContainsString('/inicio<', $xml, 'the home page is listed once, as /');
    }

    public function test_images_are_resized_to_webp_and_fake_images_rejected(): void
    {
        $png = tempnam(sys_get_temp_dir(), 'pb') . '.png';
        $image = imagecreatetruecolor(3000, 1500);
        imagefill($image, 0, 0, imagecolorallocate($image, 210, 78, 43));
        imagepng($image, $png);

        $media = pb_media_find(pb_media_store($png, 'foto grande.png'));
        $this->assertSame('image/png', $media['mime']);
        $this->assertSame([1920, 960], [(int) $media['width'], (int) $media['height']]);
        $this->assertStringEndsWith('.webp', $media['path']);
        $this->assertFileExists(pb_uploads_dir() . '/' . $media['thumb_path']);
        $this->assertSame(PB_THUMB_WIDTH, getimagesize(pb_uploads_dir() . '/' . $media['thumb_path'])[0]);

        pb_media_delete((int) $media['id']);
        $this->assertFileDoesNotExist(pb_uploads_dir() . '/' . $media['path']);

        $fake = tempnam(sys_get_temp_dir(), 'pb');
        file_put_contents($fake, '<?php system($_GET["c"]);');
        $this->expectExceptionMessage('Tipo de arquivo não aceito');
        pb_media_store($fake, 'foto.jpg');
    }
}
