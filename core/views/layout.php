<?php
/** @var string $content */
$user = pb_current_user();
$asset = fn(string $file) => pb_url("core/assets/$file") . '?v=' . filemtime(PB_ROOT . "/core/assets/$file");
$current = pb_request_path();
$nav = [
    '/admin/pages' => __('Páginas'),
    '/admin/media' => __('Mídia'),
    '/admin/menus' => __('Menus'),
    '/admin/settings' => __('Aparência e contato'),
];
// What plugins add to the panel lives under its own icon: their screens (for whoever the plugin allows) and the
// settings screens of the active ones. Installing, switching on and off and deleting is for administrators.
$pluginNames = $user ? pb_plugins_available() : [];
$pluginScreens = [];
foreach ($GLOBALS['pb_admin_pages'] ?? [] as $slug => $page) {
    if ($user && pb_has_role($user, $page['role'])) {
        $pluginScreens["/admin/p/$slug"] = $page['label'];
    }
}
$pluginConfig = [];
foreach (array_keys($GLOBALS['pb_plugin_settings'] ?? []) as $slug) {
    if ($user && isset($pluginNames[$slug])) {
        $pluginConfig["/admin/plugins/settings?plugin=$slug"] = sprintf(__('Configurar %s'), $pluginNames[$slug]['name']);
    }
}
$brokenPlugins = [];
$system = []; // technical screens, rarely touched: the gear menu (administrators only)
if ($user && pb_has_role($user, 'admin')) {
    $system = [
        '/admin/general' => __('Endereço e idiomas'),
        '/admin/themes' => __('Temas'),
        '/admin/updates' => __('Atualizações'),
        '/admin/email' => __('E-mail'),
        '/admin/users' => __('Usuários'),
    ];
    foreach (pb_plugin_states() as $slug => $state) {
        if (!empty($state['error'])) {
            $brokenPlugins[] = $pluginNames[$slug]['name'] ?? $slug;
        }
    }
}
?>
<?php $savedTheme = $user ? (pb_user_prefs($user)['theme'] ?? '') : ''; // kept on the server: it follows the person to any browser ?>
<!doctype html>
<html lang="<?= e(pb_locale()) ?>"<?= $savedTheme !== '' ? ' data-theme="' . e($savedTheme) . '" data-theme-saved' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(isset($title) ? "$title · PageBrick" : 'PageBrick') ?></title>
<link rel="icon" href="<?= e($asset('favicon.svg')) ?>" type="image/svg+xml">
<?php if ($user): ?><meta name="pb-csrf" content="<?= e(pb_csrf_token()) ?>">
<?php endif ?>
<script>try { const root = document.documentElement, t = localStorage.getItem('pb-theme'); if (root.hasAttribute('data-theme-saved')) localStorage.setItem('pb-theme', root.dataset.theme); else if (t) root.dataset.theme = t; } catch (e) {}</script>
<?php if (!empty($editor)): ?>
<link rel="stylesheet" href="<?= e($asset('trix.css')) ?>">
<script src="<?= e($asset('trix.js')) ?>"></script>
<?php if (pb_locale() !== 'en'): // the editor's buttons come in English ?>
<script>Object.assign(Trix.config.lang, <?= json_encode([
    'bold' => __('Negrito'), 'italic' => __('Itálico'), 'strike' => __('Riscado'), 'link' => __('Link'), 'unlink' => __('Tirar link'),
    'heading1' => __('Título'), 'quote' => __('Citação'), 'code' => __('Código'), 'bullets' => __('Lista'), 'numbers' => __('Lista numerada'),
    'outdent' => __('Diminuir recuo'), 'indent' => __('Aumentar recuo'), 'undo' => __('Desfazer'), 'redo' => __('Refazer'),
    'urlPlaceholder' => __('Digite um endereço…'), 'remove' => __('Remover'), 'attachFiles' => __('Anexar arquivos'), 'captionPlaceholder' => __('Legenda…'),
], JSON_UNESCAPED_UNICODE) ?>);</script>
<?php endif ?>
<?php endif ?>
<link rel="stylesheet" href="<?= e($asset('admin.css')) ?>">
<script src="<?= e($asset('admin.js')) ?>" defer></script>
</head>
<body data-show-password="<?= e(__('Mostrar senha')) ?>" data-hide-password="<?= e(__('Esconder senha')) ?>">
<?php if ($user): ?>
<header class="top">
    <a class="brand" href="<?= e(pb_url('/admin')) ?>">PageBrick</a>
    <nav aria-label="<?= e(__('Painel')) ?>">
        <?php foreach ($nav as $path => $label): ?>
            <a href="<?= e(pb_url($path)) ?>"<?= str_starts_with($current, $path) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach ?>
    </nav>
    <?php if ($pluginScreens || $pluginConfig || pb_has_role($user, 'admin')):
        $inPlugins = array_filter(array_keys($pluginScreens), fn($path) => str_starts_with($current, $path)) || str_starts_with($current, '/admin/plugins'); ?>
        <details class="nav-more settings-menu plugins-menu">
            <summary title="<?= e(__('Plugins')) ?>" aria-label="<?= e(__('Plugins')) ?>"<?= $inPlugins ? ' aria-current="page"' : '' ?>>
                <?php if ($brokenPlugins): ?><span class="dot" aria-hidden="true"></span><?php endif ?>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22v-5"/><path d="M9 8V2"/><path d="M15 8V2"/><path d="M18 8v5a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4V8Z"/></svg>
            </summary>
            <div>
                <strong><?= e(__('Plugins')) ?></strong>
                <?php foreach ($pluginScreens as $path => $label): ?>
                    <a href="<?= e(pb_url($path)) ?>"<?= str_starts_with($current, $path) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                <?php endforeach ?>
                <?php if ($pluginConfig): ?>
                    <hr>
                    <?php foreach ($pluginConfig as $path => $label): ?>
                        <a href="<?= e(pb_url($path)) ?>"><?= e($label) ?></a>
                    <?php endforeach ?>
                <?php endif ?>
                <?php if (pb_has_role($user, 'admin')): ?>
                    <hr>
                    <a href="<?= e(pb_url('/admin/plugins')) ?>"<?= $current === '/admin/plugins' ? ' aria-current="page"' : '' ?>><?= e(__('Gerenciar plugins')) ?></a>
                <?php endif ?>
            </div>
        </details>
    <?php endif ?>
    <?php if ($user):
        $back = $current . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''); ?>
        <details class="nav-more settings-menu">
            <summary title="<?= e(__('Idioma do painel')) ?>" aria-label="<?= e(__('Idioma do painel') . ': ' . PB_LOCALES[pb_locale()]) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/></svg>
            </summary>
            <div>
                <strong><?= e(__('Idioma do painel')) ?></strong>
                <?php foreach (PB_LOCALES as $code => $name): ?>
                    <form method="post" action="<?= e(pb_url('/admin/panel-language')) ?>">
                        <?= pb_csrf_field() ?>
                        <input type="hidden" name="back" value="<?= e($back) ?>">
                        <button type="submit" name="locale" value="<?= e($code) ?>" class="menu-item" lang="<?= e($code) ?>"<?= $code === pb_locale() ? ' aria-current="true"' : '' ?>><?= e($name) ?></button>
                    </form>
                <?php endforeach ?>
                <p class="help"><?= e(__('Só o painel, só para você. O site não muda.')) ?></p>
            </div>
        </details>
    <?php endif ?>
    <?php if ($system):
        $inSystem = array_filter(array_keys($system), fn($path) => str_starts_with($current, $path));
        $available = pb_available_updates(); // from the saved copy of the catalog: no network here
        $updateCount = ($available['core'] ? 1 : 0) + count($available['plugin']) + count($available['theme']);
        $gearLabel = $updateCount ? __('Configurações (há atualizações)') : __('Configurações'); ?>
        <details class="nav-more settings-menu">
            <summary title="<?= e($gearLabel) ?>" aria-label="<?= e($gearLabel) ?>"<?= $inSystem ? ' aria-current="page"' : '' ?>>
                <?php if ($updateCount): ?><span class="dot" aria-hidden="true"></span><?php endif ?>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
            </summary>
            <div>
                <strong><?= e(__('Configurações')) ?></strong>
                <?php foreach ($system as $path => $label): ?>
                    <a href="<?= e(pb_url($path)) ?>"<?= str_starts_with($current, $path) ? ' aria-current="page"' : '' ?>><?= e($label) ?><?php if ($path === '/admin/updates' && $updateCount): ?> <span class="count"><?= $updateCount ?></span><?php endif ?></a>
                <?php endforeach ?>
            </div>
        </details>
    <?php endif ?>
    <button type="button" class="theme-switch" data-theme-switch data-url="<?= e(pb_url('/admin/preferences')) ?>" title="<?= e(__('Alternar entre claro e escuro')) ?>" aria-label="<?= e(__('Alternar entre claro e escuro')) ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor"/></svg>
    </button>
    <a href="<?= e(pb_url('/')) ?>" target="_blank"><?= e(__('Ver o site')) ?></a>
    <form method="post" action="<?= e(pb_url('/admin/logout')) ?>">
        <?= pb_csrf_field() ?>
        <a href="<?= e(pb_url('/admin/account')) ?>" title="<?= e(__('Minha conta')) ?>"><?= e($user['name']) ?></a>
        <button type="submit" class="link"><?= e(__('Sair')) ?></button>
    </form>
