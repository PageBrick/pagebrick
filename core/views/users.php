<?php $roles = pb_role_labels(); ?>
<div class="page-head">
    <h1><span class="crumb"><?= e(__('Configurações')) ?> <span aria-hidden="true">/</span></span> <?= e(__('Usuários')) ?></h1>
    <div class="page-actions"><a class="button" href="#novo-usuario"><?= pb_admin_icon('plus') ?><?= e(__('Novo usuário')) ?></a></div>
</div>
<div class="card flush">
    <div class="table-wrap">
    <table>
        <thead>
            <tr><th><?= e(__('Nome')) ?></th><th><?= e(__('E-mail')) ?></th><th><?= e(__('Papel')) ?></th><th><span class="sr-only"><?= e(__('Ações')) ?></span></th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['name']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td><span class="pill<?= $u['role'] === 'admin' ? ' info' : '' ?>"><?= e($roles[$u['role']] ?? $u['role']) ?></span></td>
                <td class="actions">
                    <?php if ((int) $u['id'] === (int) $current['id']): ?>
                        <a href="<?= e(pb_url('/admin/account')) ?>"><?= e(__('Minha conta')) ?></a>
                    <?php else: ?>
                        <a href="<?= e(pb_url('/admin/users/edit?id=' . (int) $u['id'])) ?>"><?= e(__('Editar')) ?></a>
                        <form method="post" action="<?= e(pb_url('/admin/users/delete')) ?>" data-confirm="<?= e(sprintf(__('Excluir %s?'), $u['name'])) ?>">
                            <?= pb_csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" class="link"><?= e(__('Excluir')) ?></button>
                        </form>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    </div>
</div>

<div class="card" id="novo-usuario">
    <h2><?= e(__('Novo usuário')) ?></h2>
    <?php if (!empty($error)): ?>
        <p class="error" role="alert"><?= e($error) ?></p>
    <?php endif ?>
    <form method="post" action="<?= e(pb_url('/admin/users')) ?>">
        <?= pb_csrf_field() ?>
        <label for="name"><?= e(__('Nome')) ?></label>
        <input id="name" name="name" required maxlength="100" value="<?= e($old['name'] ?? '') ?>">
        <label for="email"><?= e(__('E-mail')) ?></label>
        <input id="email" name="email" type="email" required maxlength="190" value="<?= e($old['email'] ?? '') ?>">
        <label for="password"><?= e(__('Senha')) ?></label>
        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
        <label for="role"><?= e(__('Papel')) ?></label>
        <select id="role" name="role">
            <?php foreach ($roles as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= ($old['role'] ?? 'editor') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach ?>
        </select>
        <p class="muted"><?= e(__('Editor: edita o conteúdo do site. Administrador: também instala plugins, troca o tema e gerencia usuários.')) ?></p>
        <button type="submit"><?= e(__('Criar usuário')) ?></button>
    </form>
</div>
