<?php $form = fn(string $action, string $label, string $class = '', string $confirm = '') =>
    '<form method="post" action="' . e(pb_url('/admin/updates')) . '"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>'
    . pb_csrf_field() . '<button type="submit" name="action" value="' . e($action) . '" class="' . e($class) . '">' . e($label) . '</button></form>'; ?>
<div class="card">
    <h1><?= e(__('Atualizações')) ?></h1>
    <p><?= e(sprintf(__('Você está usando o PageBrick %s.'), PB_VERSION)) ?></p>
    <?php if ($catalogError): ?>
        <p class="error"><?= e(sprintf(__('Não consegui verificar: %s'), $catalogError)) ?></p>
    <?php elseif ($checkedAt): ?>
        <p class="muted"><?= e(sprintf(__('Última verificação: %s.'), pb_date(date('Y-m-d H:i:s', $checkedAt), true))) ?></p>
    <?php endif ?>
    <?= $form('check', __('Verificar agora'), 'secondary') ?>
</div>

<div class="card">
    <h2><?= e(__('PageBrick')) ?></h2>
    <?php if ($lastResult): ?>
        <?php if ($lastResult['ok']): ?>
            <p class="flash ok"><?= e(sprintf(__('Atualizado da versão %s para a %s em %s. Todas as páginas do site foram conferidas depois da atualização.'), $lastResult['from'], $lastResult['to'], pb_date($lastResult['at'], true))) ?></p>
        <?php else: ?>
            <p class="flash error"><?= e(sprintf(__('A atualização para a versão %s foi desfeita automaticamente em %s, porque %s. O site continua na versão %s, como estava.'), $lastResult['to'], pb_date($lastResult['at'], true), $lastResult['problem'], $lastResult['from'])) ?></p>
        <?php endif ?>
    <?php endif ?>
    <?php if ($updates['core']): ?>
        <p><?= e(sprintf(__('A versão %s está disponível.'), $updates['core']['version'])) ?></p>
        <?php if (!empty($updates['core']['notes'])): ?><p class="muted"><?= e((string) $updates['core']['notes']) ?></p><?php endif ?>
        <?php if ($blockers): ?>
            <div class="flash error" role="alert">
                <p><strong><?= e(__('Ainda não dá para atualizar sem risco de quebrar o site:')) ?></strong></p>
                <ul><?php foreach ($blockers as $blocker): ?><li><?= e($blocker) ?></li><?php endforeach ?></ul>
            </div>
        <?php else: ?>
            <p class="help"><?= e(__('Como funciona: antes, o PageBrick guarda uma cópia da versão atual. Depois, confere todas as páginas do site com a versão nova; se alguma der erro, volta sozinho para a versão atual. Páginas, fotos, temas e plugins não são alterados.')) ?></p>
            <?= $form('update-core', sprintf(__('Atualizar para %s'), $updates['core']['version']), '', __('Atualizar o PageBrick agora?')) ?>
        <?php endif ?>
    <?php else: ?>
        <p class="muted"><?= e(__('Está na versão mais nova.')) ?></p>
    <?php endif ?>
    <?php if ($coreBackups): ?>
        <?= $form('restore-core', sprintf(__('Voltar para a versão %s'), $coreBackups[0]['version']), 'link', __('Voltar para a versão anterior do PageBrick?')) ?>
    <?php endif ?>
</div>

<div class="card">
    <h2><?= e(__('Plugins e temas')) ?></h2>
    <?php if (!$updates['plugin'] && !$updates['theme']): ?>
        <p class="muted"><?= e(__('Tudo em dia.')) ?></p>
    <?php endif ?>
    <ul>
        <?php foreach ($updates['plugin'] as $slug => $entry): ?>
            <li><a href="<?= e(pb_url('/admin/plugins')) ?>"><?= e(sprintf(__('Plugin %s: versão %s'), $entry['name'] ?? $slug, $entry['version'])) ?></a></li>
        <?php endforeach ?>
        <?php foreach ($updates['theme'] as $slug => $entry): ?>
            <li><a href="<?= e(pb_url('/admin/themes')) ?>"><?= e(sprintf(__('Tema %s: versão %s'), $entry['name'] ?? $slug, $entry['version'])) ?></a></li>
        <?php endforeach ?>
    </ul>
</div>
