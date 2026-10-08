<?php
$form = function (string $slug, string $action, string $label, string $class = '', string $confirm = '', string $version = '') {
    // With a version: installed in steps, each shown as it happens (admin.js, POST /admin/packages/step).
    $steps = $version !== '' ? ' data-package-steps data-type="plugin" data-slug="' . e($slug) . '" data-version="' . e($version) . '"' : '';
    return '<form method="post" action="' . e(pb_url('/admin/plugins')) . '"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . $steps . '>'
        . pb_csrf_field() . '<input type="hidden" name="plugin" value="' . e($slug) . '">'
        . '<button type="submit" name="action" value="' . e($action) . '" class="' . e($class) . '">' . e($label) . '</button></form>';
};
?>
<div class="card">
    <h1><?= e(__('Plugins')) ?></h1>
    <p class="help"><?= e(__('Plugins acrescentam recursos ao site. Se um plugin der erro, o PageBrick desliga só ele e o resto do site continua funcionando.')) ?></p>
    <?php if (!$plugins): ?>
        <p class="muted"><?= e(__('Nenhum plugin instalado.')) ?></p>
    <?php endif ?>
    <ul class="plugin-list">
        <?php foreach ($plugins as $slug => $plugin):
            $state = $states[$slug] ?? [];
            $on = !empty($state['active']);
            $backup = pb_backups("plugin_{$slug}_")[0] ?? null; ?>
            <li class="plugin<?= $on ? ' is-active' : '' ?>">
                <div>
                    <h2><?= e($plugin['name']) ?> <span class="muted"><?= e($plugin['version']) ?></span></h2>
                    <p><?= e($plugin['description']) ?></p>
                    <?php if ($plugin['author'] !== ''): ?><p class="muted"><?= e(sprintf(__('Por %s'), $plugin['author'])) ?></p><?php endif ?>
                    <?php if (isset($plugin['problem'])): ?><p class="error"><?= e($plugin['problem']) ?></p><?php endif ?>
                    <?php if (!empty($state['error'])): ?>
                        <p class="error"><?= e(sprintf(__('Desligado automaticamente em %s porque deu erro:'), pb_date($state['error_at'], true))) ?> <code><?= e($state['error']) ?></code></p>
                    <?php endif ?>
                </div>
                <div class="plugin-actions">
                    <span class="badge"><?= e($on ? __('Ativo') : __('Inativo')) ?></span>
                    <?php if ($on && !pb_safe_mode() && in_array($slug, $withSettings, true)): ?>
                        <a href="<?= e(pb_url('/admin/plugins/settings?plugin=' . rawurlencode($slug))) ?>"><?= e(__('Configurar')) ?></a>
                    <?php endif ?>
                    <?php if (!empty($plugin['missing'])): ?>
                        <?= $form($slug, 'dismiss', __('Remover da lista'), 'link') ?>
                    <?php else: ?>
                        <?php if ($on): ?>
                            <?= $form($slug, 'deactivate', __('Desativar'), 'secondary') ?>
                        <?php elseif (!isset($plugin['problem'])): ?>
                            <?= $form($slug, 'activate', __('Ativar')) ?>
                        <?php endif ?>
                        <?php if (isset($updates[$slug])): ?>
                            <?= $form($slug, 'update', sprintf(__('Atualizar para %s'), $updates[$slug]['version']), 'secondary', '', (string) $updates[$slug]['version']) ?>
                        <?php endif ?>
                        <?php if ($backup): ?>
                            <?= $form($slug, 'restore', sprintf(__('Voltar para %s'), $backup['version']), 'link', __('Voltar para a versão anterior deste plugin?')) ?>
                        <?php endif ?>
                        <?php if (!empty($state['error'])): ?>
                            <?= $form($slug, 'dismiss', __('Dispensar aviso'), 'link') ?>
                        <?php endif ?>
                        <?php if (!$on): ?>
                            <?= $form($slug, 'delete', __('Excluir'), 'link danger', sprintf(__('Excluir o plugin "%s"? Uma cópia fica guardada nos backups.'), $plugin['name'])) ?>
                        <?php endif ?>
                    <?php endif ?>
                </div>
            </li>
        <?php endforeach ?>
    </ul>
