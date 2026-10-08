<?php
$form = function (string $slug, string $action, string $label, string $class = '', string $confirm = '') {
    return '<form method="post" action="' . e(pb_url('/admin/themes')) . '"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>'
        . pb_csrf_field() . '<input type="hidden" name="theme" value="' . e($slug) . '">'
        . '<button type="submit" name="action" value="' . e($action) . '" class="' . e($class) . '">' . e($label) . '</button></form>';
};
?>
<div class="card">
    <h1><?= e(__('Temas')) ?></h1>
    <p class="help"><?= e(__('O tema é o visual do site. Trocar de tema mantém as páginas, as fotos e os textos. Use "Pré-visualizar" para ver o site com o tema antes de ativar: só você vê.')) ?></p>
    <div class="theme-grid">
        <?php foreach ($themes as $slug => $theme):
            $state = $states[$slug] ?? [];
            $backup = pb_backups("theme_{$slug}_")[0] ?? null; ?>
            <div class="theme<?= $slug === $active ? ' is-active' : '' ?>">
                <?php if (isset($theme['screenshot'])): ?>
                    <img src="<?= e(pb_url("content/themes/$slug/{$theme['screenshot']}")) ?>" alt="" loading="lazy">
                <?php else: ?>
                    <div class="theme-noshot" aria-hidden="true"><?= e(pb_limit($theme['name'], 1)) ?></div>
                <?php endif ?>
                <h2><?= e($theme['name']) ?> <span class="muted"><?= e($theme['version']) ?></span></h2>
                <p><?= e($theme['description']) ?></p>
                <?php if (isset($theme['problem'])): ?><p class="error"><?= e($theme['problem']) ?></p><?php endif ?>
                <?php if (!empty($state['error'])): ?>
                    <p class="error"><?= e(sprintf(__('Deu erro em %s:'), pb_date($state['error_at'], true))) ?> <code><?= e($state['error']) ?></code></p>
                <?php endif ?>
                <div class="plugin-actions">
                    <?php if ($slug === $active): ?>
                        <span class="badge"><?= e(__('Ativo')) ?></span>
                        <?php if (is_file(pb_themes_dir() . "/$slug/demo.php")):
                            $demoLocale = pb_theme_demo()['locale'] ?? null; ?>
                            <?php if ($demoLocale !== null && $demoLocale !== pb_site_locale() && isset(PB_LOCALES[$demoLocale])): ?>
                                <p class="help"><?= e(sprintf(__('O conteúdo deste tema é em %1$s, e o idioma principal do site é %2$s. Ao importar, %1$s vira o idioma principal (o que os visitantes veem primeiro); o painel continua no seu idioma.'), PB_LOCALES[$demoLocale], PB_LOCALES[pb_site_locale()])) ?></p>
                                <?= $form($slug, 'import-demo-language', sprintf(__('Importar e tornar %s o idioma do site'), PB_LOCALES[$demoLocale]), 'secondary', __('Isso cria as páginas que vêm com o tema e troca os menus. Páginas com o mesmo endereço recebem o conteúdo do tema (a versão anterior fica no histórico). Continuar?')) ?>
                            <?php else: ?>
                                <?= $form($slug, 'import-demo', __('Importar o conteúdo do tema'), 'secondary', __('Isso cria as páginas que vêm com o tema e troca os menus. Páginas com o mesmo endereço recebem o conteúdo do tema (a versão anterior fica no histórico). Continuar?')) ?>
                            <?php endif ?>
                        <?php endif ?>
                    <?php elseif (!isset($theme['problem'])): ?>
                        <?= $form($slug, 'activate', __('Ativar')) ?>
                        <?= $form($slug, 'preview', __('Pré-visualizar'), 'secondary') ?>
                    <?php endif ?>
                    <?php if (isset($updates[$slug])): ?>
                        <?= $form($slug, 'update', sprintf(__('Atualizar para %s'), $updates[$slug]['version']), 'secondary') ?>
                    <?php endif ?>
                    <?php if ($backup): ?>
                        <?= $form($slug, 'restore', sprintf(__('Voltar para %s'), $backup['version']), 'link', __('Voltar para a versão anterior deste tema?')) ?>
                    <?php endif ?>
                    <?php if (!empty($state['error'])): ?>
                        <?= $form($slug, 'dismiss', __('Dispensar aviso'), 'link') ?>
                    <?php endif ?>
                    <?php if ($slug === PB_FALLBACK_THEME): ?>
                        <p class="help"><?= e(__('Este é o tema de segurança: se outro tema der erro, o site aparece com ele. Por isso não pode ser excluído.')) ?></p>
                    <?php elseif ($slug === $active): ?>
                        <p class="help"><?= e(__('Para excluir este tema, ative outro antes.')) ?></p>
                    <?php else: ?>
                        <?= $form($slug, 'delete', __('Excluir'), 'link danger', sprintf(__('Excluir o tema "%s"? Uma cópia fica guardada nos backups.'), $theme['name'])) ?>
                    <?php endif ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>
</div>

<?php $installed = array_keys($themes); $available = array_filter($catalog, fn($t) => is_array($t) && !in_array($t['slug'] ?? '', $installed, true)); ?>
<?php if ($available): ?>
<div class="card">
    <h2><?= e(__('Temas do catálogo')) ?></h2>
    <ul class="plugin-list">
        <?php foreach ($available as $entry): ?>
            <li class="plugin">
                <div><h2><?= e((string) ($entry['name'] ?? $entry['slug'])) ?> <span class="muted"><?= e((string) ($entry['version'] ?? '')) ?></span></h2><p><?= e((string) ($entry['description'] ?? '')) ?></p></div>
                <div class="plugin-actions"><?= $form((string) $entry['slug'], 'install', __('Instalar')) ?></div>
            </li>
        <?php endforeach ?>
    </ul>
</div>
<?php endif ?>

<form class="card" method="post" action="<?= e(pb_url('/admin/themes')) ?>" enctype="multipart/form-data">
    <?= pb_csrf_field() ?>
    <h2><?= e(__('Enviar tema (.zip)')) ?></h2>
    <p class="help"><?= e(__('Para temas feitos pela sua agência ou comprados de um desenvolvedor. Envie só arquivos de quem você confia. Se o tema já existir, a versão atual fica guardada.')) ?></p>
    <input type="file" name="package" accept=".zip,application/zip" required aria-label="<?= e(__('Arquivo .zip do tema')) ?>">
    <button type="submit" name="action" value="upload"><?= e(__('Enviar')) ?></button>
</form>
