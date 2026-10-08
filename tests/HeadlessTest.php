<?php

use PHPUnit\Framework\TestCase;

/** The content API (core/headless.php): the site's content as JSON for front ends built outside PageBrick. */
final class HeadlessTest extends TestCase
{
    protected function setUp(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config();
        pb_migrate();
        pb_set_option('site_title', 'Padaria Exemplo');
        pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
        pb_seed_demo();
        pb_activate_plugin('blog');
    }

    private function get(string $path, array $query = []): array
    {
        pb_test_new_request();
        pb_load_plugins();
        $_GET = $query;
        $_SERVER['REQUEST_URI'] = $path;
        ob_start();
        pb_public('GET', $path);
        $json = json_decode(ob_get_clean(), true);
        $_GET = [];
        $this->assertIsArray($json, "$path answers JSON");
        return $json;
    }

    public function test_site_settings_and_menus(): void
    {
        $site = $this->get('/api/v1/site');
        $this->assertSame('Padaria Exemplo', $site['name']);
        $this->assertSame('pt-BR', $site['locale']);
        $this->assertSame('#d24e2b', $site['settings']['identity']['color']);
        $this->assertSame(['label' => 'Sobre', 'link' => '/sobre'], $site['menus']['main'][1]);
        $this->assertArrayHasKey('footer', $site['menus']);
    }

    public function test_only_published_pages_with_their_fields(): void
    {
        $draft = pb_page_create('Rascunho secreto', 'page', [], 'draft');
        $slugs = array_column($this->get('/api/v1/pages')['pages'], 'slug');
        $this->assertContains('sobre', $slugs);
        $this->assertNotContains('rascunho-secreto', $slugs);
        $this->assertSame(['error' => 'not_found'], $this->get('/api/v1/pages/rascunho-secreto'));
        $this->assertNotNull($draft);

        $home = $this->get('/api/v1/pages/inicio');
        $this->assertSame('/', $home['path']);
        $this->assertTrue($home['home']);
        $this->assertSame('/servicos', $home['fields']['hero']['button_link'], 'links to pages are paths');
        $this->assertStringStartsWith('http://localhost/content/uploads/', $home['fields']['hero']['image']['url'], 'images have full addresses');
        $this->assertSame(960, $home['fields']['hero']['image']['width']);
        $this->assertNull($home['fields']['testimonials'], 'a hidden section is null');
        $this->assertStringContainsString('<p>', $home['fields']['about']['text'], 'rich text is HTML');
        $this->assertCount(3, $home['fields']['services']['items']);
    }

    public function test_unknown_addresses_answer_json_404(): void
    {
        $this->assertSame(['error' => 'not_found'], $this->get('/api/v1/nada'));
        $this->assertSame(['error' => 'not_found'], $this->get('/api/v2/pages'));
    }

    public function test_values_follow_the_documented_rules(): void
    {
        $fields = [
            'secret' => ['type' => 'password', 'label' => 'Senha'],
            'link' => ['type' => 'link', 'label' => 'Link'],
            'outside' => ['type' => 'link', 'label' => 'Link'],
            'photo' => ['type' => 'image', 'label' => 'Foto'],
        ];
        $_SERVER['SCRIPT_NAME'] = '/site/index.php'; // installed in a subfolder
        $json = pb_content_json($fields, ['secret' => 'x', 'link' => 'page:' . pb_page_by_slug('sobre')['id'], 'outside' => 'https://example.com', 'photo' => '']);
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $this->assertSame(['link' => '/sobre', 'outside' => 'https://example.com', 'photo' => null], $json, 'no passwords; paths from the site root');
    }

    public function test_blog_endpoints(): void
    {
        $tips = pbb_save_category(0, 'Dicas', '', 0, '');
        $id = pbb_create('Pão de fermentação natural');
        pbb_save($id, ['title' => 'Pão de fermentação natural', 'slug' => '', 'status' => 'published', 'published_on' => date('Y-m-d'),
            'categories' => [$tips], 'f' => ['summary' => 'Como fazer.', 'body' => '<p>Passo a passo.</p>']]);
        pbb_create('Rascunho');

        $list = $this->get('/api/v1/blog');
        $this->assertSame(1, $list['total']);
        $this->assertSame('/blog/pao-de-fermentacao-natural', $list['posts'][0]['path']);
        $this->assertArrayNotHasKey('body', $list['posts'][0]['fields'], 'the list stays light');
        $this->assertSame([['name' => 'Dicas', 'slug' => 'dicas']], $list['posts'][0]['categories']);
        $this->assertSame('/blog/categoria/dicas', $list['categories'][0]['path']);
        $this->assertSame(1, $this->get('/api/v1/blog', ['category' => 'dicas'])['total']);
        $this->assertSame(['error' => 'not_found'], $this->get('/api/v1/blog', ['category' => 'nao-existe']));

        $post = $this->get('/api/v1/blog/pao-de-fermentacao-natural');
        $this->assertSame('<p>Passo a passo.</p>', $post['fields']['body']);
        $this->assertSame(['error' => 'not_found'], $this->get('/api/v1/blog/rascunho'));
    }
}
