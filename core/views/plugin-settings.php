<form method="post" action="<?= e(pb_url('/admin/plugins/settings')) ?>">
    <?= pb_csrf_field() ?>
    <input type="hidden" name="plugin" value="<?= e($slug) ?>">
    <div class="card">
        <?php if (pb_has_role(pb_current_user(), 'admin')): ?><p class="muted"><a href="<?= e(pb_url('/admin/plugins')) ?>">← <?= e(__('Plugins')) ?></a></p><?php endif ?>
        <h1><?= e($title) ?></h1>
        <?= pb_field_inputs($fields, $data, 'f') ?>
        <button type="submit"><?= e(__('Salvar')) ?></button>
    </div>
</form>
