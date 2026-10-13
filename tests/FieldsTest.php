<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Field cleaning, HTML sanitizing, safe output and small helpers. No database needed. */
final class FieldsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pb_config'] = null;
    }

    public function test_slugs(): void
    {
        $this->assertSame('servicos-precos', pb_slugify('Serviços & Preços'));
        $this->assertSame('agua-fria-2025', pb_slugify('  ÁGUA Fria!! 2025 '));
        $this->assertSame('', pb_slugify('!!!'));
    }

    public static function dangerousHtml(): array
    {
        return [
            'script and event attribute' => ['<p onclick="roubar()">Oi <script>alert(1)</script><strong>forte</strong></p>', '<p>Oi <strong>forte</strong></p>'],
            'javascript link' => ['<a href="javascript:alert(1)">clique</a>', '<a>clique</a>'],
            'image with onerror' => ['<img src=x onerror=alert(1)>texto', 'texto'],
            'svg with script' => ['<svg><script>alert(1)</script></svg>ok', 'ok'],
            'style attribute and span' => ['<span style="position:fixed">t</span>', 't'],
            'iframe' => ['<iframe src="https://mal.example"></iframe>fim', 'fim'],
            'safe link keeps only href' => ['<a href="https://ok.example" target="_blank" class="x">ok</a>', '<a href="https://ok.example">ok</a>'],
            'h1 becomes h2' => ['<h1>Título</h1>', '<h2>Título</h2>'],
            'comment removed' => ['a<!-- segredo -->b', 'ab'],
        ];
    }

    #[DataProvider('dangerousHtml')]
    public function test_rich_text_is_sanitized(string $input, string $expected): void
    {
        $this->assertSame($expected, pb_sanitize_html($input));
    }

    public function test_sanitizer_keeps_portuguese_text(): void
    {
        $this->assertSame('<p>Atenção: são três opções</p>', html_entity_decode(pb_sanitize_html('<p>Atenção: são três opções</p>'), ENT_HTML5, 'UTF-8'));
    }

    public function test_collect_cleans_every_type_and_drops_unknown_keys(): void
    {
        $defs = [
            'title' => ['type' => 'text'],
            'site' => ['type' => 'url'],
            'mail' => ['type' => 'email'],
            'photo' => ['type' => 'image'],
            'color' => ['type' => 'color', 'default' => '#d24e2b'],
            'size' => ['type' => 'select', 'options' => ['p' => 'P', 'g' => 'G'], 'default' => 'p'],
            'go' => ['type' => 'link'],
            'items' => ['type' => 'list', 'fields' => ['name' => ['type' => 'text']]],
        ];
        $data = pb_collect_fields($defs, [
            'title' => "  Olá\x00 ",
            'site' => 'javascript:alert(1)',
            'mail' => 'nao-e-email',
            'photo' => '12abc',
            'color' => 'red',
            'size' => 'xxl',
            'go' => ['page' => 'page:7', 'url' => 'https://ignored.example'],
            'items' => [['name' => 'Ana'], ['name' => ''], 'lixo', ['name' => 'Bia', 'extra' => 'x']],
            'hacker' => 'campo que não existe',
        ]);

        $this->assertSame([
            'title' => 'Olá',
            'site' => '',
            'mail' => '',
            'photo' => 0,
            'color' => '#d24e2b',
            'size' => 'p',
            'go' => 'page:7',
            'items' => [['name' => 'Ana'], ['name' => 'Bia']],
        ], $data);
    }

    public function test_urls(): void
    {
        $this->assertSame('https://www.empresa.com.br', pb_clean_url('www.empresa.com.br'));
        $this->assertSame('mailto:a@b.com', pb_clean_url('mailto:a@b.com'));
        $this->assertSame('/contato', pb_clean_url('/contato'));
        $this->assertSame('', pb_clean_url('data:text/html,<script>'));
        $this->assertSame('', pb_clean_url('https://x.com/"onmouseover="alert(1)'));
    }

    public function test_sections_are_visible_unless_switched_off(): void
    {
        $def = ['type' => 'group', 'toggle' => true, 'fields' => ['title' => ['type' => 'text']]];

        $this->assertTrue(pb_collect_value($def, null)['_visible'], 'new sections start visible');
        $this->assertTrue(pb_collect_value($def, ['_visible' => '1', 'title' => 'x'])['_visible']);
        $this->assertFalse(pb_collect_value($def, ['_visible' => '0', 'title' => 'x'])['_visible']);

        $page = new PbGroup(['hero' => $def], ['hero' => ['_visible' => false, 'title' => 'x']]);
        $this->assertFalse($page->hero->visible());
    }

    public function test_values_are_escaped_when_printed(): void
    {
        $group = new PbGroup(
            ['t' => ['type' => 'text'], 'a' => ['type' => 'textarea'], 'r' => ['type' => 'richtext'], 'm' => ['type' => 'email'], 'p' => ['type' => 'tel']],
            ['t' => '<b>oi</b>', 'a' => "linha 1\n<i>linha 2</i>", 'r' => '<p><strong>já limpo</strong></p>', 'm' => 'a@b.com', 'p' => '(19) 3333-4444'],
        );

        $this->assertSame('&lt;b&gt;oi&lt;/b&gt;', (string) $group->t);
        $this->assertSame("linha 1<br>\n&lt;i&gt;linha 2&lt;/i&gt;", (string) $group->a);
        $this->assertSame('<p><strong>já limpo</strong></p>', (string) $group->r);
        $this->assertSame('mailto:a@b.com', (string) $group->m);
        $this->assertSame('tel:1933334444', (string) $group->p);
        $this->assertSame('<b>oi</b>', $group->t->raw());
        $this->assertTrue($group->nao_existe->isEmpty());
    }

    public function test_whatsapp_links(): void
    {
        $this->assertSame('https://wa.me/5519999998888', pb_whatsapp_url('(19) 99999-8888'));
        $this->assertSame('https://wa.me/5519999998888', pb_whatsapp_url('+55 19 99999-8888'));
        $this->assertSame('https://wa.me/5519999998888', pb_whatsapp_url('019 99999-8888'));
        $this->assertSame('https://wa.me/5519999998888?text=Ol%C3%A1%21', pb_whatsapp_url('19999998888', 'Olá!'));
        $this->assertSame('', pb_whatsapp_url(''));
    }

    public function test_brand_colors_stay_readable(): void
    {
        $this->assertSame('#171923', pb_text_color_on('#ffe600'), 'dark text on yellow');
        $this->assertSame('#ffffff', pb_text_color_on('#171923'), 'white text on dark');
        $this->assertGreaterThanOrEqual(4.5, pb_contrast(pb_readable_color('#ffe600'), '#ffffff'));
        $this->assertSame('#1d4e89', pb_readable_color('#1d4e89'), 'dark colors are already readable');
    }
}
