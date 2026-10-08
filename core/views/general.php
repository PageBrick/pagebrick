<?php
/**
 * @var string|null $error
 * @var array       $old     what was typed, when saving failed
 */
$siteUrl = $old['site_url'] ?? rtrim(pb_absolute_url(), '/');
$main = $old['locale'] ?? pb_site_locale();
$extra = $old['locales'] ?? array_slice(pb_site_locales(), 1);
?>
<form method="post" action="<?= e(pb_url('/admin/general')) ?>">
    <?= pb_csrf_field() ?>
    <div class="card">
        <h1><?= e(__('Endereço e idiomas')) ?></h1>
        <?php if ($error): ?>
            <p class="error" role="alert"><?= e($error) ?></p>
        <?php endif ?>
        <label for="site_url"><?= e(__('Endereço do site')) ?></label>
        <input id="site_url" name="site_url" type="url" required maxlength="190" value="<?= e($siteUrl) ?>">
        <p class="help"><?= e(__('O endereço oficial, usado pelo Google, no mapa do site e nos e-mails. Com o cadeado de segurança no navegador, use https://.')) ?></p>
        <?php if (pb_is_https() && str_starts_with(pb_absolute_url(), 'http://')): ?>
            <p class="flash error"><?= e(__('O site já abre com https://, mas o endereço oficial ainda começa com http://. Troque para https:// e salve.')) ?></p>
        <?php endif ?>
        <label for="locale"><?= e(__('Idioma do site')) ?></label>
        <select id="locale" name="locale">
            <?php foreach (PB_LOCALES as $code => $name): ?>
                <option value="<?= e($code) ?>"<?= $code === $main ? ' selected' : '' ?>><?= e($name) ?></option>
            <?php endforeach ?>
        </select>
        <p class="help"><?= e(__('Muda os textos fixos do site (menus, botões, formulário) e o idioma informado ao Google. O texto das suas páginas não é traduzido automaticamente.')) ?></p>
        <fieldset class="categories">
            <legend><?= e(__('Outros idiomas do site')) ?></legend>
            <?php foreach (PB_LOCALES as $code => $name): if ($code === pb_site_locale()) continue; ?>
                <label class="switch"><input type="checkbox" name="locales[]" value="<?= e($code) ?>"<?= in_array($code, $extra, true) ? ' checked' : '' ?>>
                    <?= e($name) ?> <span class="muted"><?= e(pb_locale_path($code, '/') === '/' ? '' : '(' . pb_absolute_url(pb_locale_path($code, '/')) . ')') ?></span></label>
            <?php endforeach ?>
            <p class="help"><?= e(__('Para oferecer o site em mais idiomas. Depois, em Páginas, traduza cada página; o tema mostra um seletor de idioma se tiver um.')) ?></p>
        </fieldset>
        <button type="submit"><?= e(__('Salvar')) ?></button>
    </div>
</form>
