<div class="card auth">
    <?php if (empty($valid)): ?>
        <div class="auth-head">
            <h1><?= e(__('Criar uma senha nova')) ?></h1>
        </div>
        <p class="error" role="alert"><?= e(__('Este link não vale mais: ele dura 1 hora e só pode ser usado uma vez. Peça um novo.')) ?></p>
        <p class="auth-back"><a href="<?= e(pb_url('/admin/forgot')) ?>"><?= e(__('Pedir um link novo')) ?></a></p>
    <?php else: ?>
        <div class="auth-head">
            <h1><?= e(__('Criar uma senha nova')) ?></h1>
            <p class="muted"><?= e(__('Pelo menos 8 caracteres.')) ?></p>
        </div>
        <?php if (!empty($error)): ?>
            <p class="error" role="alert"><?= e($error) ?></p>
        <?php endif ?>
        <form method="post" action="<?= e(pb_url('/admin/reset')) ?>">
            <?= pb_csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label for="password"><?= e(__('Nova senha')) ?></label>
            <div class="input-icon">
                <?= pb_admin_icon('lock') ?>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required autofocus>
            </div>
            <label for="password_repeat"><?= e(__('Repita a nova senha')) ?></label>
            <div class="input-icon">
                <?= pb_admin_icon('lock') ?>
                <input id="password_repeat" name="password_repeat" type="password" autocomplete="new-password" minlength="8" required>
            </div>
            <button type="submit"><?= e(__('Salvar a senha nova')) ?></button>
        </form>
    <?php endif ?>
</div>
