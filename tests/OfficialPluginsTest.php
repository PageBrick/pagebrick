<?php

use PHPUnit\Framework\TestCase;

/** The official plugins shipped in content/plugins: contact form and blog. */
final class OfficialPluginsTest extends TestCase
{
    protected function setUp(): void
    {
        pb_test_fresh_db();
        $GLOBALS['pb_config'] = pb_test_config();
        pb_migrate();
        pb_set_option('site_title', 'Padaria Exemplo');
        pb_create_user('Ana', 'ana@example.com', 'senha-de-teste-123', 'admin');
        pb_seed_demo();
        pb_activate_plugin('contact-form');
        pb_activate_plugin('blog');
        pb_test_new_request();
        pb_load_plugins();
    }

    private function visit(string $path): string
    {
        unset($GLOBALS['pbcf_used']); // per-request flag; each visit is a new request
        $_SERVER['REQUEST_URI'] = $path;
        ob_start();
        pb_public('GET', $path);
        return ob_get_clean();
    }

    private function message(array $changes = []): array
    {
        return $changes + ['name' => 'Bia Souza', 'email' => 'bia@example.com', 'phone' => '(19) 99999-8888', 'message' => 'Quero um orçamento para 50 pães.'];
    }

    private function token(int $age): array
    {
        $time = (string) (time() - $age);
        return [$time, pb_sign("contact-form|$time")];
    }

    // ---------------------------------------------------------------- contact form

    public function test_contact_page_shows_the_form_and_its_stylesheet(): void
    {
        $html = $this->visit('/contato');
        $this->assertStringContainsString('id="contato-form"', $html);
        $this->assertStringContainsString('contact-form/style.css', $html);
        $this->assertStringContainsString('/politica-de-privacidade', $html, 'links the privacy policy');
        $this->assertStringNotContainsString('contact-form/style.css', $this->visit('/sobre'), 'stylesheet only where the form is');
    }

    public function test_a_valid_message_is_stored_and_emailed_to_the_admin(): void
    {
        [$time, $signature] = $this->token(10);
        $this->assertSame([], pbcf_validate($this->message(), $time, $signature, '10.0.0.1'));

        $id = pbcf_store_and_send($this->message(), null, '10.0.0.1');
        $row = pb_db()->query('SELECT * FROM ' . pb_table('contact_messages') . " WHERE id = $id")->fetch();
        $this->assertSame('Quero um orçamento para 50 pães.', $row['message']);
        $this->assertNull($row['mail_error']);

        $mail = $GLOBALS['pb_sent_mail'][0];
        $this->assertSame('ana@example.com', $mail['to']);
        $this->assertSame('bia@example.com', $mail['replyTo'], 'answering the e-mail goes to the visitor');
        $this->assertStringContainsString('Bia Souza', $mail['subject']);
    }

    public function test_spam_and_bad_submissions_are_refused(): void
    {
        $ok = $this->token(10);
        $cases = [
            'sent too fast' => [$this->message(), $this->token(1), 'rápido demais'],
            'older than a day' => [$this->message(), $this->token(90000), 'expirou'],
            'forged token' => [$this->message(), [$ok[0], 'assinatura-falsa'], 'expirou'],
            'no name' => [$this->message(['name' => '']), $ok, 'seu nome'],
            'line break in name' => [$this->message(['name' => "Bia\nBcc: alvo@example.com"]), $ok, 'seu nome'],
            'bad e-mail' => [$this->message(['email' => 'bia@']), $ok, 'e-mail válido'],
            'empty message' => [$this->message(['message' => 'oi']), $ok, 'mensagem'],
        ];
        foreach ($cases as $case => [$message, [$time, $signature], $expected]) {
            $errors = pbcf_validate($message, $time, $signature, '10.0.0.1');
            $this->assertStringContainsString($expected, implode(' ', $errors), $case);
        }
    }

    public function test_too_many_messages_from_one_address_are_refused(): void
    {
        for ($i = 0; $i < PBCF_MAX_PER_HOUR; $i++) {
            pbcf_store_and_send($this->message(), null, '10.0.0.7');
        }
        [$time, $signature] = $this->token(10);
        $this->assertStringContainsString('muitas mensagens', implode(' ', pbcf_validate($this->message(), $time, $signature, '10.0.0.7')));
        $this->assertSame([], pbcf_validate($this->message(), $time, $signature, '10.0.0.8'), 'other addresses are fine');
    }

    public function test_a_failing_email_never_loses_the_message(): void
    {
        $GLOBALS['pb_config']['mail'] = 'smtp';
        pb_save_mail_settings(['host' => '127.0.0.1', 'port' => '1', 'secure' => 'none']);

        $id = pbcf_store_and_send($this->message(), null, '10.0.0.1');
        $row = pb_db()->query('SELECT * FROM ' . pb_table('contact_messages') . " WHERE id = $id")->fetch();
        $this->assertSame('Bia Souza', $row['name']);
        $this->assertStringContainsString('não foi enviado', $row['mail_error']);
    }

