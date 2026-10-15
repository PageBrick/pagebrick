<?php
/**
 * The menus: the form on the left (each menu a card of rows to drag), the site beside it with the menus as they are typed, live.
 * What is typed waits in a draft, saved by itself, until someone clicks Publish (core/assets/studio.js).
 *
 * @var array $locations     menu location => its name
 * @var bool  $hasDraft      the menus have unpublished changes
 * @var array $previewPages  pages the site can be looked at on
 * @var int   $homeId
 */
$state = $hasDraft ? 'changes' : 'live';
$states = ['live' => __('Publicados · no site'), 'changes' => __('Publicados · com alterações não publicadas')];
$pages = array_values(array_filter(pb_page_list(), fn($p) => $p['translation_of'] === null && $p['locale'] === pb_site_locale()));
usort($pages, fn($a, $b) => $a['id'] <=> $b['id']);

/** One row of a menu: a handle, the text, where it leads, and a way to remove it. $index is a number or "__i__" (the template). */
$row = function (string $location, string $index, array $item) use ($pages): string {
    $name = "menu[$location][$index]";
    $id = 'menu-' . $location . '-' . trim(preg_replace('/[^a-z0-9]+/i', '-', $index), '-');
    $link = (string) ($item['link'] ?? '');
    $note = pb_menu_item_note($item);
    return '<div class="menu-row" data-item data-sort data-menu-item>'
        . '<button type="button" class="pb-grip" data-grip aria-label="' . e(__('Mover este item do menu')) . '" title="' . e(__('Arraste para mudar a posição. Com o teclado: setas para cima e para baixo.')) . '">' . pb_admin_icon('grip-vertical') . '</button>'
        . '<div class="menu-field menu-field-text"><label class="sr-only" for="' . e($id) . '-label">' . e(__('Texto')) . '</label>'
        . '<input id="' . e($id) . '-label" data-menu-label name="' . e($name) . '[label]" maxlength="500" value="' . e((string) ($item['label'] ?? '')) . '" placeholder="' . e(__('Texto do item')) . '" autocomplete="off"></div>'
        . '<div class="menu-field menu-field-link"><label class="sr-only" for="' . e($id) . '-link">' . e(__('Leva para')) . '</label>' . pb_link_input($id . '-link', $name . '[link]', $link) . '</div>'
        . '<button type="button" class="icon-btn menu-remove" data-remove title="' . e(__('Remover do menu')) . '" aria-label="' . e(__('Remover do menu')) . '">' . pb_admin_icon('trash') . '</button>'
        . ($note !== '' ? '<p class="menu-note" role="note">' . pb_admin_icon('circle-alert') . e($note) . '</p>' : '')
        . '</div>';
};
?>
<form method="post" action="<?= e(pb_url('/admin/menus')) ?>" id="menus-form" class="studio-form" data-studio="menus" data-state="<?= e($state) ?>"
      data-preview-url="<?= e(pb_url('/admin/menus/preview')) ?>" data-autosave-url="<?= e(pb_url('/admin/menus/autosave')) ?>"
      data-texts="<?= e(json_encode([
          'saving' => __('Salvando…'),
          'saved' => __('Rascunho salvo às %s'),
          'savedPage' => __('Salvo às %s'),
          'failed' => __('Não foi possível salvar: %s'),
          'offline' => __('Sem conexão. Tente de novo.'),
          'refused' => __('A hospedagem recusou o envio (erro %s). Costuma ser o filtro de segurança dela: um texto que parece código ou um comando, como "<?php" ou "curl https://…", pode causar isso. Mude ou apague esse texto e tente de novo.'),
          'moved' => __('"%1$s" agora é a seção %2$s de %3$s'),
          'loading' => __('Atualizando a página…'),
          'changes' => $states['changes'],
          'live' => $states['live'],
          'itemAdded' => __('Item adicionado ao menu.'),
          'itemRemoved' => __('Item removido do menu.'),
          'menuLabel' => __('Texto do item'),
      ], JSON_UNESCAPED_UNICODE)) ?>">
    <?= pb_csrf_field() ?>

    <div class="studio-bar">
        <div class="studio-crumb">
            <h1 class="studio-title"><?= e(__('Menus')) ?></h1>
            <span class="pill <?= $state === 'live' ? 'ok' : 'warn' ?>" data-state-pill><?= e($states[$state]) ?></span>
        </div>
        <p class="studio-note" role="status" aria-live="polite" data-save-note></p>
        <div class="studio-actions">
            <button type="submit" class="secondary" name="action" value="draft"><?= e(__('Salvar rascunho')) ?></button>
            <button type="submit" name="action" value="publish" data-publish><?= pb_admin_icon('send') ?><?= e(__('Publicar menus')) ?></button>
            <details class="menu studio-more">
                <summary class="icon-btn" title="<?= e(__('Mais ações')) ?>" aria-label="<?= e(__('Mais ações')) ?>"><?= pb_admin_icon('ellipsis') ?></summary>
                <div>
                    <button type="submit" class="menu-item danger" name="action" value="discard" data-discard formnovalidate
                            data-confirm="<?= e(__('Descartar as alterações que ainda não foram publicadas? Os menus voltam à versão que está no site.')) ?>"<?= $hasDraft ? '' : ' hidden' ?>><?= pb_admin_icon('undo') ?><?= e(__('Descartar alterações não publicadas')) ?></button>
                    <a class="menu-item" href="<?= e(pb_url('/')) ?>" target="_blank" rel="noopener"><?= pb_admin_icon('eye') ?><?= e(__('Ver no site')) ?></a>
                </div>
            </details>
        </div>
    </div>

    <div class="studio-tabs" role="tablist" aria-label="<?= e(__('Editar ou ver a página')) ?>">
        <button type="button" role="tab" aria-selected="true" data-tab="edit"><?= e(__('Editar')) ?></button>
        <button type="button" role="tab" aria-selected="false" data-tab="preview"><?= e(__('Ver a página')) ?></button>
    </div>

    <div class="studio-body" data-view="edit">
        <div class="studio-edit thin-scroll">
            <p class="help studio-intro"><?= e(__('Escolha as páginas que aparecem em cada menu e arraste para mudar a ordem. O site ao lado mostra o resultado na hora, e só muda para os visitantes quando você publica. Páginas em rascunho não aparecem no site.')) ?></p>
            <?php foreach ($locations as $location => $label):
                $items = pb_menu_items_working($location); ?>
                <section class="card menu-card" data-menu-location="<?= e($location) ?>" data-edge="<?= $location === 'footer' ? 'bottom' : 'top' ?>" aria-labelledby="menu-title-<?= e($location) ?>">
                    <div class="menu-card-head">
                        <h2 id="menu-title-<?= e($location) ?>"><?= e($label) ?></h2>
                    </div>
                    <fieldset class="menu-list" data-list>
                        <legend class="sr-only"><?= e($label) ?></legend>
                        <div class="menu-cols" aria-hidden="true"><span></span><span><?= e(__('Texto')) ?></span><span><?= e(__('Leva para')) ?></span><span></span></div>
                        <div class="menu-items" data-items data-sortable aria-label="<?= e($label) ?>">
                            <?php foreach (array_values($items) as $i => $item): ?><?= $row($location, (string) $i, (array) $item) ?><?php endforeach ?>
                        </div>
                        <p class="menu-empty muted"><?= e(__('Este menu está vazio: nada aparece no site. Adicione uma página abaixo.')) ?></p>
                        <template><?= $row($location, '__i__', []) ?></template>
                        <div class="menu-add">
                            <button type="button" class="secondary" data-add><?= pb_admin_icon('plus') ?><?= e(__('Adicionar item')) ?></button>
                            <label class="menu-quick">
                                <span class="sr-only"><?= e(__('Adicionar uma página ao menu')) ?></span>
                                <select data-quick-add data-no-save>
                                    <option value=""><?= e(__('ou escolha uma página…')) ?></option>
                                    <?php foreach ($pages as $p): ?>
                                        <option value="page:<?= (int) $p['id'] ?>"><?= e($p['title']) ?><?= $p['status'] !== 'published' ? ' · ' . e(__('rascunho')) : '' ?></option>
                                    <?php endforeach ?>
                                </select>
                            </label>
                        </div>
                    </fieldset>
                </section>
            <?php endforeach ?>
        </div>

        <?php require __DIR__ . '/studio-preview.php'; ?>
    </div>
</form>
