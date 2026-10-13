<div class="card">
    <p class="muted"><a href="<?= e(pb_url('/admin/users')) ?>">← <?= e(__('Usuários')) ?></a></p>
    <h1><?= e($user['name']) ?></h1>
    <?php if ($error): ?>
        <p class="error" role="alert"><?= e($error) ?></p>
    <?php endif ?>
    <form method="post" action="<?= e(pb_url('/admin/users/edit')) ?>">
        <?= pb_csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
        <label for="name"><?= e(__('Nome')) ?></label>
        <input id="name" name="name" required maxlength="100" value="<?= e($user['name']) ?>">
        <label for="email"><?= e(__('E-mail')) ?></label>
        <input id="email" name="email" type="email" required maxlength="190" value="<?= e($user['email']) ?>">
        <label for="role"><?= e(__('Papel')) ?></label>
        <select id="role" name="role">
            <?php foreach (pb_role_labels() as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $user['role'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach ?>
        </select>
        <label for="password"><?= e(__('Nova senha')) ?></label>
        <input id="password" name="password" type="password" minlength="8" autocomplete="new-password">
        <p class="help"><?= e(__('Preencha só se a pessoa esqueceu a senha. Depois, avise a nova senha a ela por um canal seguro.')) ?></p>
        <button type="submit"><?= e(__('Salvar')) ?></button>
    </form>
</div>