    public function test_old_messages_are_deleted_as_the_privacy_policy_promises(): void
    {
        pb_db()->exec('INSERT INTO ' . pb_table('contact_messages') . " (name, email, message, ip, created_at)
            VALUES ('Antigo', 'a@example.com', 'mensagem antiga', '1.1.1.1', NOW() - INTERVAL 13 MONTH)");
        pbcf_store_and_send($this->message(), null, '10.0.0.1');
        $names = pb_db()->query('SELECT name FROM ' . pb_table('contact_messages'))->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['Bia Souza'], $names);
    }

    // ---------------------------------------------------------------- blog

    private function publish(string $title, array $changes = []): int
    {
        $id = pbb_create($title);
        pbb_save($id, $changes + ['title' => $title, 'slug' => '', 'status' => 'published', 'published_on' => date('Y-m-d'), 'f' => []]);
        return $id;
    }

    // ---------------------------------------------------------------- the theme owns the front end

    public function test_the_theme_can_replace_any_template_and_stylesheet_of_a_plugin(): void
    {
        $dir = sys_get_temp_dir() . '/pb-own-' . bin2hex(random_bytes(4));
        pb_test_copy_dir(PB_ROOT . '/content/themes/default', "$dir/default");
        pb_test_copy_dir(PB_ROOT . '/content/themes/default', "$dir/own");
        $copy = fn(string $file, string $code) => (is_dir(dirname("$dir/own/plugins/$file")) || mkdir(dirname("$dir/own/plugins/$file"), 0777, true)) && file_put_contents("$dir/own/plugins/$file", $code);
        // Copies only list.php: the cards it includes still come from the plugin.
        $copy('blog/templates/list.php', '<div id="MEU-BLOG"><?= pb_include(__DIR__ . "/cards.php", ["posts" => $posts]) ?></div>');
        $copy('contact-form/form.php', '<?php if (!empty($GLOBALS["break_form"])) throw new RuntimeException("erro no tema"); ?><form id="MEU-FORM"></form>');
        $copy('contact-form/style.css', '/* meu */');
        $GLOBALS['pb_config']['themes_dir'] = $dir;
        try {
            pb_activate_theme('own');
            $this->publish('Nova fornada de panetone');

            pb_test_new_request();
            pb_load_plugins();
            $list = $this->visit('/blog');
            $this->assertStringContainsString('<div id="MEU-BLOG">', $list);
            $this->assertStringContainsString('Nova fornada de panetone', $list);

            $contact = $this->visit('/contato');
            $this->assertStringContainsString('<form id="MEU-FORM">', $contact);
            $this->assertStringNotContainsString('id="contato-form"', $contact);
            $this->assertMatchesRegularExpression('#own/plugins/contact-form/style\.css\?v=\d+#', $contact);

            $original = pb_plugins_dir() . '/blog/admin-list.php';
            unset($GLOBALS['pb_public_request']);
            $this->assertSame($original, pb_template_file($original), 'the panel never uses theme copies');

            // A broken copy is the theme's fault: the page falls back to the default theme and the plugin stays on.
            $GLOBALS['break_form'] = true;
            $contact = $this->visit('/contato');
            $this->assertStringContainsString('id="contato-form"', $contact);
            $this->assertStringContainsString('erro no tema', pb_theme_states()['own']['error']);
            $this->assertTrue(pb_plugin_states()['contact-form']['active']);
        } finally {
            unset($GLOBALS['break_form']);
            pb_rmtree($dir);
        }
    }

    public function test_blog_publishing_flow(): void
    {
        $this->publish('Nova fornada de panetone', ['f' => ['summary' => 'Chegou a época.', 'body' => '<p>Encomende já.<script>x()</script></p>']]);
        $draft = pbb_create('Rascunho secreto');
        $this->publish('Texto do futuro', ['published_on' => date('Y-m-d', strtotime('+3 days'))]);

        $list = $this->visit('/blog');
        $this->assertStringContainsString('Nova fornada de panetone', $list);
        $this->assertStringNotContainsString('Rascunho secreto', $list);
        $this->assertStringNotContainsString('Texto do futuro', $list, 'scheduled posts wait for their date');

        $post = $this->visit('/blog/nova-fornada-de-panetone');
        $this->assertStringContainsString('<p>Encomende já.</p>', $post);
        $this->assertStringContainsString('<link rel="canonical" href="' . pb_absolute_url('/blog/nova-fornada-de-panetone') . '">', $post);
        $this->assertStringContainsString('Página não encontrada', $this->visit('/blog/texto-do-futuro'));
        $this->assertStringContainsString('Página não encontrada', $this->visit('/blog/rascunho-secreto'));

        $this->assertStringContainsString('Nova fornada de panetone', $this->visit('/'), 'latest posts on the home page');
        $this->assertStringContainsString('/blog/nova-fornada-de-panetone', pb_sitemap_xml());
        $this->assertNotNull(pbb_find($draft));
    }

