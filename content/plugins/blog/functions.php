<?php
// Blog: posts with dates, organized in categories that can have subcategories.
// Public addresses, under a base the owner chooses ("blog" by default):
//   /blog                      latest posts            (?pagina=2 ...)
//   /blog/categoria/{slug}     posts of a category and of its subcategories
//   /blog/{slug}               one post
// Templates in templates/ render inside the active theme's layout, so the blog keeps the site's look.

const PBB_PER_PAGE = 10;
/** First part of category addresses: /blog/categoria/x (/blog/category/x in English). */
function pbb_category_prefix(): string
{
    return pb_slugify(__('categoria')) ?: 'categoria';
}

function pbb_settings(): PbGroup
{
    return pb_plugin_settings_values('blog');
}

function pbb_title(): string
{
    return pbb_settings()->title->raw() ?: 'Blog';
}

/** The blog's address ("blog", "noticias"...). The panel's own address is never accepted. */
function pbb_base(): string
{
    $base = pb_slugify((string) pbb_settings()->path->raw());
    return in_array($base, ['', 'admin', 'api'], true) ? 'blog' : $base; // never the panel's or the content API's address
}

function pbb_url(string $path = ''): string
{
    return pb_url('/' . pbb_base() . ($path === '' ? '' : "/$path"));
}

/** Fields of a post, edited with the same field system as pages. */
function pbb_fields(): array
{
    return [
        'summary' => ['type' => 'textarea', 'label' => __('Resumo'), 'help' => __('Uma ou duas frases. Aparece na lista de textos e no Google.')],
        'image' => ['type' => 'image', 'label' => __('Foto')],
        'body' => ['type' => 'richtext', 'label' => __('Texto')],
    ];
}

// ------------------------------------------------------------------ categories

/** All categories, as a tree flattened in display order: each with 'depth' and 'path' (ids from the root). */
// ponytail: one query per call and a few calls per page; cache it if a blog ever has hundreds of categories.
function pbb_category_tree(): array
{
    $rows = pb_db()->query('SELECT * FROM ' . pb_table('blog_categories') . ' ORDER BY name')->fetchAll();
    $byParent = [];
    foreach ($rows as $row) {
        $row['id'] = (int) $row['id'];
        $row['parent_id'] = $row['parent_id'] === null ? null : (int) $row['parent_id'];
        $byParent[$row['parent_id'] ?? 0][] = $row;
    }
    $tree = [];
    $walk = function (int $parent, int $depth, array $path) use (&$walk, &$tree, $byParent) {
        foreach ($byParent[$parent] ?? [] as $row) {
            if (in_array($row['id'], $path, true)) {
                continue; // a loop in the data would never end
            }
            $tree[$row['id']] = $row + ['depth' => $depth, 'path' => [...$path, $row['id']]];
            $walk($row['id'], $depth + 1, [...$path, $row['id']]);
        }
    };
    $walk(0, 0, []);
    return $tree;
}

/** The category and every category below it. */
function pbb_category_and_descendants(int $id): array
{
    return array_keys(array_filter(pbb_category_tree(), fn($c) => in_array($id, $c['path'], true)));
}

function pbb_category_by_slug(string $slug): ?array
{
    foreach (pbb_category_tree() as $category) {
        if ($category['slug'] === $slug) {
            return $category;
        }
    }
    return null;
}

/** Creates (id 0) or changes a category. Returns its id. Throws with a message the user can read. */
function pbb_save_category(int $id, string $name, string $slug, int $parentId, string $description): int
{
    $name = trim($name);
    if (!preg_match('/^[^\x00-\x1F\x7F]{1,100}$/u', $name)) {
        throw new InvalidArgumentException(__('Informe o nome da categoria (até 100 caracteres).'));
    }
    $slug = pb_slugify($slug !== '' ? $slug : $name) ?: 'categoria';
    $tree = pbb_category_tree();
    foreach ($tree as $other) {
        if ($other['slug'] === $slug && $other['id'] !== $id) {
            throw new InvalidArgumentException(sprintf(__('O endereço "%s" já é usado por outra categoria.'), $slug));
        }
    }
    if ($parentId !== 0 && (!isset($tree[$parentId]) || ($id !== 0 && in_array($id, $tree[$parentId]['path'], true)))) {
        throw new InvalidArgumentException(__('Escolha outra categoria-mãe: uma categoria não pode ficar dentro dela mesma.'));
    }
    $values = [$name, $slug, $parentId ?: null, pb_limit(trim($description), 500)];
    $table = pb_table('blog_categories');
    if ($id === 0) {
        pb_db()->prepare("INSERT INTO $table (name, slug, parent_id, description) VALUES (?, ?, ?, ?)")->execute($values);
        return (int) pb_db()->lastInsertId();
    }
    pb_db()->prepare("UPDATE $table SET name = ?, slug = ?, parent_id = ?, description = ? WHERE id = ?")->execute([...$values, $id]);
    return $id;
}

