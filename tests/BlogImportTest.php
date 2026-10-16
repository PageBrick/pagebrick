<?php

use PHPUnit\Framework\TestCase;

/** The blog plugin's "Importar" tab: posts and categories from a JSON file, checked first and written all or nothing. */
final class BlogImportTest extends TestCase
{
    private int $admin;
    private int $editor;

    protected function setUp(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config();
        pb_migrate();
        pb_set_option('site_title', 'Página inicial');
        $this->admin = pb_create_user('Ana Souza', 'ana@example.com', 'senha-de-teste-123', 'admin');
        $this->editor = pb_create_user('Bia', 'bia@example.com', 'senha-de-teste-123', 'editor');
        pb_seed_demo();
        pb_activate_plugin('blog');
        pb_test_new_request();
        pb_load_plugins();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        http_response_code(200);
    }

    private function counts(): array
    {
        return [
            'posts' => (int) pb_db()->query('SELECT COUNT(*) FROM ' . pb_table('blog_posts'))->fetchColumn(),
            'categories' => (int) pb_db()->query('SELECT COUNT(*) FROM ' . pb_table('blog_categories'))->fetchColumn(),
        ];
    }

    private function file(array $posts, array $categories = []): string
    {
        return json_encode(['categories' => $categories, 'posts' => $posts], JSON_UNESCAPED_UNICODE);
    }

    private function post(string $title, array $changes = []): array
    {
        return $changes + ['title' => $title, 'published_on' => '2026-03-10', 'status' => 'published', 'fields' => ['summary' => 'Resumo.', 'body' => '<p>Texto.</p>']];
    }

    /** The blog screen as a panel user sees it: [status, html]. A successful import redirects (and ends the request), so these runs are the ones that stay on the screen. */
    private function screen(string $method, ?int $userId, array $get = [], array $post = []): array
    {
        pb_test_new_request();
        pb_load_plugins();
        $_SESSION = $userId ? ['user_id' => $userId] : [];
        $_GET = $get;
        $_POST = $post;
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = '/admin/p/blog';
        http_response_code(200);
        ob_start();
        pb_admin($method, '/admin/p/blog');
        return [http_response_code(), ob_get_clean()];
    }

    public function test_the_example_file_is_valid_and_imports(): void
    {
        $example = json_encode(pbb_import_example(), JSON_UNESCAPED_UNICODE);
        $result = pbb_import(pbb_import_parse($example));

        $this->assertSame(['created' => 2, 'skipped' => 0, 'categories' => 2], $result);
        $tree = pbb_category_tree();
        $this->assertSame(['dicas', 'receitas'], array_column($tree, 'slug'));
        $this->assertSame(1, $tree[array_key_last($tree)]['depth'], 'the subcategory goes inside its parent');
        $published = pbb_find_published('como-guardar-o-pao');
        $this->assertSame('2026-01-15', $published['published_on']);
        $this->assertSame(['Dicas'], array_column($published['categories'], 'name'));
        $this->assertNull(pbb_find_published('uma-sopa-simples'), 'the draft is not public');
        $this->assertSame(2, $this->counts()['posts']);
    }

    public function test_importing_the_same_file_twice_does_not_duplicate(): void
    {
        $json = $this->file(
            [$this->post('Primeiro texto', ['categories' => ['novidades']]), $this->post('Segundo texto', ['slug' => 'o-segundo'])],
            [['name' => 'Novidades', 'slug' => 'novidades']],
        );
        $this->assertSame(['created' => 2, 'skipped' => 0, 'categories' => 1], pbb_import(pbb_import_parse($json)));
        $before = $this->counts();

        $this->assertSame(['created' => 0, 'skipped' => 2, 'categories' => 0], pbb_import(pbb_import_parse($json)));
        $this->assertSame($before, $this->counts());
        $this->assertSame(2, $before['posts']);
        $this->assertNotNull(pbb_find_published('o-segundo'), 'the address of the file is kept');
    }

    public function test_an_address_already_used_is_left_alone(): void
    {
        pbb_save(pbb_create('Já existe'), ['title' => 'Já existe', 'slug' => '', 'status' => 'published', 'published_on' => '2025-01-01', 'f' => ['summary' => 'Original.']]);
        // "categoria" is the address the blog keeps for its categories (/blog/categoria/...).
        $result = pbb_import(pbb_import_parse($this->file([$this->post('Já existe'), $this->post('Categoria'), $this->post('Novo')])));

        $this->assertSame(1, $result['created']);
        $this->assertSame(2, $result['skipped']);
        $this->assertSame('Original.', pbb_find_published('ja-existe')['data']['summary'], 'what exists is not touched');
        $this->assertNull(pbb_find_published('categoria'));
    }