</div>

<div class="card">
    <h2><?= e(__('Loja de plugins')) ?></h2>
    <p class="help"><?= e(__('Plugins publicados no catálogo oficial do PageBrick. A assinatura digital de cada pacote é conferida antes de instalar, para garantir que ele não foi alterado no caminho.')) ?></p>
    <?php if ($catalogError): ?>
        <p class="error"><?= e(sprintf(__('Não consegui consultar a loja: %s'), $catalogError)) ?></p>
    <?php endif ?>
    <?php $available = array_filter($catalog, fn($p) => is_array($p) && !isset($plugins[$p['slug'] ?? ''])); ?>
    <?php if (!$available && !$catalogError): ?>
        <p class="muted"><?= e(__('Você já tem todos os plugins da loja.')) ?></p>
    <?php endif ?>
    <ul class="plugin-list">
        <?php foreach ($available as $entry): ?>
            <li class="plugin">
                <div>
                    <h2><?= e((string) ($entry['name'] ?? $entry['slug'])) ?> <span class="muted"><?= e((string) ($entry['version'] ?? '')) ?></span></h2>
                    <p><?= e((string) ($entry['description'] ?? '')) ?></p>
                    <?php if (!empty($entry['author'])): ?><p class="muted"><?= e(sprintf(__('Por %s'), $entry['author'])) ?></p><?php endif ?>
                </div>
                <div class="plugin-actions">
                    <?php if (!empty($entry['price'])): ?>
                        <span class="badge"><?= e((string) $entry['price']) ?></span>
                        <?php if (!empty($entry['homepage'])): ?><a href="<?= e(pb_clean_url((string) $entry['homepage'])) ?>" target="_blank" rel="noopener"><?= e(__('Comprar no site do autor')) ?></a><?php endif ?>
                    <?php else: ?>
                        <?= $form((string) $entry['slug'], 'install', __('Instalar'), '', '', (string) ($entry['version'] ?? '')) ?>
                    <?php endif ?>
                </div>
            </li>
        <?php endforeach ?>
    </ul>
    <?= $form('', 'refresh', __('Verificar a loja de novo'), 'link') ?>
</div>

<form class="card" method="post" action="<?= e(pb_url('/admin/plugins')) ?>" enctype="multipart/form-data" data-package-steps data-type="plugin">
    <?= pb_csrf_field() ?>
    <h2><?= e(__('Enviar plugin (.zip)')) ?></h2>
    <p class="help"><?= e(__('Para plugins feitos pela sua agência ou comprados de um desenvolvedor. Envie só arquivos de quem você confia: plugins enviados por .zip não vêm do catálogo oficial e não têm assinatura conferida. Se o plugin já existir, a versão atual fica guardada.')) ?></p>
    <input type="file" name="package" accept=".zip,application/zip" required aria-label="<?= e(__('Arquivo .zip do plugin')) ?>">
    <button type="submit" name="action" value="upload"><?= e(__('Enviar')) ?></button>
</form>

<details class="card">
    <summary><?= e(__('Link de socorro')) ?></summary>
    <p class="help"><?= e(__('Se o painel parar de abrir por causa de um plugin, este link entra com todos os plugins desligados. Guarde em um lugar seguro: quem tiver o link consegue usar o modo de segurança.')) ?></p>
    <p><input readonly aria-label="<?= e(__('Link de socorro')) ?>" value="<?= e($recoveryUrl) ?>"></p>
    <p class="help"><?= e(__('Sem acesso ao painel? Coloque \'safe_mode\' => true no arquivo config.php pelo gerenciador de arquivos da hospedagem.')) ?></p>
</details>

<script type="application/json" id="pb-step-texts"><?= json_encode(pb_package_step_texts(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