/** Removes a category; its subcategories move up one level and its posts simply lose it. */
function pbb_delete_category(int $id): void
{
    $category = pbb_category_tree()[$id] ?? throw new InvalidArgumentException(__('Categoria não encontrada.'));
    pb_db()->prepare('UPDATE ' . pb_table('blog_categories') . ' SET parent_id = ? WHERE parent_id = ?')->execute([$category['parent_id'], $id]);
    pb_db()->prepare('DELETE FROM ' . pb_table('blog_post_categories') . ' WHERE category_id = ?')->execute([$id]);
    pb_db()->prepare('DELETE FROM ' . pb_table('blog_categories') . ' WHERE id = ?')->execute([$id]);
}

/** Categories of each post: post id => [category rows]. */
function pbb_categories_of(array $postIds): array
{
    if (!$postIds) {
        return [];
    }
    $tree = pbb_category_tree();
    $rows = pb_db()->query('SELECT post_id, category_id FROM ' . pb_table('blog_post_categories')
        . ' WHERE post_id IN (' . implode(',', array_map('intval', $postIds)) . ')')->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        if (isset($tree[(int) $row['category_id']])) {
            $result[(int) $row['post_id']][] = $tree[(int) $row['category_id']];
        }
    }
    return $result;
}

// ------------------------------------------------------------------ posts

function pbb_decode(array|false $row): ?array
{
    if (!$row) {
        return null;
    }
    $row['id'] = (int) $row['id'];
    $row['data'] = json_decode($row['data'], true) ?: [];
    return $row;
}

function pbb_post_url(array $post): string
{
    return pbb_url($post['slug']);
}

function pbb_values(array $post): PbGroup
{
    return new PbGroup(pbb_fields(), $post['data']);
}

/** SQL condition for "published and its date has arrived", optionally limited to some categories. */
function pbb_published_where(array $categoryIds = []): string
{
    $where = "p.status = 'published' AND p.published_on <= CURDATE()";
    if ($categoryIds) {
        $where .= ' AND p.id IN (SELECT post_id FROM ' . pb_table('blog_post_categories')
            . ' WHERE category_id IN (' . implode(',', array_map('intval', $categoryIds)) . '))';
    }
    return $where;
}

/** Published posts, newest first, with their 'categories'. */
function pbb_published(int $limit, int $offset = 0, array $categoryIds = []): array
{
    $posts = array_map('pbb_decode', pb_db()->query('SELECT p.* FROM ' . pb_table('blog_posts') . ' p WHERE '
        . pbb_published_where($categoryIds) . " ORDER BY p.published_on DESC, p.id DESC LIMIT $limit OFFSET $offset")->fetchAll());
    $categories = pbb_categories_of(array_column($posts, 'id'));
    return array_map(fn($post) => $post + ['categories' => $categories[$post['id']] ?? []], $posts);
}

function pbb_published_count(array $categoryIds = []): int
{
    return (int) pb_db()->query('SELECT COUNT(*) FROM ' . pb_table('blog_posts') . ' p WHERE ' . pbb_published_where($categoryIds))->fetchColumn();
}

function pbb_find(int $id): ?array
{
    $st = pb_db()->prepare('SELECT * FROM ' . pb_table('blog_posts') . ' WHERE id = ?');
    $st->execute([$id]);
    $post = pbb_decode($st->fetch());
    return $post ? $post + ['categories' => pbb_categories_of([$post['id']])[$post['id']] ?? []] : null;
}

function pbb_find_published(string $slug): ?array
{
    $st = pb_db()->prepare('SELECT p.* FROM ' . pb_table('blog_posts') . ' p WHERE p.slug = ? AND ' . pbb_published_where());
    $st->execute([$slug]);
    $post = pbb_decode($st->fetch());
    return $post ? $post + ['categories' => pbb_categories_of([$post['id']])[$post['id']] ?? []] : null;
}

