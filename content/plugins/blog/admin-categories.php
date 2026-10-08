<?php
/**
 * @var array       $tree     category tree (pbb_category_tree)
 * @var array|null  $editing  category being edited
 * @var string|null $error
 */
$form = $editing ?? ['id' => 0, 'name' => '', 'slug' => '', 'parent_id' => null, 'description' => ''];
?>
<nav class="tabs"><a href="<?= e(pb_url('/admin/p/blog')) ?>"><?= e(__('Textos')) ?></a><a href="<?= e(pb_url('/admin/p/blog?aba=categorias')) ?>" aria-current="page"><?= e(__('Categorias')) ?></a></nav>
<?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif ?>
<div class="card">
    <h1><?= e(__('Categorias')) ?></h1>
    <p class="help"><?= e(__('Organize os textos por assunto. Uma categoria pode ficar dentro de outra (por exemplo, "Receitas" dentro de "Dicas"). Cada categoria ganha uma página no site com os textos dela e das que estão dentro dela.')) ?></p>
    <?php if (!$tree): ?>
        <p class="muted"><?= e(__('Nenhuma categoria ainda.')) ?></p>
    <?php else: ?>
        <table>
            <thead><tr><th><?= e(__('Nome')) ?></th><th><?= e(__('Endereço')) ?></th><th><?= e(__('Textos')) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($tree as $c): ?>
                <tr>
                    <td style="padding-left: <?= .5 + $c['depth'] * 1.5 ?>rem"><?= $c['depth'] ? '↳ ' : '' ?><?= e($c['name']) ?></td>
                    <td><a href="<?= e(pbb_url(pbb_category_prefix() . '/' . $c['slug'])) ?>" target="_blank">/<?= e(pbb_base() . '/' . pbb_category_prefix() . '/' . $c['slug']) ?></a></td>
                    <td><?= pbb_published_count([$c['id']]) ?></td>
                    <td class="actions">
                        <a href="<?= e(pb_url('/admin/p/blog?aba=categorias&editar=' . $c['id'])) ?>"><?= e(__('Editar')) ?></a>
                        <form method="post" action="<?= e(pb_url('/admin/p/blog')) ?>" data-confirm="<?= e(sprintf(__('Excluir a categoria "%s"? Os textos continuam publicados; as subcategorias sobem um nível.'), $c['name'])) ?>">
                            <?= pb_csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" name="action" value="delete-category" class="link danger"><?= e(__('Excluir')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>
<form class="card" method="post" action="<?= e(pb_url('/admin/p/blog?aba=categorias')) ?>">
    <?= pb_csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
    <h2><?= $form['id'] ? e(sprintf(__('Editar "%s"'), $form['name'])) : e(__('Nova categoria')) ?></h2>
    <label for="cat-name"><?= e(__('Nome')) ?></label>
    <input id="cat-name" name="name" required maxlength="100" value="<?= e($form['name']) ?>">
    <label for="cat-parent"><?= e(__('Dentro de')) ?></label>
    <select id="cat-parent" name="parent_id">
        <option value="0"><?= e(__('Nenhuma (categoria principal)')) ?></option>
        <?php foreach ($tree as $c): ?>
            <?php if ($form['id'] && in_array($form['id'], $c['path'], true)) continue; // not inside itself ?>
            <option value="<?= $c['id'] ?>"<?= $c['id'] === $form['parent_id'] ? ' selected' : '' ?>><?= str_repeat('— ', $c['depth']) . e($c['name']) ?></option>
        <?php endforeach ?>
    </select>
    <label for="cat-description"><?= e(__('Descrição (opcional)')) ?></label>
    <textarea id="cat-description" name="description" maxlength="500" rows="2"><?= e($form['description']) ?></textarea>
    <p class="help"><?= e(__('Aparece no topo da página da categoria e no Google.')) ?></p>
    <?php if ($form['id']): ?>
        <label for="cat-slug"><?= e(__('Endereço')) ?></label>
        <input id="cat-slug" name="slug" maxlength="80" value="<?= e($form['slug']) ?>">
    <?php endif ?>
    <button type="submit" name="action" value="save-category"><?= e($form['id'] ? __('Salvar categoria') : __('Criar categoria')) ?></button>
    <?php if ($form['id']): ?><a href="<?= e(pb_url('/admin/p/blog?aba=categorias')) ?>"><?= e(__('Cancelar')) ?></a><?php endif ?>
</form>
