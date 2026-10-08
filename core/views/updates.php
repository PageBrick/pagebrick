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
        <p><?= e(sprintf(__('A versão %s está disponível.'), $updates['core']['version'])) ?>
            <?php if (!$blockers && pb_auto_update_allows((string) $updates['core']['version'], pb_auto_update_mode())): ?><?= e(__('Ela se instala sozinha nas próximas horas, ou agora pelo botão.')) ?><?php endif ?></p>
        <?php if (!empty($updates['core']['notes'])): ?><p class="muted"><?= e((string) $updates['core']['notes']) ?></p><?php endif ?>
        <?php if ($blockers): ?>
            <div class="flash error" role="alert">
                <p><strong><?= e(__('Ainda não dá para atualizar sem risco de quebrar o site:')) ?></strong></p>
                <ul><?php foreach ($blockers as $blocker): ?><li><?= e($blocker) ?></li><?php endforeach ?></ul>
            </div>
        <?php else: ?>
            <p class="help"><?= e(__('Como funciona: antes, o PageBrick guarda uma cópia da versão atual. Depois, confere todas as páginas do site com a versão nova; se alguma der erro, volta sozinho para a versão atual. Páginas, fotos, temas e plugins não são alterados.')) ?></p>
            <?php $to = (string) $updates['core']['version']; // admin.js runs the update step by step and shows each one ?>
            <form method="post" action="<?= e(pb_url('/admin/updates')) ?>" data-confirm="<?= e(__('Atualizar o PageBrick agora?')) ?>" data-update-core
                  data-step-url="<?= e(pb_url('/admin/updates/step')) ?>" data-result-url="<?= e(pb_url('/admin/updates/result')) ?>"
                  data-download="<?= e(sprintf(__('Baixando a versão %s'), $to)) ?>" data-verify="<?= e(__('Conferindo a assinatura')) ?>"
                  data-backup="<?= e(__('Guardando uma cópia da versão atual')) ?>" data-swap="<?= e(__('Trocando o núcleo')) ?>"
                  data-check="<?= e(__('Abrindo todas as páginas do site')) ?>" data-ok="<?= e(sprintf(__('Tudo certo: o site está na versão %s.'), $to)) ?>"
                  data-undone="<?= e(sprintf(__('A versão %1$s foi desfeita sozinha, porque %3$s. O site continua na versão %2$s, e os visitantes não viram nada.'), $to, PB_VERSION, '%s')) ?>"
                  data-failed="<?= e(__('A atualização parou. Recarregue a página para ver como o site ficou.')) ?>" data-continue="<?= e(__('Continuar')) ?>">
                <?= pb_csrf_field() ?>
                <button type="submit" name="action" value="update-core"><?= e(sprintf(__('Atualizar para %s'), $to)) ?></button>
            </form>
        <?php endif ?>
    <?php else: ?>
        <p class="muted"><?= e(__('Está na versão mais nova.')) ?></p>
    <?php endif ?>
    <?php if ($coreBackups): ?>
        <?= $form('restore-core', sprintf(__('Voltar para a versão %s'), $coreBackups[0]['version']), 'link', __('Voltar para a versão anterior do PageBrick?')) ?>
    <?php endif ?>
</div>

<form class="card" method="post" action="<?= e(pb_url('/admin/updates')) ?>">
    <?= pb_csrf_field() ?>
    <input type="hidden" name="action" value="mode">
    <h2><?= e(__('Como o PageBrick se atualiza')) ?></h2>
    <div class="modes">
        <?php foreach ([
            'manual' => [__('Manual'), __('O painel avisa quando sai uma versão nova, e você clica para atualizar.')],
            'patch' => [__('Automática só para correções'), __('Versões que só corrigem problemas (1.0.1, 1.0.2…) se instalam sozinhas. Versões com novidades (1.1, 2.0) esperam o seu clique. Recomendado.')],
            'all' => [__('Automática para todas'), __('Toda versão nova se instala sozinha.')],
        ] as $value => [$label, $help]): ?>
            <label class="mode">
                <input type="radio" name="mode" value="<?= $value ?>"<?= pb_auto_update_mode() === $value ? ' checked' : '' ?>>
                <span><strong><?= e($label) ?></strong><span><?= e($help) ?></span></span>
            </label>
        <?php endforeach ?>
    </div>
    <p class="help"><?= e(__('Sozinha ou pelo botão, a proteção é a mesma: uma cópia da versão atual antes, todas as páginas conferidas depois e a volta automática se algo quebrar. Os administradores recebem um e-mail a cada atualização automática.')) ?></p>
    <p class="help"><?= e(pb_can_answer_first()
        ? __('Neste servidor, a verificação acontece depois que a página é entregue: nenhum visitante espera por ela.')
        : __('Neste servidor (sem PHP-FPM nem LiteSpeed), quando sai uma versão nova, o primeiro visitante depois da verificação espera alguns segundos enquanto ela é instalada.')) ?></p>
    <button type="submit"><?= e(__('Salvar')) ?></button>
</form>

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