    public function test_categories_and_subcategories(): void
    {
        $tips = pbb_save_category(0, 'Dicas', '', 0, 'Dicas para o dia a dia.');
        $recipes = pbb_save_category(0, 'Receitas', '', $tips, '');
        $news = pbb_save_category(0, 'Novidades', '', 0, '');
        $this->publish('Como guardar o pão', ['categories' => [$tips]]);
        $rabanada = $this->publish('Receita de rabanada', ['categories' => [$recipes, $news, 9999]]);
        $this->publish('Abrimos aos domingos', ['categories' => [$news]]);

        $tree = pbb_category_tree();
        $this->assertSame(['Dicas', 'Receitas', 'Novidades'], array_column($tree, 'name'), 'children right after their parent');
        $this->assertSame(1, $tree[$recipes]['depth']);
        $this->assertSame(['Receitas', 'Novidades'], array_column(pbb_find($rabanada)['categories'], 'name'), 'unknown category ids are ignored');

        $tipsPage = $this->visit('/blog/categoria/dicas');
        $this->assertStringContainsString('Como guardar o pão', $tipsPage);
        $this->assertStringContainsString('Receita de rabanada', $tipsPage, 'a category shows the posts of its subcategories');
        $this->assertStringNotContainsString('Abrimos aos domingos', $tipsPage);
        $this->assertStringContainsString('/blog/categoria/receitas', $tipsPage, 'links to its subcategories');

        $recipesPage = $this->visit('/blog/categoria/receitas');
        $this->assertStringContainsString('Receita de rabanada', $recipesPage);
        $this->assertStringNotContainsString('Como guardar o pão', $recipesPage);
        $this->assertStringContainsString('href="/blog/categoria/dicas">Dicas</a>', $recipesPage, 'the trail back to the parent');
        $this->assertStringContainsString('/blog/categoria/receitas', pb_sitemap_xml());

        // Deleting a parent moves its children up instead of losing them.
        pbb_delete_category($tips);
        $this->assertNull(pbb_category_tree()[$recipes]['parent_id']);
    }

    public function test_category_rules(): void
    {
        $tips = pbb_save_category(0, 'Dicas', '', 0, '');
        $recipes = pbb_save_category(0, 'Receitas', '', $tips, '');
        try {
            pbb_save_category($tips, 'Dicas', 'dicas', $recipes, '');
            $this->fail('A category was put inside its own child');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('dentro dela mesma', $e->getMessage());
        }
        $this->expectExceptionMessage('já é usado');
        pbb_save_category(0, 'Dicas', '', 0, '');
    }

    public function test_post_rules(): void
    {
        $id = pbb_create('Primeira');
        pbb_create('Segunda');
        $this->assertSame('primeira-2', pbb_find(pbb_create('Primeira'))['slug']);
        $this->assertSame('categoria-2', pbb_find(pbb_create('Categoria'))['slug'], '"categoria" belongs to the category pages');

        $this->expectExceptionMessage('já é usado');
        pbb_save($id, ['title' => 'Primeira', 'slug' => 'segunda', 'status' => 'draft', 'published_on' => date('Y-m-d')]);
    }

    public function test_name_and_address_are_configurable_and_offered_in_menus(): void
    {
        pb_save_plugin_settings('blog', ['title' => 'Notícias', 'path' => 'Notícias da Padaria']);
        pbb_save_category(0, 'Eventos', '', 0, '');
        pb_test_new_request();
        pb_load_plugins();
        $this->publish('Feira de domingo');

        $this->assertStringContainsString('Feira de domingo', $this->visit('/noticias-da-padaria'));
        $this->assertStringContainsString('Página não encontrada', $this->visit('/blog'));
        $this->assertSame('Notícias', $GLOBALS['pb_admin_pages']['blog']['label'], 'the panel menu uses the name too');

        $targets = pb_apply_filters('link_targets', []);
        $this->assertSame('Notícias', $targets['/noticias-da-padaria']);
        $this->assertArrayHasKey('/noticias-da-padaria/categoria/eventos', $targets);

        pb_save_menu('main', [['label' => '', 'link' => ['page' => '/noticias-da-padaria', 'url' => '']]]);
        $this->assertSame('/noticias-da-padaria', pb_menu_items_raw('main')[0]['link']);
    }

    public function test_the_panel_and_api_addresses_can_never_be_taken(): void
    {
        pb_save_plugin_settings('blog', ['path' => 'admin']);
        $this->assertSame('blog', pbb_base());
        pb_save_plugin_settings('blog', ['path' => 'api']);
        $this->assertSame('blog', pbb_base());
    }
}
