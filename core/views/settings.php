<?php
/**
 * @var string      $siteTitle
 * @var array       $fields       settings fields (standard + theme)
 * @var array       $data
 * @var string|null $error
 * @var string|null $translating  extra language whose texts are being translated, or null
 */
$locales = pb_site_locales();
?>
<?php if (count($locales) > 1): ?>
    <nav class="tabs" aria-label="<?= e(__('Idiomas')) ?>">
        <?php foreach ($locales as $i => $code): ?>
            <a href="<?= e(pb_url($i === 0 ? '/admin/settings' : '/admin/settings?idioma=' . rawurlencode($code))) ?>" lang="<?= e($code) ?>"
               <?= ($translating ?? $locales[0]) === $code ? 'aria-current="page"' : '' ?>><?= e(PB_LOCALES[$code]) ?><?= $i === 0 ? ' · ' . e(__('principal')) : '' ?></a>
        <?php endforeach ?>
    </nav>
<?php endif ?>

<?php if ($translating): ?>
<form method="post" action="<?= e(pb_url('/admin/settings')) ?>">
    <?= pb_csrf_field() ?>
    <input type="hidden" name="translation" value="<?= e($translating) ?>">
    <div class="card">
        <h1><?= e(sprintf(__('Textos em %s'), PB_LOCALES[$translating])) ?></h1>
        <p class="help"><?= e(__('Só os textos mudam de um idioma para outro: logo, cores, telefone e redes sociais vêm do idioma principal. Um campo em branco mostra o texto do idioma principal.')) ?></p>
        <?= pb_field_inputs(pb_text_fields($fields), pb_settings_translation($translating), 'f') ?>
        <button type="submit"><?= e(__('Salvar')) ?></button>
    </div>
</form>
<?php else: ?>
<form method="post" action="<?= e(pb_url('/admin/settings')) ?>">
    <?= pb_csrf_field() ?>
    <div class="card">
        <h1><?= e(__('Aparência e contato')) ?></h1>
        <?php if ($error): ?>
            <p class="error" role="alert"><?= e($error) ?></p>
        <?php endif ?>
        <label for="site_title"><?= e(__('Nome do site')) ?></label>
        <input id="site_title" name="site_title" required maxlength="100" value="<?= e($siteTitle) ?>">
        <?php if (pb_has_role(pb_current_user(), 'admin')): ?>
            <p class="help"><?= e(__('Endereço e idiomas do site ficam em Configurações, no ícone de engrenagem no topo.')) ?></p>
        <?php endif ?>
        <?= pb_field_inputs($fields, $data, 'f') ?>
        <button type="submit"><?= e(__('Salvar')) ?></button>
    </div>
</form>
<?php endif ?>
