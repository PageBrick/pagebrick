<form method="post" action="<?= e(pb_url('/admin/settings')) ?>">
    <?= pb_csrf_field() ?>
    <div class="card">
        <h1><?= e(__('Aparência e contato')) ?></h1>
        <?php if ($error): ?>
            <p class="error" role="alert"><?= e($error) ?></p>
        <?php endif ?>
        <label for="site_title"><?= e(__('Nome do site')) ?></label>
        <input id="site_title" name="site_title" required maxlength="100" value="<?= e($siteTitle) ?>">
        <label for="locale"><?= e(__('Idioma do site')) ?></label>
        <select id="locale" name="locale">
            <?php foreach (PB_LOCALES as $code => $name): ?>
                <option value="<?= e($code) ?>"<?= $code === pb_site_locale() ? ' selected' : '' ?>><?= e($name) ?></option>
            <?php endforeach ?>
        </select>
        <p class="help"><?= e(__('Muda os textos fixos do site (menus, botões, formulário) e o idioma informado ao Google. O texto das suas páginas não é traduzido automaticamente.')) ?></p>
        <?= pb_field_inputs($fields, $data, 'f') ?>
        <button type="submit"><?= e(__('Salvar')) ?></button>
    </div>
</form>
