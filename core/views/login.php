<div class="card auth">
    <div class="auth-head">
        <h1><?= e(__('Entrar no painel')) ?></h1>
        <p class="muted"><?= e(sprintf(__('Painel de %s'), pb_option('site_title', 'PageBrick'))) ?></p>
    </div>
    <?php if (!empty($error)): ?>
        <p class="error" role="alert"><?= e($error) ?></p>
    <?php endif ?>
    <form method="post" action="<?= e(pb_url('/admin/login')) ?>">
        <?= pb_csrf_field() ?>
        <label for="email"><?= e(__('E-mail')) ?></label>
        <div class="input-icon">
            <?= pb_admin_icon('mail') ?>
            <input id="email" name="email" type="email" autocomplete="username" required autofocus value="<?= e($email ?? '') ?>">
        </div>
        <div class="label-row">
            <label for="password"><?= e(__('Senha')) ?></label>
            <a href="<?= e(pb_url('/admin/forgot')) ?>"><?= e(__('Esqueci minha senha')) ?></a>
        </div>
        <div class="input-icon">
            <?= pb_admin_icon('lock') ?>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button type="submit"><?= e(__('Entrar')) ?></button>
    </form>
</div>
