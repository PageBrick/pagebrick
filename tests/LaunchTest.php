<?php

use PHPUnit\Framework\TestCase;

/** What the panel says about a live site that still has the installer's example text, or that still opens without the padlock. */
final class LaunchTest extends TestCase
{
    private string $dir;
    private int $admin;
    private int $editor;

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $this->dir = sys_get_temp_dir() . '/pb-launch-' . bin2hex(random_bytes(4));
        mkdir("$this->dir/backups", 0777, true);
        mkdir("$this->dir/uploads", 0777, true);
        file_put_contents("$this->dir/.htaccess", "Options -Indexes\n");
        $GLOBALS['pb_config'] = pb_test_config(['htaccess_file' => "$this->dir/.htaccess", 'backups_dir' => "$this->dir/backups", 'uploads_dir' => "$this->dir/uploads"]);
        pb_migrate();
        pb_set_option('site_title', 'Site');
        $this->admin = pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
        $this->editor = pb_create_user('Bia', 'bia@example.com', 'senha-de-teste-123', 'editor');
        pb_seed_demo();
    }

    protected function tearDown(): void
    {
        $_SESSION = $_GET = $_POST = [];
        pb_set_locale('pt-BR');
        pb_rmtree($this->dir);
    }

    private function titles(): array
    {
        return array_values(pb_launch_notices()['pages']);
    }

    private function page(string $slug): array
    {
        return pb_page_by_slug($slug) ?? $this->fail("no page $slug");
    }

    private function dashboard(int $user): string
    {
        $_SESSION['user_id'] = $user;
        $_SERVER['REQUEST_URI'] = '/admin';
        ob_start();
        pb_admin('GET', '/admin');
        return ob_get_clean();
    }

    public function test_a_site_just_installed_has_five_pages_with_example_text(): void
    {
        $this->assertSame(['Início', 'Sobre', 'Serviços', 'Contato', 'Política de privacidade'], $this->titles());
    }

    public function test_nothing_is_said_while_the_site_is_not_open_to_everybody(): void
    {
        pb_set_site_mode('construction', '');
        $this->assertSame(['pages' => [], 'https' => false], pb_launch_notices());
        pb_set_site_mode('live', '');
        $this->assertCount(5, pb_launch_notices()['pages']);
    }

    /** Saves the About page the way the panel's form does: the fields the person wrote, published. */
    private function rewriteAbout(array $fields): void
    {
        $about = $this->page('sobre');
        pb_page_save($about['id'], ['title' => $about['title'], 'status' => 'published', 'f' => $fields + $about['data']], $this->admin);
    }

    public function test_a_page_leaves_the_list_when_its_example_text_is_gone(): void
    {
        $this->rewriteAbout([
            'intro' => 'Somos uma pequena marcenaria de Curitiba, fundada em 1998, que faz móveis sob medida.',
            'body' => '<h2>Nossa história</h2><p>Começamos numa garagem e hoje atendemos todo o estado com móveis feitos à mão.</p>',
        ]);
        $this->assertSame('published', $this->page('sobre')['status']);
        $this->assertNotContains('Sobre', $this->titles());
        $this->assertCount(4, $this->titles());
    }

    public function test_one_sentence_of_the_example_left_on_the_page_is_enough_to_be_listed(): void
    {
        $this->rewriteAbout(['intro' => 'Somos uma pequena marcenaria de Curitiba, fundada em 1998, que faz móveis sob medida.']);
        $this->assertContains('Sobre', $this->titles(), 'the body still says what the installer wrote');
    }

    public function test_the_example_text_is_recognised_in_any_language_of_the_core(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config(['uploads_dir' => "$this->dir/uploads"]);
        pb_migrate();
        pb_set_option('site_title', 'Site');
        pb_set_option('locale', 'en');
        pb_set_locale('en');
        pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
        pb_seed_demo();
        pb_set_locale('pt-BR'); // the panel's language is not the site's
        $this->assertCount(5, pb_launch_notices()['pages']);
    }

    public function test_a_page_nobody_wrote_the_example_for_is_not_listed(): void
    {
        pb_page_create('Preços', 'page', ['intro' => 'Nossos preços mudam com o tamanho da encomenda; peça um orçamento sem compromisso.', 'body' => '<p>Mesas a partir de R$ 900.</p>'], 'published');
        $this->assertNotContains('Preços', $this->titles());
    }

    public function test_a_draft_is_not_on_the_air_so_it_is_not_listed(): void
    {
        pb_page_unpublish($this->page('contato')['id'], $this->admin);
        $this->assertNotContains('Contato', $this->titles());
    }

    public function test_leave_it_as_it_is_is_remembered_for_that_page_only(): void
    {
        pb_launch_acknowledge('page', $this->page('sobre')['id']);
        $this->assertSame(['Início', 'Serviços', 'Contato', 'Política de privacidade'], $this->titles());
        pb_launch_acknowledge('page', $this->page('sobre')['id']);
        $this->assertCount(1, pb_launch_acknowledged()['pages'], 'saying it twice does not pile up');
        pb_launch_acknowledge('page', 0);
        pb_launch_acknowledge('nonsense', 5);
        $this->assertSame([$this->page('sobre')['id']], pb_launch_acknowledged()['pages']);
    }

    public function test_the_https_notice_goes_away_when_the_rules_are_in_or_the_owner_says_it_is_fine(): void
    {
        $this->assertTrue(pb_launch_notices()['https']);
        pb_https_set(true);
        $this->assertFalse(pb_launch_notices()['https']);
        pb_https_set(false);
        $this->assertTrue(pb_launch_notices()['https']);
        pb_launch_acknowledge('https');
        $this->assertFalse(pb_launch_notices()['https']);
    }

    public function test_the_sentences_are_built_once_per_version_and_kept(): void
    {
        $texts = pb_sample_texts();
        $this->assertGreaterThan(30, count($texts), 'three languages of sentences');
        $kept = json_decode(pb_option('sample_texts'), true);
        $this->assertSame(PB_VERSION, $kept['version']);
        pb_set_option('sample_texts', json_encode(['version' => PB_VERSION, 'texts' => ['Só esta frase aqui, bem comprida para valer.']]));
        $this->assertSame(['Só esta frase aqui, bem comprida para valer.'], pb_sample_texts(), 'the kept list is used');
        pb_set_option('sample_texts', json_encode(['version' => '0.0.1', 'texts' => ['velha']]));
        $this->assertGreaterThan(30, count(pb_sample_texts()), 'a list of another version is built again');
        $this->assertSame('pt-BR', pb_locale(), 'and the panel keeps its language');
    }

    public function test_the_bell_tells_administrators_only(): void
    {
        $admin = pb_admin_nav(pb_find_user($this->admin), '/admin')['notices'];
        $this->assertSame(['Há 5 páginas no ar com texto de exemplo.', 'O site ainda abre sem o cadeado (https).'], array_column($admin, 'text'));
        $this->assertSame(['/admin', '/admin/general#https'], array_column($admin, 'url'));
        $this->assertSame([], pb_admin_nav(pb_find_user($this->editor), '/admin')['notices']);

        foreach (['sobre', 'servicos', 'contato', 'politica-de-privacidade'] as $slug) {
            pb_launch_acknowledge('page', $this->page($slug)['id']);
        }
        $this->assertSame('Há 1 página no ar com texto de exemplo.', pb_admin_nav(pb_find_user($this->admin), '/admin')['notices'][0]['text']);
    }

    public function test_the_dashboard_has_the_card_for_administrators_with_the_buttons_that_apply(): void
    {
        $card = $this->dashboard($this->admin);
        $this->assertStringContainsString('Antes de divulgar o site', $card);
        $this->assertStringContainsString('Passar para rascunho', $card);
        $this->assertSame(4, substr_count($card, 'action="/admin/launch/draft"'), 'not for the home page, which has to stay published');
        $this->assertSame(5, substr_count($card, 'name="what" value="page"'));
        $this->assertStringContainsString('Usar só https', $card);
        $this->assertStringContainsString('/admin/general#https', $card);

        $this->assertStringNotContainsString('Antes de divulgar o site', $this->dashboard($this->editor));

        pb_set_site_mode('construction', '');
        $this->assertStringNotContainsString('Antes de divulgar o site', $this->dashboard($this->admin));
    }

    public function test_the_card_goes_when_everything_was_dealt_with(): void
    {
        foreach (pb_launch_notices()['pages'] as $id => $title) {
            pb_launch_acknowledge('page', $id);
        }
        pb_launch_acknowledge('https');
        $this->assertStringNotContainsString('Antes de divulgar o site', $this->dashboard($this->admin));
    }
}