    public function test_a_problem_in_one_line_saves_nothing(): void
    {
        $json = $this->file(
            [$this->post('Bom texto'), $this->post('Outro bom texto'), $this->post('Data ruim', ['published_on' => '31/01/2026']), $this->post('Status ruim', ['status' => 'publicado']),
                $this->post('Categoria ausente', ['categories' => ['nao-existe']]), ['sem' => 'título']],
            [['name' => 'Nova categoria', 'slug' => 'nova']],
        );
        try {
            pbb_import_parse($json);
            $this->fail('the file has problems');
        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();
        }
        $this->assertStringContainsString('Nada foi importado', $message);
        $this->assertStringContainsString('Texto 3 ("Data ruim")', $message);
        $this->assertStringContainsString('Texto 4 ("Status ruim")', $message);
        $this->assertStringContainsString('Texto 5 ("Categoria ausente")', $message);
        $this->assertStringContainsString('Texto 6', $message);
        $this->assertStringNotContainsString('Bom texto', $message, 'only what is wrong is listed');
        $this->assertSame(['posts' => 0, 'categories' => 0], $this->counts());

        // The same through the screen: the list of what to fix is shown, nothing is saved.
        [$status, $html] = $this->screen('POST', $this->admin, [], ['action' => 'import', 'json' => $json]);
        $this->assertStringContainsString('Texto 3 (&quot;Data ruim&quot;)', $html);
        $this->assertSame(['posts' => 0, 'categories' => 0], $this->counts());
    }

    public function test_a_failure_while_writing_undoes_everything(): void
    {
        // Passes the check but breaks on the second post (an impossible date gets past a hand-made list): the first post and the category must not stay.
        $parsed = [
            'categories' => ['novidades' => ['name' => 'Novidades', 'slug' => 'novidades', 'parent' => '', 'description' => '']],
            'posts' => [
                ['title' => 'Primeiro', 'slug' => 'primeiro', 'published_on' => '2026-03-10', 'status' => 'published', 'categories' => ['novidades'], 'summary' => '', 'body' => ''],
                ['title' => 'Segundo', 'slug' => 'segundo', 'published_on' => '2026-02-31', 'status' => 'published', 'categories' => [], 'summary' => '', 'body' => ''],
            ],
        ];
        try {
            pbb_import($parsed);
            $this->fail('the second post cannot be saved');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('data válida', $e->getMessage());
        }
        $this->assertSame(['posts' => 0, 'categories' => 0], $this->counts(), 'all or nothing');
        $this->assertFalse(pb_db()->inTransaction());
    }

    public function test_invalid_files_are_refused_with_a_clear_message(): void
    {
        foreach (['', 'isto não é JSON', '{', '[]', '{}', '{"posts": "texto"}', '{"posts": {"a": 1}}', '{"categories": [], "posts": 3}'] as $text) {
            try {
                pbb_import_parse($text);
                $this->fail("'$text' is not a valid file");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('Não consegui ler o arquivo', $e->getMessage(), "'$text'");
            }
        }
        $this->assertSame(['posts' => 0, 'categories' => 0], $this->counts());
        // a UTF-8 byte order mark (Windows editors add it) is fine
        $this->assertCount(1, pbb_import_parse("\xEF\xBB\xBF" . $this->file([$this->post('Com BOM')]))['posts']);
    }

    public function test_a_file_over_the_limit_is_refused(): void
    {
        foreach ([str_repeat(' ', PBB_IMPORT_MAX_BYTES + 1), $this->file([$this->post('Grande', ['fields' => ['summary' => '', 'body' => str_repeat('x', PBB_IMPORT_MAX_BYTES)]])])] as $text) {
            try {
                pbb_import_parse($text);
                $this->fail('over the limit');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('passa de 2 MB', $e->getMessage());
            }
        }
        $many = array_map(fn($n) => $this->post("Texto $n"), range(1, PBB_IMPORT_MAX_POSTS + 1));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('textos demais');
        pbb_import_parse($this->file($many));
    }

    public function test_the_upload_limits_are_checked_before_reading(): void
    {
        $_FILES = ['file' => ['error' => UPLOAD_ERR_INI_SIZE, 'tmp_name' => '', 'size' => 0, 'name' => 'a.json']];
        try {
            pbb_import_text();
            $this->fail('the host refused the file');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('limite da hospedagem', $e->getMessage());
        } finally {
            $_FILES = [];
        }
        $_POST = ['json' => '   '];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Escolha o arquivo .json');
        pbb_import_text();
    }

