<div class="card">
    <h1><?= e(__('Mídia')) ?></h1>
    <form method="post" action="<?= e(pb_url('/admin/media')) ?>" enctype="multipart/form-data" class="inline">
        <?= pb_csrf_field() ?>
        <label for="file"><?= e(__('Enviar fotos ou PDFs')) ?></label>
        <input id="file" type="file" name="file[]" multiple required accept="image/jpeg,image/png,image/webp,image/gif,application/pdf">
        <button type="submit"><?= e(__('Enviar')) ?></button>
    </form>
    <p class="help"><?= e(__('Fotos grandes são reduzidas automaticamente para o site ficar rápido.')) ?></p>
</div>

<?php if (!$items): ?>
    <p class="muted"><?= e(__('Nenhum arquivo ainda.')) ?></p>
<?php endif ?>
<div class="media-list">
    <?php foreach ($items as $m): ?>
        <div class="card media-item">
            <?php if (str_starts_with($m['mime'], 'image/')): ?>
                <img src="<?= e(pb_media_url($m, 'thumb')) ?>" alt="" loading="lazy">
            <?php else: ?>
                <a class="file" href="<?= e(pb_media_url($m)) ?>" target="_blank">PDF</a>
            <?php endif ?>
            <p class="muted" title="<?= e($m['original_name']) ?>"><?= e($m['original_name']) ?></p>
            <form method="post" action="<?= e(pb_url('/admin/media/alt')) ?>">
                <?= pb_csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <label for="alt-<?= (int) $m['id'] ?>"><?= e(__('Descrição (para cegos e Google)')) ?></label>
                <input id="alt-<?= (int) $m['id'] ?>" name="alt" maxlength="255" value="<?= e($m['alt']) ?>">
                <button type="submit" class="secondary"><?= e(__('Salvar descrição')) ?></button>
            </form>
            <p><input readonly aria-label="<?= e(__('Endereço do arquivo')) ?>" value="<?= e(pb_absolute_url('content/uploads/' . $m['path'])) ?>"></p>
            <form method="post" action="<?= e(pb_url('/admin/media/delete')) ?>" data-confirm="<?= e(__('Excluir este arquivo? Ele some das páginas que o usam.')) ?>">
                <?= pb_csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <button type="submit" class="link danger"><?= e(__('Excluir')) ?></button>
            </form>
        </div>
    <?php endforeach ?>
</div>