function pbb_slug_taken(string $slug, int $exceptId = 0): bool
{
    if ($slug === pbb_category_prefix()) {
        return true; // /blog/categoria/... belongs to categories
    }
    $st = pb_db()->prepare('SELECT COUNT(*) FROM ' . pb_table('blog_posts') . ' WHERE slug = ? AND id <> ?');
    $st->execute([$slug, $exceptId]);
    return (int) $st->fetchColumn() > 0;
}

/** Creates a draft dated today. Returns its id. */
function pbb_create(string $title): int
{
    $title = pb_validate_page_title($title);
    $base = pb_slugify($title) ?: 'texto';
    $slug = $base;
    for ($n = 2; pbb_slug_taken($slug); $n++) {
        $slug = "$base-$n";
    }
    pb_db()->prepare('INSERT INTO ' . pb_table('blog_posts') . ' (title, slug, status, published_on, data) VALUES (?, ?, ?, CURDATE(), ?)')
        ->execute([$title, $slug, 'draft', '{}']);
    return (int) pb_db()->lastInsertId();
}

/** Saves the edit form (title, slug, status, published_on, f, categories[]). Throws with a message the user can read. */
function pbb_save(int $id, array $in): void
{
    $post = pbb_find($id) ?? throw new InvalidArgumentException(__('Texto não encontrado.'));
    $title = pb_validate_page_title(is_string($in['title'] ?? null) ? $in['title'] : '');
    $slug = pb_slugify(is_string($in['slug'] ?? null) ? $in['slug'] : '') ?: (pb_slugify($title) ?: 'texto');
    if (pbb_slug_taken($slug, $id)) {
        throw new InvalidArgumentException(sprintf(__('O endereço "%s" já é usado (por outro texto ou pelas categorias).'), $slug));
    }
    $date = is_string($in['published_on'] ?? null) ? $in['published_on'] : '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
        throw new InvalidArgumentException(__('Informe uma data válida.'));
    }
    $status = ($in['status'] ?? '') === 'published' ? 'published' : 'draft';
    $data = pb_collect_fields(pbb_fields(), $in['f'] ?? []) + $post['data'];
    $tree = pbb_category_tree();
    $categories = array_values(array_unique(array_filter(array_map('intval', is_array($in['categories'] ?? null) ? $in['categories'] : []), fn($c) => isset($tree[$c]))));

    pb_db()->prepare('UPDATE ' . pb_table('blog_posts') . ' SET title = ?, slug = ?, status = ?, published_on = ?, data = ? WHERE id = ?')
        ->execute([$title, $slug, $status, $date, json_encode($data, JSON_UNESCAPED_UNICODE), $id]);
    $pivot = pb_table('blog_post_categories');
    pb_db()->prepare("DELETE FROM $pivot WHERE post_id = ?")->execute([$id]);
    foreach ($categories as $categoryId) {
        pb_db()->prepare("INSERT INTO $pivot (post_id, category_id) VALUES (?, ?)")->execute([$id, $categoryId]);
    }
}

function pbb_delete(int $id): void
{
    pb_db()->prepare('DELETE FROM ' . pb_table('blog_post_categories') . ' WHERE post_id = ?')->execute([$id]);
    pb_db()->prepare('DELETE FROM ' . pb_table('blog_posts') . ' WHERE id = ?')->execute([$id]);
}

// ------------------------------------------------------------------ public pages

/** GET /blog */
function pbb_list_page(): void
{
    $settings = pbb_settings();
    echo pbb_render_list(pbb_title(), $settings->intro->raw(), [], '/' . pbb_base(), null);
}

/** GET /blog/{rest}: a category or a post. */
function pbb_route(string $rest): void
{
    if (preg_match('~^' . pbb_category_prefix() . '/([a-z0-9-]+)$~', $rest, $m) && ($category = pbb_category_by_slug($m[1]))) {
        echo pbb_render_list($category['name'], $category['description'], pbb_category_and_descendants($category['id']),
            '/' . pbb_base() . '/' . pbb_category_prefix() . '/' . $category['slug'], $category);
        return;
    }
    $post = preg_match('~^[a-z0-9-]+$~', $rest) ? pbb_find_published($rest) : null;
    if (!$post) {
        http_response_code(404);
        echo pb_render_not_found();
        return;
    }
    echo pb_render_in_theme(__DIR__ . '/templates/single.php', ['post' => $post, 'fields' => pbb_values($post)], [
        'title' => $post['title'],
        'description' => $post['data']['summary'] ?? '',
        'path' => '/' . pbb_base() . '/' . $post['slug'],
    ]);
}