    public function test_only_administrators_import(): void
    {
        $json = $this->file([$this->post('Texto do editor')]);

        // The tab is for administrators: an editor neither sees it nor can post to it.
        [, $list] = $this->screen('GET', $this->editor);
        $this->assertStringNotContainsString('aba=importar', $list);
        [, $page] = $this->screen('GET', $this->editor, ['aba' => 'importar']);
        $this->assertStringContainsString('Só administradores importam textos.', $page);
        $this->assertStringNotContainsString('blog-import-file', $page);
        [, $attempt] = $this->screen('POST', $this->editor, [], ['action' => 'import', 'json' => $json]);
        $this->assertStringContainsString('Só administradores importam textos.', $attempt);
        $this->assertSame(['posts' => 0, 'categories' => 0], $this->counts(), 'nothing was saved');

        [, $adminList] = $this->screen('GET', $this->admin);
        $this->assertStringContainsString('aba=importar', $adminList);
        $this->assertFalse((function () {
            $_SESSION = [];
            return pbb_can_import();
        })(), 'and nobody signed out');
    }

    public function test_the_form_carries_the_csrf_token_the_panel_checks_on_every_post(): void
    {
        [, $page] = $this->screen('GET', $this->admin, ['aba' => 'importar']);
        $this->assertMatchesRegularExpression('/<form method="post"[^>]*enctype="multipart\/form-data">\s*<input type="hidden" name="_csrf" value="[^"]+">/', $page);
        $this->assertStringContainsString('name="file"', $page);
        $this->assertStringContainsString('name="json"', $page);
        $this->assertStringContainsString('aba=importar&amp;exemplo=1', $page, 'the link to the example file');
        // pb_handle_request() turns away any panel POST without this token (core/app.php); the plugin adds nothing around it.
        $this->assertFalse(pb_csrf_valid(null));
        $this->assertFalse(pb_csrf_valid('outro-token'));
    }

    public function test_the_text_goes_through_the_same_cleaning_as_the_editor(): void
    {
        $dirty = '<p onclick="roubar()">Olá <strong>mundo</strong></p><script>alert(1)</script><iframe src="https://example.com"></iframe><a href="javascript:x()">link</a>';
        pbb_import(pbb_import_parse($this->file([$this->post('Texto sujo', ['fields' => ['summary' => '<b>Resumo</b>', 'body' => $dirty]])])));

        $saved = pbb_find_published('texto-sujo');
        $this->assertSame(pb_sanitize_html($dirty), $saved['data']['body'], 'exactly what the editor would keep');
        $this->assertStringNotContainsString('<script', $saved['data']['body']);
        $this->assertStringNotContainsString('onclick', $saved['data']['body']);
        $this->assertStringNotContainsString('<iframe', $saved['data']['body']);
        $this->assertStringContainsString('<strong>mundo</strong>', $saved['data']['body']);
        $this->assertStringContainsString('&lt;b&gt;Resumo&lt;/b&gt;', (string) pbb_values($saved)->summary, 'the summary is plain text: printed, it is escaped');
    }

    public function test_a_post_without_status_comes_in_as_a_draft_and_a_blank_address_comes_from_the_title(): void
    {
        $post = $this->post('Título com Acentuação', ['slug' => '']);
        unset($post['status']);
        pbb_import(pbb_import_parse($this->file([$post])));

        $row = pb_db()->query('SELECT slug, status FROM ' . pb_table('blog_posts'))->fetch();
        $this->assertSame(['slug' => 'titulo-com-acentuacao', 'status' => 'draft'], $row);
    }

    public function test_categories_may_point_to_ones_already_in_the_blog_and_parents_to_ones_in_the_file(): void
    {
        $existing = pbb_save_category(0, 'Dicas', 'dicas', 0, '');
        $json = $this->file(
            [$this->post('Dica um', ['categories' => ['dicas', 'receitas', 'receitas']])],
            [['name' => 'Receitas', 'slug' => 'receitas', 'parent' => 'dicas'], ['name' => 'Sopas', 'slug' => 'sopas', 'parent' => 'receitas']],
        );
        $this->assertSame(['created' => 1, 'skipped' => 0, 'categories' => 2], pbb_import(pbb_import_parse($json)));

        $tree = pbb_category_tree();
        $bySlug = array_column($tree, null, 'slug');
        $this->assertSame($existing, $bySlug['receitas']['parent_id']);
        $this->assertSame($bySlug['receitas']['id'], $bySlug['sopas']['parent_id']);
        $this->assertSame(['Dicas', 'Receitas'], array_column(pbb_find_published('dica-um')['categories'], 'name'), 'a repeated category counts once');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('a categoria-mãe "fantasma" não existe');
        pbb_import_parse($this->file([], [['name' => 'Órfã', 'slug' => 'orfa', 'parent' => 'fantasma']]));
    }
}
