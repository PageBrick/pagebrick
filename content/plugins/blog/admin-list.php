<?php
/**
 * @var array       $posts
 * @var array       $categories  post id => [category rows]
 * @var string|null $error
 */
?>
<nav class="tabs"><a href="<?= e(pb_url('/admin/p/blog')) ?>" aria-current="page"><?= e(__('Textos')) ?></a><a href="<?= e(pb_url('/admin/p/blog?aba=categorias')) ?>"><?= e(__('Categorias')) ?></a></nav>
<?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif ?>
<div class="card">
    <h1><?= e(pbb_title()) ?></h1>
    <p class="help"><?= e(__('No site:')) ?> <a href="<?= e(pbb_url()) ?>" target="_blank"><?= e(pb_absolute_url('/' . pbb_base())) ?></a>. <?= e(__('O nome e o endereço mudam em Plugins → Blog → Configurar.')) ?></p>
    <?php if (!$posts): ?>
        <p class="muted"><?= e(__('Nenhum texto ainda.')) ?></p>
    <?php else: ?>
        <table>
            <thead><tr><th><?= e(__('Título')) ?></th><th><?= e(__('Categorias')) ?></th><th><?= e(__('Data')) ?></th><th><?= e(__('Situação')) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($posts as $p): ?>
                <tr>
                    <td><a href="<?= e(pb_url('/admin/p/blog?editar=' . (int) $p['id'])) ?>"><?= e($p['title']) ?></a></td>
                    <td><?= e(implode(', ', array_column($categories[(int) $p['id']] ?? [], 'name'))) ?></td>
                    <td><?= e(pb_date($p['published_on'])) ?></td>
                    <td><?= e($p['status'] === 'published' ? (strtotime($p['published_on']) > time() ? __('Agendado') : __('Publicado')) : __('Rascunho')) ?></td>
                    <td class="actions">
                        <form method="post" action="<?= e(pb_url('/admin/p/blog')) ?>" data-confirm="<?= e(sprintf(__('Excluir "%s"?'), $p['title'])) ?>">
                            <?= pb_csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" name="action" value="delete" class="link danger"><?= e(__('Excluir')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>
<div class="card">
    <h2><?= e(__('Novo texto')) ?></h2>
    <form method="post" action="<?= e(pb_url('/admin/p/blog')) ?>">
        <?= pb_csrf_field() ?>
        <label for="blog-title"><?= e(__('Título')) ?></label>
        <input id="blog-title" name="title" required maxlength="200">
        <button type="submit" name="action" value="create"><?= e(__('Criar texto')) ?></button>
    </form>
</div>