/** A paginated list: the whole blog, or one category (with its subcategories). */
function pbb_render_list(string $title, string $intro, array $categoryIds, string $path, ?array $category): string
{
    $total = pbb_published_count($categoryIds);
    $pages = max(1, (int) ceil($total / PBB_PER_PAGE));
    $current = min($pages, max(1, (int) pb_query('pagina')));
    $tree = pbb_category_tree();
    return pb_render_in_theme(__DIR__ . '/templates/list.php', [
        'posts' => pbb_published(PBB_PER_PAGE, ($current - 1) * PBB_PER_PAGE, $categoryIds),
        'intro' => $intro,
        'category' => $category,
        'parents' => $category ? array_map(fn($id) => $tree[$id], array_slice($category['path'], 0, -1)) : [],
        'children' => array_filter($tree, fn($c) => $c['parent_id'] === ($category['id'] ?? null) && pbb_published_count(pbb_category_and_descendants($c['id'])) > 0),
        'current' => $current,
        'pages' => $pages,
        'path' => $path,
    ], ['title' => $title, 'description' => $intro, 'path' => $path . ($current > 1 ? "?pagina=$current" : '')]);
}

// ------------------------------------------------------------------ content API (front ends built outside PageBrick)

/** GET /api/v1/blog?page=2&category=tips: published posts, newest first, plus every category. */
function pbb_api_list(): void
{
    $slug = pb_query('category');
    $category = $slug !== '' ? pbb_category_by_slug($slug) : null;
    if ($slug !== '' && !$category) {
        pb_content_send(null);
        return;
    }
    $ids = $category ? pbb_category_and_descendants($category['id']) : [];
    $total = pbb_published_count($ids);
    $page = max(1, (int) pb_query('page'));
    $tree = pbb_category_tree();
    pb_content_send([
        'title' => pbb_title(),
        'intro' => (string) pbb_settings()->intro->raw(),
        'path' => '/' . pbb_base(),
        'page' => $page,
        'pages' => max(1, (int) ceil($total / PBB_PER_PAGE)),
        'total' => $total,
        'posts' => array_map(fn($post) => pbb_api_post_data($post, false), pbb_published(PBB_PER_PAGE, ($page - 1) * PBB_PER_PAGE, $ids)),
        'categories' => array_values(array_map(fn($c) => [
            'name' => $c['name'], 'slug' => $c['slug'], 'description' => $c['description'],
            'parent' => $c['parent_id'] === null ? null : ($tree[$c['parent_id']]['slug'] ?? null),
            'path' => '/' . pbb_base() . '/' . pbb_category_prefix() . '/' . $c['slug'],
        ], $tree)),
    ]);
}

/** GET /api/v1/blog/{slug}: one published post with its text. */
function pbb_api_post(string $slug): void
{
    $post = preg_match('~^[a-z0-9-]+$~', $slug) ? pbb_find_published($slug) : null;
    pb_content_send($post ? pbb_api_post_data($post, true) : null);
}

function pbb_api_post_data(array $post, bool $withBody): array
{
    $fields = pb_content_json(pbb_fields(), $post['data']);
    if (!$withBody) {
        unset($fields['body']); // the list stays light; the post's own address has the text
    }
    return [
        'id' => $post['id'],
        'title' => $post['title'],
        'slug' => $post['slug'],
        'path' => '/' . pbb_base() . '/' . $post['slug'],
        'published_on' => $post['published_on'],
        'categories' => array_map(fn($c) => ['name' => $c['name'], 'slug' => $c['slug']], $post['categories']),
        'fields' => $fields,
    ];
}

function pbb_home_section(): string
{
    $count = (int) (pbb_settings()->home->raw() ?: 3);
    $posts = $count > 0 ? pbb_published($count) : [];
    return $posts ? pb_include(__DIR__ . '/templates/home.php', ['posts' => $posts, 'title' => pbb_title()]) : '';
}

