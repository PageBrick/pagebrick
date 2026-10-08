<form method="post" action="<?= e(pb_url('/admin/menus')) ?>">
    <?= pb_csrf_field() ?>
    <div class="card">
        <h1><?= e(__('Menus')) ?></h1>
        <p class="help"><?= e(__('Escolha as páginas que aparecem em cada menu e a ordem delas. Páginas em rascunho não aparecem no site.')) ?></p>
        <?php foreach ($locations as $location => $label): ?>
            <?= pb_field_input(pb_menu_def($label), pb_menu_items_raw($location), 'menu[' . $location . ']') ?>
        <?php endforeach ?>
        <button type="submit"><?= e(__('Salvar menus')) ?></button>
    </div>
</form>
