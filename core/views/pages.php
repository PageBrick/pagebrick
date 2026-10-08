<?php $statuses = ['draft' => __('Rascunho'), 'published' => __('Publicada')]; ?>
<div class="card">
    <h1><?= e(__('Páginas')) ?></h1>
    <table>
        <thead>
            <tr><th><?= e(__('Título')) ?></th><th><?= e(__('Modelo')) ?></th><th><?= e(__('Situação')) ?></th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($pages as $p): ?>
            <tr>
                <td>
                    <a href="<?= e(pb_url('/admin/pages/edit?id=' . (int) $p['id'])) ?>"><?= e($p['title']) ?></a>
                    <?php if ((int) $p['id'] === $homeId): ?><span class="badge"><?= e(__('Página inicial')) ?></span><?php endif ?>
                </td>
                <td><?= e($templates[$p['template']] ?? $p['template']) ?></td>
                <td><?= e($statuses[$p['status']] ?? $p['status']) ?></td>
                <td class="actions">
                    <?php if ((int) $p['id'] !== $homeId && $p['status'] === 'published'): ?>
                        <form method="post" action="<?= e(pb_url('/admin/pages/home')) ?>">
                            <?= pb_csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="link"><?= e(__('Tornar inicial')) ?></button>
                        </form>
                    <?php endif ?>
                    <?php if ((int) $p['id'] !== $homeId): ?>
                        <form method="post" action="<?= e(pb_url('/admin/pages/delete')) ?>" data-confirm="<?= e(sprintf(__('Excluir a página "%s"? Isso não pode ser desfeito.'), $p['title'])) ?>">
                            <?= pb_csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="link danger"><?= e(__('Excluir')) ?></button>
                        </form>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2><?= e(__('Nova página')) ?></h2>
    <form method="post" action="<?= e(pb_url('/admin/pages')) ?>">
        <?= pb_csrf_field() ?>
        <label for="title"><?= e(__('Título')) ?></label>
        <input id="title" name="title" required maxlength="200">
        <label for="template"><?= e(__('Modelo')) ?></label>
        <select id="template" name="template">
            <?php foreach ($templates as $key => $label): ?>
                <option value="<?= e($key) ?>"><?= e($label) ?></option>
            <?php endforeach ?>
        </select>
        <button type="submit"><?= e(__('Criar página')) ?></button>
    </form>
</div>