</header>
<?php else: ?>
<header class="guest"><span class="brand" role="img" aria-label="PageBrick"></span></header>
<?php endif ?>
<main<?= !empty($editor) ? ' class="wide"' : (!empty($narrow) ? ' class="narrow"' : '') ?>>
    <?php if ($user && pb_safe_mode()): ?>
        <form class="flash error" method="post" action="<?= e(pb_url('/admin/safe-mode/exit')) ?>">
            <?= pb_csrf_field() ?>
            <strong><?= e(__('Modo de segurança:')) ?></strong> <?= e(__('nenhum plugin está rodando para você agora.')) ?>
            <?php if (empty($GLOBALS['pb_config']['safe_mode'])): ?><button type="submit" class="link"><?= e(__('Sair do modo de segurança')) ?></button><?php endif ?>
        </form>
    <?php endif ?>
    <?php if ($user && ($mode = pb_site_mode()) !== 'live' && $current !== '/admin'): ?>
        <p class="flash error">
            <?= e($mode === 'maintenance' ? __('O site está em manutenção: visitantes veem só o aviso.') : __('O site está em construção: visitantes veem só o aviso.')) ?>
            <a href="<?= e(pb_url('/admin')) ?>"><?= e(__('Mudar')) ?></a>
        </p>
    <?php endif ?>
    <?php if ($brokenPlugins && !str_starts_with($current, '/admin/plugins')): ?>
        <p class="flash error" role="alert">
            <?= e(sprintf(__('Plugin desligado automaticamente por erro: %s.'), implode(', ', $brokenPlugins))) ?>
            <a href="<?= e(pb_url('/admin/plugins')) ?>"><?= e(__('Ver detalhes')) ?></a>
        </p>
    <?php endif ?>
    <?php foreach (pb_take_flashes() as [$type, $message]): ?>
        <p class="flash <?= e($type) ?>" role="status"><?= e($message) ?></p>
    <?php endforeach ?>
    <?= $content ?>
</main>
<?php if (!empty($editor)): ?>
<dialog id="pb-media" aria-labelledby="pb-media-title">
    <div class="dialog-head">
        <h2 id="pb-media-title"><?= e(__('Escolha uma imagem')) ?></h2>
        <button type="button" class="link" data-close><?= e(__('Fechar')) ?></button>
    </div>
    <label class="upload">
        <?= e(__('Enviar imagem nova')) ?>
        <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple data-upload
               data-url="<?= e(pb_url('/admin/media')) ?>" data-csrf="<?= e(pb_csrf_token()) ?>" data-sending="<?= e(__('Enviando…')) ?>">
    </label>
    <p class="muted" data-status role="status"></p>
    <div class="media-grid" data-grid>
        <?php foreach (pb_media_list(true) as $m): ?>
            <button type="button" data-media-id="<?= (int) $m['id'] ?>" data-thumb="<?= e(pb_media_url($m, 'thumb')) ?>">
                <img src="<?= e(pb_media_url($m, 'thumb')) ?>" alt="<?= e($m['alt'] ?: $m['original_name']) ?>" loading="lazy">
            </button>
        <?php endforeach ?>
    </div>
</dialog>
<?php endif ?>
</body>
</html>
