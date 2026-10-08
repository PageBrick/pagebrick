<?php
/**
 * @var array       $post   with 'categories'
 * @var array       $tree   category tree (pbb_category_tree)
 * @var string|null $error
 */
$checked = array_column($post['categories'], 'id');
?>
<form method="post" action="<?= e(pb_url('/admin/p/blog')) ?>" class="editor">
    <?= pb_csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
    <div class="editor-main">
        <p class="muted"><a href="<?= e(pb_url('/admin/p/blog')) ?>">← <?= e(pbb_title()) ?></a></p>
        <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif ?>
        <div class="card">
            <label for="blog-title"><?= e(__('Título')) ?></label>
            <input id="blog-title" name="title" required maxlength="200" class="big" value="<?= e($post['title']) ?>">
            <?= pb_field_inputs(pbb_fields(), $post['data'], 'f') ?>
        </div>
        <details class="card">
            <summary><?= e(__('Endereço do texto')) ?></summary>
            <label for="blog-slug"><?= e(__('Endereço')) ?></label>
            <input id="blog-slug" name="slug" maxlength="80" value="<?= e($post['slug']) ?>">
            <p class="help"><?= e(__('Fica assim:')) ?> <?= e(pb_absolute_url('/' . pbb_base() . '/' . $post['slug'])) ?></p>
        </details>
    </div>
    <aside class="editor-side">
        <div class="card sticky">
            <label for="blog-status"><?= e(__('Situação')) ?></label>
            <select id="blog-status" name="status">
                <option value="published"<?= $post['status'] === 'published' ? ' selected' : '' ?>><?= e(__('Publicado')) ?></option>
                <option value="draft"<?= $post['status'] !== 'published' ? ' selected' : '' ?>><?= e(__('Rascunho')) ?></option>
            </select>
            <label for="blog-date"><?= e(__('Data')) ?></label>
            <input id="blog-date" name="published_on" type="date" required value="<?= e($post['published_on']) ?>">
            <p class="help"><?= e(__('Com uma data futura, o texto aparece sozinho nesse dia.')) ?></p>
            <fieldset class="categories">
                <legend><?= e(__('Categorias')) ?></legend>
                <?php if (!$tree): ?>
                    <p class="help"><?= e(__('Nenhuma categoria ainda.')) ?> <a href="<?= e(pb_url('/admin/p/blog?aba=categorias')) ?>"><?= e(__('Criar categorias')) ?></a></p>
                <?php endif ?>
                <?php foreach ($tree as $category): ?>
                    <label class="switch" style="padding-left: <?= $category['depth'] * 1.25 ?>rem">
                        <input type="checkbox" name="categories[]" value="<?= $category['id'] ?>"<?= in_array($category['id'], $checked, true) ? ' checked' : '' ?>>
                        <?= e($category['name']) ?>
                    </label>
                <?php endforeach ?>
            </fieldset>
            <button type="submit" name="action" value="save"><?= e(__('Salvar')) ?></button>
            <?php if ($post['status'] === 'published' && strtotime($post['published_on']) <= time()): ?>
                <p><a href="<?= e(pbb_post_url($post)) ?>" target="_blank"><?= e(__('Ver no site')) ?></a></p>
            <?php endif ?>
        </div>
    </aside>
</form>
