<div class="card auth">
    <h1><?= e(__('Entrar no painel')) ?></h1>
    <?php if (!empty($error)): ?>
        <p class="error" role="alert"><?= e($error) ?></p>
    <?php endif ?>
    <form method="post" action="<?= e(pb_url('/admin/login')) ?>">
        <?= pb_csrf_field() ?>
        <label for="email"><?= e(__('E-mail')) ?></label>
        <input id="email" name="email" type="email" autocomplete="username" required autofocus value="<?= e($email ?? '') ?>">
        <div class="label-row">
            <label for="password"><?= e(__('Senha')) ?></label>
            <a href="<?= e(pb_url('/admin/forgot')) ?>"><?= e(__('Esqueci minha senha')) ?></a>
        </div>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="submit"><?= e(__('Entrar')) ?></button>
    </form>
</div>
