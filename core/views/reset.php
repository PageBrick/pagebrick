<?php
/**
 * @var bool $valid   the link still works
 * @var bool $invite  the link is an invitation: the person chooses their first password and goes straight in
 */
$invite = !empty($invite);
?>
<div class="card auth">
    <?php if (empty($valid)): ?>
        <div class="auth-head">
            <h1><?= e(__('Criar uma senha nova')) ?></h1>
        </div>
        <p class="error" role="alert"><?= e(__('Este link não vale mais: ele só pode ser usado uma vez e tem prazo (1 hora para trocar a senha, 7 dias para um convite). Peça um novo.')) ?></p>
        <p class="auth-back"><a href="<?= e(pb_url('/admin/forgot')) ?>"><?= e(__('Pedir um link novo')) ?></a></p>
    <?php else: ?>
        <div class="auth-head">
            <h1><?= e($invite ? sprintf(__('Bem-vindo ao painel de %s'), pb_option('site_title', 'PageBrick')) : __('Criar uma senha nova')) ?></h1>
            <p class="muted"><?= e($invite ? __('Crie a sua senha para entrar. Pelo menos 8 caracteres.') : __('Pelo menos 8 caracteres.')) ?></p>
        </div>
        <?php if (!empty($error)): ?>
            <p class="error" role="alert"><?= e($error) ?></p>
        <?php endif ?>
        <form method="post" action="<?= e(pb_url('/admin/reset')) ?>">
            <?= pb_csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label for="password"><?= e($invite ? __('Senha') : __('Nova senha')) ?></label>
            <div class="input-icon">
                <?= pb_admin_icon('lock') ?>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required autofocus>
            </div>
            <label for="password_repeat"><?= e($invite ? __('Repita a senha') : __('Repita a nova senha')) ?></label>
            <div class="input-icon">
                <?= pb_admin_icon('lock') ?>
                <input id="password_repeat" name="password_repeat" type="password" autocomplete="new-password" minlength="8" required>
            </div>
            <button type="submit"><?= e($invite ? __('Criar a senha e entrar') : __('Salvar a senha nova')) ?></button>
        </form>
    <?php endif ?>
</div>
