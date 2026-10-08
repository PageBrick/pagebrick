<div class="card auth">
    <h1><?= e(__('Criar uma senha nova')) ?></h1>
    <?php if (empty($valid)): ?>
        <p class="error" role="alert"><?= e(__('Este link não vale mais: ele dura 1 hora e só pode ser usado uma vez. Peça um novo.')) ?></p>
        <p class="auth-back"><a href="<?= e(pb_url('/admin/forgot')) ?>"><?= e(__('Pedir um link novo')) ?></a></p>
    <?php else: ?>
        <?php if (!empty($error)): ?>
            <p class="error" role="alert"><?= e($error) ?></p>
        <?php endif ?>
        <form method="post" action="<?= e(pb_url('/admin/reset')) ?>">
            <?= pb_csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label for="password"><?= e(__('Nova senha')) ?></label>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required autofocus>
            <p class="help"><?= e(__('Pelo menos 8 caracteres.')) ?></p>
            <label for="password_repeat"><?= e(__('Repita a nova senha')) ?></label>
            <input id="password_repeat" name="password_repeat" type="password" autocomplete="new-password" minlength="8" required>
            <button type="submit"><?= e(__('Salvar a senha nova')) ?></button>
        </form>
    <?php endif ?>
</div>
