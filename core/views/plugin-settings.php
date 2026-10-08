<form method="post" action="<?= e(pb_url('/admin/plugins/settings')) ?>">
    <?= pb_csrf_field() ?>
    <input type="hidden" name="plugin" value="<?= e($slug) ?>">
    <div class="card">
        <p class="muted"><a href="<?= e(pb_url('/admin/plugins')) ?>">← <?= e(__('Plugins')) ?></a></p>
        <h1><?= e($title) ?></h1>
        <?= pb_field_inputs($fields, $data, 'f') ?>
        <button type="submit"><?= e(__('Salvar')) ?></button>
    </div>
</form>
