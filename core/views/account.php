<div class="card">
    <h1><?= e(__('Minha conta')) ?></h1>
    <?php if ($error): ?>
        <p class="error" role="alert"><?= e($error) ?></p>
    <?php endif ?>
    <form method="post" action="<?= e(pb_url('/admin/account')) ?>">
        <?= pb_csrf_field() ?>
        <label for="name"><?= e(__('Nome')) ?></label>
        <input id="name" name="name" required maxlength="100" value="<?= e($user['name']) ?>">
        <label for="email"><?= e(__('E-mail')) ?></label>
        <input id="email" name="email" type="email" required maxlength="190" autocomplete="username" value="<?= e($user['email']) ?>">
        <label for="locale"><?= e(__('Idioma do painel')) ?></label>
        <select id="locale" name="locale">
            <option value=""><?= e(sprintf(__('O mesmo do site (%s)'), PB_LOCALES[pb_site_locale()])) ?></option>
            <?php foreach (PB_LOCALES as $code => $name): ?>
                <option value="<?= e($code) ?>"<?= $code === ($user['locale'] ?? '') ? ' selected' : '' ?>><?= e($name) ?></option>
            <?php endforeach ?>
        </select>
        <p class="help"><?= e(__('Vale só para você. O site e os outros usuários continuam no idioma do site.')) ?></p>
        <label for="password"><?= e(__('Nova senha')) ?></label>
        <input id="password" name="password" type="password" minlength="8" autocomplete="new-password">
        <p class="help"><?= e(__('Deixe em branco para manter a senha atual.')) ?></p>
        <label for="current_password"><?= e(__('Senha atual (para confirmar)')) ?></label>
        <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
        <button type="submit"><?= e(__('Salvar')) ?></button>
    </form>
</div>
