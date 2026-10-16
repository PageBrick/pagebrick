<?php
// Blog: importing posts from a JSON file (the "Importar" tab of the blog screen). Loaded by functions.php.
//
// File format (see pbb_import_example(), also offered as a download in the panel):
//   { "categories": [ { "name", "slug", "parent" (a slug or null), "description" } ],
//     "posts": [ { "title", "slug", "published_on" (YYYY-MM-DD), "status" ("published" or "draft"),
//                  "categories": [slugs], "fields": { "summary", "body" } } ] }
// Everything is checked before anything is written; then it is written in one transaction. A post whose address is already used (by another post, or the
// one reserved for categories) is left alone, so importing the same file twice changes nothing. The text goes through the same cleaning as the editor's. Photos are not imported.

const PBB_IMPORT_MAX_BYTES = 2 * 1024 * 1024;
const PBB_IMPORT_MAX_POSTS = 500;

/** The file the "Download an example file" link gives: valid, short, with a subcategory, a published post and a draft. */
function pbb_import_example(): array
{
    return [
        'categories' => [
            ['name' => __('Dicas'), 'slug' => 'dicas', 'parent' => null, 'description' => __('Dicas curtas para o dia a dia.')],
            ['name' => __('Receitas'), 'slug' => 'receitas', 'parent' => 'dicas', 'description' => ''],
        ],
        'posts' => [
            [
                'title' => __('Como guardar o pão'), 'slug' => 'como-guardar-o-pao', 'published_on' => '2026-01-15', 'status' => 'published', 'categories' => ['dicas'],
                'fields' => ['summary' => __('Três ideias para o pão durar mais.'), 'body' => '<p>' . __('Envolva o pão em um pano limpo.') . '</p><h2>' . __('Congelar') . '</h2><p>' . __('Corte em fatias antes.') . '</p>'],
            ],
            [
                'title' => __('Uma sopa simples'), 'slug' => '', 'published_on' => '2026-02-03', 'status' => 'draft', 'categories' => ['receitas'],
                'fields' => ['summary' => '', 'body' => '<p>' . __('Cebola, água e paciência.') . '</p>'],
            ],
        ],
    ];
}

/** Only administrators import (the blog screen itself is open to editors). */
function pbb_can_import(): bool
{
    $user = pb_current_user();
    return $user !== null && pb_has_role($user, 'admin');
}

/**
 * The file's text: the uploaded .json, or what was pasted in the box. Throws with a message the user can read.
 */
function pbb_import_text(): string
{
    $file = $_FILES['file'] ?? ['error' => UPLOAD_ERR_NO_FILE];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($file['error'] ?? null) === UPLOAD_ERR_INI_SIZE) {
            throw new InvalidArgumentException(sprintf(__('O arquivo passa do limite da hospedagem (%s).'), ini_get('upload_max_filesize')));
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException(__('Não consegui receber o arquivo. Tente de novo.'));
        }
        if (($file['size'] ?? 0) > PBB_IMPORT_MAX_BYTES) {
            throw new InvalidArgumentException(pbb_import_too_big());
        }
        return (string) file_get_contents($file['tmp_name']);
    }
    $text = is_string($_POST['json'] ?? null) ? $_POST['json'] : '';
    if (trim($text) === '') {
        throw new InvalidArgumentException(__('Escolha o arquivo .json ou cole o conteúdo dele na caixa.'));
    }
    return $text;
}

function pbb_import_too_big(): string
{
    return sprintf(__('O arquivo passa de %d MB. Divida-o em arquivos menores.'), PBB_IMPORT_MAX_BYTES / 1048576);
}

/**
 * Reads and checks the whole file; writes nothing. Returns ['categories' => [slug => row], 'posts' => [row…]], with every post's address, date,
 * status, categories (slugs), summary and body ready. Throws with ONE message listing what is wrong (the first problems, one per line).
 */