function pbb_sitemap_urls(): array
{
    $base = '/' . pbb_base();
    $rows = pb_db()->query('SELECT p.slug, p.updated_at FROM ' . pb_table('blog_posts') . ' p WHERE ' . pbb_published_where())->fetchAll();
    if (!$rows) {
        return [];
    }
    $urls = [[$base, date('Y-m-d')]];
    foreach (pbb_category_tree() as $category) {
        if (pbb_published_count(pbb_category_and_descendants($category['id'])) > 0) {
            $urls[] = ["$base/" . pbb_category_prefix() . "/{$category['slug']}", date('Y-m-d')];
        }
    }
    return array_merge($urls, array_map(fn($r) => ["$base/{$r['slug']}", $r['updated_at']], $rows));
}

/** Entries for the "Leva para" list of menus and buttons. */
function pbb_link_targets(): array
{
    $targets = [pbb_url() => pbb_title()];
    foreach (pbb_category_tree() as $category) {
        $targets[pbb_url(pbb_category_prefix() . '/' . $category['slug'])] = pbb_title() . ' › ' . str_repeat('— ', $category['depth']) . $category['name'];
    }
    return $targets;
}

function pbb_stylesheet(): string
{
    return '<link rel="stylesheet" href="' . e(pb_plugin_url('blog', 'style.css')) . "\">\n";
}

// ------------------------------------------------------------------ panel

/** The blog screen: posts and categories. */
function pbb_admin(): void
{
    $self = '/admin/p/blog';
    $post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    $action = pb_post('action');
    try {
        if ($post) {
            switch ($action) {
                case 'create':
                    pb_redirect("$self?editar=" . pbb_create(pb_post('title')));
                case 'save':
                    pbb_save((int) pb_post('id'), $_POST);
                    pb_flash('ok', __('Texto salvo.'));
                    pb_redirect("$self?editar=" . (int) pb_post('id'));
                case 'delete':
                    pbb_delete((int) pb_post('id'));
                    pb_flash('ok', __('Texto excluído.'));
                    pb_redirect($self);
                case 'save-category':
                    pbb_save_category((int) pb_post('id'), pb_post('name'), pb_post('slug'), (int) pb_post('parent_id'), pb_post('description'));
                    pb_flash('ok', __('Categoria salva.'));
                    pb_redirect("$self?aba=categorias");
                case 'delete-category':
                    pbb_delete_category((int) pb_post('id'));
                    pb_flash('ok', __('Categoria excluída. Os textos dela continuam publicados.'));
                    pb_redirect("$self?aba=categorias");
            }
        }
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
        if ($action === 'save' && ($editing = pbb_find((int) pb_post('id')))) {
            // Show what was typed, so nothing is lost.
            $editing = array_merge($editing, ['title' => pb_post('title'), 'slug' => pb_post('slug'), 'status' => pb_post('status'),
                'published_on' => pb_post('published_on'), 'data' => pb_collect_fields(pbb_fields(), $_POST['f'] ?? []),
                'categories' => array_values(array_intersect_key(pbb_category_tree(), array_flip(array_map('intval', (array) ($_POST['categories'] ?? [])))))]);
            echo pb_include(__DIR__ . '/admin-edit.php', ['post' => $editing, 'tree' => pbb_category_tree(), 'error' => $error]);
            return;
        }
    }

    $tree = pbb_category_tree();
    if (pb_query('aba') === 'categorias') {
        $editingCategory = $tree[(int) pb_query('editar')] ?? null;
        echo pb_include(__DIR__ . '/admin-categories.php', ['tree' => $tree, 'editing' => $editingCategory, 'error' => $error ?? null]);
        return;
    }
    if (pb_query('editar') !== '' && ($editing = pbb_find((int) pb_query('editar')))) {
        echo pb_include(__DIR__ . '/admin-edit.php', ['post' => $editing, 'tree' => $tree, 'error' => null]);
        return;
    }
    $posts = pb_db()->query('SELECT id, title, slug, status, published_on FROM ' . pb_table('blog_posts') . ' ORDER BY published_on DESC, id DESC')->fetchAll();
    $categories = pbb_categories_of(array_map('intval', array_column($posts, 'id')));
    echo pb_include(__DIR__ . '/admin-list.php', ['posts' => $posts, 'categories' => $categories, 'error' => $error ?? null]);
}
