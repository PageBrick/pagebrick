<?php
/**
 * The divider and the site beside the form, shared by the split screens (the page editor and the menus).
 *
 * @var array|null $previewPages  pages the site can be looked at on (the menus screen); null: no choice
 * @var int        $homeId        the one chosen at first
 */
$previewPages ??= null;
?>
        <div class="studio-split" role="separator" aria-orientation="vertical" tabindex="0" data-split
             aria-label="<?= e(__('Tamanho do formulário e da página')) ?>" title="<?= e(__('Arraste para mudar o tamanho das duas partes. As setas do teclado ajustam; dois cliques voltam ao tamanho de antes.')) ?>"></div>
        <aside class="studio-preview" aria-label="<?= e(__('A página como os visitantes vão ver')) ?>">
            <div class="studio-preview-bar">
                <span class="studio-preview-title"><?= e(__('Como vai ficar')) ?></span>
                <?php if ($previewPages): ?>
                    <label class="studio-preview-page">
                        <span class="sr-only"><?= e(__('Ver o site na página')) ?></span>
                        <select name="page" data-no-save>
                            <?php foreach ($previewPages as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"<?= (int) $p['id'] === (int) ($homeId ?? 0) ? ' selected' : '' ?>><?= e($p['title']) ?></option>
                            <?php endforeach ?>
                        </select>
                    </label>
                <?php endif ?>
                <span class="studio-preview-status" data-preview-status role="status"></span>
                <span class="seg" role="group" aria-label="<?= e(__('Tamanho da tela')) ?>">
                    <button type="button" data-device="desktop" aria-pressed="true" title="<?= e(__('Computador')) ?>" aria-label="<?= e(__('Computador')) ?>"><?= pb_admin_icon('monitor') ?></button>
                    <button type="button" data-device="phone" aria-pressed="false" title="<?= e(__('Celular')) ?>" aria-label="<?= e(__('Celular')) ?>"><?= pb_admin_icon('smartphone') ?></button>
                </span>
            </div>
            <div class="studio-frames" data-device="desktop">
                <iframe class="studio-frame" title="<?= e(__('A página como os visitantes vão ver')) ?>" data-frame="a"></iframe>
                <iframe class="studio-frame" title="<?= e(__('A página como os visitantes vão ver')) ?>" data-frame="b"></iframe>
            </div>
        </aside>