function pbb_import_parse(string $json): array
{
    if (strlen($json) > PBB_IMPORT_MAX_BYTES) {
        throw new InvalidArgumentException(pbb_import_too_big());
    }
    $data = json_decode((string) preg_replace('/^\xEF\xBB\xBF/', '', $json), true);
    if (!is_array($data) || !is_array($data['posts'] ?? null) || !array_is_list($data['posts'])) {
        throw new InvalidArgumentException(__('Não consegui ler o arquivo. Ele precisa ser um JSON com a lista "posts"; baixe o arquivo de exemplo para ver o formato.'));
    }
    if (count($data['posts']) > PBB_IMPORT_MAX_POSTS) {
        throw new InvalidArgumentException(sprintf(__('O arquivo tem textos demais (o limite é %d por vez). Divida-o em arquivos menores.'), PBB_IMPORT_MAX_POSTS));
    }
    $problems = [];

    $categories = [];
    foreach (array_values(is_array($data['categories'] ?? null) ? $data['categories'] : []) as $i => $c) {
        $where = sprintf(__('Categoria %d'), $i + 1);
        $name = is_array($c) && is_string($c['name'] ?? null) ? trim($c['name']) : '';
        if (!preg_match('/^[^\x00-\x1F\x7F]{1,100}$/u', $name)) {
            $problems[] = "$where: " . __('informe o nome (até 100 caracteres).');
            continue;
        }
        $slug = pb_slugify(is_string($c['slug'] ?? null) && trim($c['slug']) !== '' ? $c['slug'] : $name) ?: 'categoria';
        if (isset($categories[$slug])) {
            $problems[] = "$where: " . sprintf(__('o endereço "%s" aparece duas vezes.'), $slug);
            continue;
        }
        $categories[$slug] = [
            'name' => $name, 'slug' => $slug, 'parent' => is_string($c['parent'] ?? null) ? pb_slugify($c['parent']) : '',
            'description' => pb_limit(is_string($c['description'] ?? null) ? trim($c['description']) : '', 500),
        ];
    }
    foreach ($categories as $c) {
        if ($c['parent'] !== '' && $c['parent'] !== $c['slug'] && !isset($categories[$c['parent']]) && !pbb_category_by_slug($c['parent'])) {
            $problems[] = sprintf(__('Categoria "%1$s": a categoria-mãe "%2$s" não existe.'), $c['name'], $c['parent']);
        }
        if ($c['parent'] === $c['slug']) {
            $problems[] = sprintf(__('Categoria "%s": ela não pode ficar dentro dela mesma.'), $c['name']);
        }
    }

    $posts = [];
    $used = [];
    foreach ($data['posts'] as $i => $p) {
        $where = sprintf(__('Texto %d'), $i + 1);
        if (!is_array($p)) {
            $problems[] = "$where: " . __('esperava um objeto com "title", "published_on" e "fields".');
            continue;
        }
        try {
            $title = pb_validate_page_title(is_string($p['title'] ?? null) ? $p['title'] : '');
        } catch (InvalidArgumentException $e) {
            $problems[] = "$where: " . $e->getMessage();
            continue;
        }
        $where = sprintf(__('Texto %1$d ("%2$s")'), $i + 1, pb_limit($title, 40));
        $slug = pb_slugify(is_string($p['slug'] ?? null) ? $p['slug'] : '') ?: (pb_slugify($title) ?: 'texto');
        if (isset($used[$slug])) {
            $problems[] = "$where: " . sprintf(__('o endereço "%s" já foi usado por outro texto do arquivo.'), $slug);
        }
        $used[$slug] = true;
        $date = is_string($p['published_on'] ?? null) ? $p['published_on'] : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            $problems[] = "$where: " . __('a data ("published_on") precisa ser assim: 2026-01-31.');
        }
        $status = $p['status'] ?? 'draft'; // without a status the post comes in as a draft: nothing goes public by accident
        if (!in_array($status, ['published', 'draft'], true)) {
            $problems[] = "$where: " . __('"status" precisa ser "published" ou "draft".');
        }
        $slugs = [];
        foreach (is_array($p['categories'] ?? null) ? $p['categories'] : [] as $given) {
            $category = is_string($given) ? pb_slugify($given) : '';
            if ($category === '' || (!isset($categories[$category]) && !pbb_category_by_slug($category))) {
                $problems[] = "$where: " . sprintf(__('a categoria "%s" não existe (nem no arquivo, nem no blog).'), is_string($given) ? $given : '?');
                continue;
            }
            $slugs[] = $category;
        }
        $fields = is_array($p['fields'] ?? null) ? $p['fields'] : [];
        $posts[] = [
            'title' => $title, 'slug' => $slug, 'published_on' => $date, 'status' => $status, 'categories' => array_values(array_unique($slugs)),
            'summary' => is_string($fields['summary'] ?? null) ? $fields['summary'] : '', 'body' => is_string($fields['body'] ?? null) ? $fields['body'] : '',
        ];
    }

    if ($problems) {
        $more = count($problems) > 8 ? "\n" . sprintf(__('…e mais %d problemas.'), count($problems) - 8) : '';
        throw new InvalidArgumentException(__('Nada foi importado: o arquivo tem o que corrigir.') . "\n" . implode("\n", array_slice($problems, 0, 8)) . $more);
    }
    return ['categories' => $categories, 'posts' => $posts];
}

/**
 * Writes what pbb_import_parse() returned, all or nothing. Categories that already exist (by address) are kept as they are; a post whose address
 * is already used is skipped. Returns ['created' => posts, 'skipped' => posts, 'categories' => new categories].
 */
function pbb_import(array $parsed): array
{
    $pdo = pb_db();
    $pdo->beginTransaction();
    try {
        $ids = [];
        $fresh = [];
        foreach ($parsed['categories'] as $c) {
            if ($existing = pbb_category_by_slug($c['slug'])) {
                $ids[$c['slug']] = (int) $existing['id'];
                continue;
            }
            $ids[$c['slug']] = pbb_save_category(0, $c['name'], $c['slug'], 0, $c['description']);
            $fresh[] = $c['slug'];
        }
        foreach ($fresh as $slug) { // the new ones go inside their parents once every category exists
            $c = $parsed['categories'][$slug];
            $parent = $c['parent'] === '' ? 0 : ($ids[$c['parent']] ?? (int) (pbb_category_by_slug($c['parent'])['id'] ?? 0));
            if ($parent > 0) {
                pbb_save_category($ids[$slug], $c['name'], $slug, $parent, $c['description']);
            }
        }
        $created = $skipped = 0;
        foreach ($parsed['posts'] as $p) {
            if (pbb_slug_taken($p['slug'])) {
                $skipped++;
                continue;
            }
            $categoryIds = array_map(fn($slug) => $ids[$slug] ?? (int) (pbb_category_by_slug($slug)['id'] ?? 0), $p['categories']);
            pbb_save(pbb_create($p['title']), [
                'title' => $p['title'], 'slug' => $p['slug'], 'status' => $p['status'], 'published_on' => $p['published_on'],
                'f' => ['summary' => $p['summary'], 'image' => '', 'body' => $p['body']], 'categories' => $categoryIds,
            ]);
            $created++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['created' => $created, 'skipped' => $skipped, 'categories' => count($fresh)];
}

/** The example file as a download (the Import tab's link). Sends the file and ends the request. */
function pbb_import_download_example(): never
{
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="blog-exemplo.json"');
    echo json_encode(pbb_import_example(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit;
}
