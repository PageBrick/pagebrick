<?php

use PHPUnit\Framework\TestCase;

/** What the panel's text editor (tables, dividers, pictures of the media library) is allowed to save, and how it is shown. */
final class RichTextTest extends TestCase
{
    private int $media;

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config();
        pb_migrate();
        pb_set_option('site_title', 'Acme');
        pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
        pb_seed_demo();
        $png = tempnam(sys_get_temp_dir(), 'pb') . '.png';
        $image = imagecreatetruecolor(800, 400);
        imagefill($image, 0, 0, imagecolorallocate($image, 210, 78, 43));
        imagepng($image, $png);
        $this->media = pb_media_store($png, 'foto.png');
        pb_db()->prepare('UPDATE ' . pb_table('media') . ' SET alt = ? WHERE id = ?')->execute(['Tijolos laranja', $this->media]);
    }

    public function test_tables_dividers_and_strikethrough_are_kept_without_attributes(): void
    {
        $html = '<table class="x" style="color:red" onclick="a()"><tbody><tr><th colspan="1" rowspan="1"><p>Item</p></th><th><p>Preço</p></th></tr>'
            . '<tr><td><p>Mesa</p></td><td>R$ 10</td></tr></tbody></table><hr class="y"><p><s>velho</s></p>';
        $this->assertSame('<table><tbody><tr><th>Item</th><th>Preço</th></tr><tr><td>Mesa</td><td>R$ 10</td></tr></tbody></table><hr><p><s>velho</s></p>',
            html_entity_decode(pb_sanitize_html($html), ENT_HTML5, 'UTF-8'));
    }

    public function test_a_list_item_with_one_paragraph_keeps_just_its_text_but_two_paragraphs_stay(): void
    {
        $this->assertSame('<ul><li>um</li><li><p>dois</p><p>mais</p></li></ul>', pb_sanitize_html('<ul><li><p>um</p></li><li><p>dois</p><p>mais</p></li></ul>'));
        $this->assertSame('<ol><li>1</li></ol>', pb_sanitize_html('<ol><li>1</li></ol>'), 'the old editor\'s lists are untouched');
    }

    public function test_blank_lines_at_the_end_are_dropped_and_a_blank_text_is_empty(): void
    {
        $this->assertSame('<p>Oi</p>', pb_sanitize_html('<p>Oi</p><p></p><p><br></p>'));
        $this->assertSame('', pb_sanitize_html('<p></p>'));
        $this->assertSame('', pb_sanitize_html('<div><br></div>'));
        $this->assertSame('<p>a</p><p></p><p>b</p>', pb_sanitize_html('<p>a</p><p></p><p>b</p>'), 'a blank line between two paragraphs is the writer\'s choice');
    }

    public function test_a_picture_is_rebuilt_from_its_media_id(): void
    {
        $evil = '<img src="https://evil.example/x.png" onerror="alert(1)" style="x" data-media="' . $this->media . '" alt="Quadro" width="9999" height="1">';
        $out = pb_sanitize_html($evil);
        $media = pb_media_find($this->media);
        $this->assertSame(sprintf('<img src="%s" alt="Quadro" width="%d" height="%d" data-media="%d">', pb_media_url($media), $media['width'], $media['height'], $this->media), $out);
        $this->assertStringNotContainsString('evil', $out);
        $this->assertStringContainsString('alt="Tijolos laranja"', pb_sanitize_html('<img data-media="' . $this->media . '" alt="  ">'), 'no description: the library\'s');
    }

    public function test_a_picture_that_is_not_in_the_library_is_dropped(): void
    {
        foreach (['<img src="https://evil.example/x.png">', '<img data-media="99999" src="/content/uploads/x.webp">', '<img data-media="1 OR 1=1">', '<img data-media="abc">', '<img src="data:image/png;base64,AAAA">'] as $html) {
            $this->assertSame('<p>texto</p>', pb_sanitize_html('<p>texto</p>' . $html), $html);
        }
    }

    public function test_the_site_shows_the_picture_from_the_library_and_a_deleted_one_disappears(): void
    {
        $stored = pb_sanitize_html('<p>Veja</p><img data-media="' . $this->media . '" alt="Quadro">');
        $media = pb_media_find($this->media);
        $field = new PbValue(['type' => 'richtext'], $stored);
        $this->assertSame(sprintf('<p>Veja</p><img src="%s" alt="Quadro" width="%d" height="%d" loading="lazy" decoding="async">', pb_media_url($media), $media['width'], $media['height']), (string) $field);

        pb_media_delete($this->media);
        $this->assertSame('<p>Veja</p>', (string) new PbValue(['type' => 'richtext'], $stored));
    }

    public function test_text_without_pictures_is_shown_exactly_as_stored(): void
    {
        $html = '<div>Texto do editor antigo<br>com quebra</div><ul><li>um</li></ul>';
        $this->assertSame($html, (string) new PbValue(['type' => 'richtext'], $html));
        $this->assertSame($html, pb_richtext_html($html, true));
    }

    public function test_the_editor_gets_the_picture_with_its_media_id_and_current_address(): void
    {
        $stored = pb_sanitize_html('<img data-media="' . $this->media . '" alt="Quadro">');
        $input = pb_field_input(['type' => 'richtext', 'label' => 'Texto'], $stored, 'f[body]');
        $this->assertStringContainsString('<textarea', $input);
        $this->assertStringContainsString('data-richtext', $input);
        $this->assertStringContainsString('data-media=&quot;' . $this->media . '&quot;', $input);
        $this->assertStringNotContainsString('trix', $input);
    }

    public function test_the_content_api_gives_pictures_in_rich_text_full_addresses(): void
    {
        $body = pb_sanitize_html('<p>Oi</p><img data-media="' . $this->media . '" alt="Quadro">');
        $id = pb_page_create('Com foto', 'page', ['body' => $body], 'published');
        $page = pb_page_find($id);
        $json = pb_content_page($page);
        $this->assertStringContainsString('src="http://localhost/content/uploads/', $json['fields']['body']);
        $this->assertStringContainsString('alt="Quadro"', $json['fields']['body']);
        $this->assertStringNotContainsString('data-media', $json['fields']['body']);
    }
}
