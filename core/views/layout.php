<?php
/** @var string $content */
$user = pb_current_user();
$asset = fn(string $file) => pb_url("core/assets/$file") . '?v=' . filemtime(PB_ROOT . "/core/assets/$file");
$current = pb_request_path();
$nav = $user ? pb_admin_nav($user, $current) : null;
$brokenPlugins = $nav['broken'] ?? [];
$savedTheme = $user ? (pb_user_prefs($user)['theme'] ?? '') : ''; // kept on the server: it follows the person to any browser
?>
<!doctype html>
<html lang="<?= e(pb_locale()) ?>"<?= $savedTheme !== '' ? ' data-theme="' . e($savedTheme) . '" data-theme-saved' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(isset($title) ? "$title · PageBrick" : 'PageBrick') ?></title>
<link rel="icon" href="<?= e($asset('favicon.svg')) ?>" type="image/svg+xml">
<?php if ($user): ?><meta name="pb-csrf" content="<?= e(pb_csrf_token()) ?>">
<?php endif ?>
<script>(function () { const root = document.documentElement; root.classList.add('js'); try { <?= $user
    ? "root.hasAttribute('data-theme-saved') ? localStorage.setItem('pb-theme', root.dataset.theme) : localStorage.removeItem('pb-theme');"
    : "const t = localStorage.getItem('pb-theme'); if (t === 'light' || t === 'dark') root.dataset.theme = t;" ?> } catch (e) {} })();</script>
<?php if (!empty($editor)): ?>
<link rel="stylesheet" href="<?= e($asset('editor.css')) ?>">
<script>window.PbEditorTexts = <?= json_encode([
    'undo' => __('Desfazer'), 'redo' => __('Refazer'), 'h2' => __('Título'), 'h3' => __('Subtítulo'),
    'bold' => __('Negrito'), 'italic' => __('Itálico'), 'underline' => __('Sublinhado'), 'strike' => __('Riscado'),
    'bullets' => __('Lista'), 'numbers' => __('Lista numerada'), 'quote' => __('Citação'),
    'link' => __('Link'), 'unlink' => __('Tirar link'), 'image' => __('Imagem'), 'table' => __('Tabela'), 'rule' => __('Divisor'),
    'toolbar' => __('Barra de formatação'), 'insert' => __('Inserir'),
    'leave' => __('Esc sai do texto e vai para o próximo campo.'),
    'linkUrl' => __('Endereço do link'), 'linkBad' => __('Digite um endereço válido: https://…, e-mail ou telefone.'),
    'apply' => __('Aplicar'), 'cancel' => __('Cancelar'), 'imageAlt' => __('Descreva a imagem'), 'imageRemove' => __('Tirar imagem'),
    'tableTools' => __('Ferramentas da tabela'), 'rowAbove' => __('Linha acima'), 'rowBelow' => __('Linha abaixo'), 'rowDelete' => __('Excluir linha'),
    'colLeft' => __('Coluna à esquerda'), 'colRight' => __('Coluna à direita'), 'colDelete' => __('Excluir coluna'),
    'headerRow' => __('Linha de cabeçalho'), 'tableDelete' => __('Excluir tabela'),
    'placeholder' => __('Escreva aqui. Digite / para inserir título, lista, imagem ou tabela.'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script src="<?= e($asset('editor.js')) ?>" defer></script>
<?php endif ?>
<link rel="stylesheet" href="<?= e($asset('admin.css')) ?>">
<?php if (!empty($studio)): ?>
<link rel="stylesheet" href="<?= e($asset('studio.css')) ?>">
<script src="<?= e($asset('studio.js')) ?>" defer></script>
<?php endif ?>
<script src="<?= e($asset('admin.js')) ?>" defer></script>
</head>
<body class="<?= $user ? 'app' . (!empty($studio) ? ' studio' : '') : 'guest-page' . (!empty($narrow) ? ' auth-page' : '') ?>" data-show-password="<?= e(__('Mostrar senha')) ?>" data-hide-password="<?= e(__('Esconder senha')) ?>">
<?php if ($user):
    $siteMode = pb_site_mode();
    $roles = pb_role_labels();
    $back = $current . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
    $words = preg_split('/\s+/u', trim($user['name']), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    $initials = mb_strtoupper(mb_substr($words[0], 0, 1) . (count($words) > 1 ? mb_substr(end($words), 0, 1) : '')); ?>
<a class="skip" href="#main"><?= e(__('Ir para o conteúdo')) ?></a>
<header class="top">
    <button type="button" class="icon-btn nav-toggle" data-nav-toggle aria-controls="pb-nav" aria-expanded="false" aria-label="<?= e(__('Menu')) ?>"><?= pb_admin_icon('menu') ?></button>
    <a class="brand" href="<?= e(pb_url('/admin')) ?>" aria-label="PageBrick"></a>
    <div class="site">
        <strong><?= e(pb_option('site_title', 'PageBrick')) ?></strong>
        <a class="pill <?= $siteMode === 'live' ? 'ok' : 'warn' ?>" href="<?= e(pb_url('/admin')) ?>"><?= e(['live' => __('No ar'), 'construction' => __('Em construção'), 'maintenance' => __('Em manutenção')][$siteMode]) ?></a>
    </div>
    <div class="top-actions">
        <a class="view-site" href="<?= e(pb_url('/')) ?>" target="_blank" rel="noopener" title="<?= e(__('Ver o site')) ?>"><?= pb_admin_icon('external') ?><span><?= e(__('Ver o site')) ?></span></a>
        <?php if (pb_has_role($user, 'admin')): ?>
            <details class="menu notices">
                <summary class="icon-btn" title="<?= e(__('Avisos')) ?>" aria-label="<?= e(__('Avisos') . ($nav['notices'] ? ' (' . count($nav['notices']) . ')' : '')) ?>">
                    <?= pb_admin_icon('bell') ?><?php if ($nav['notices']): ?><span class="dot" aria-hidden="true"></span><?php endif ?>
                </summary>
                <div>
                    <strong class="menu-title"><?= e(__('Avisos')) ?></strong>
                    <?php foreach ($nav['notices'] as $notice): ?>
                        <a class="menu-item" href="<?= e(pb_url($notice['url'])) ?>"><?= e($notice['text']) ?></a>
                    <?php endforeach ?>
                    <?php if (!$nav['notices']): ?><p class="help"><?= e(__('Tudo em dia.')) ?></p><?php endif ?>
                </div>
            </details>
        <?php endif ?>
        <details class="menu account">
            <summary aria-label="<?= e(__('Minha conta') . ': ' . $user['name']) ?>">
                <span class="avatar" aria-hidden="true" data-initials="<?= e($initials) ?>"></span>
                <span class="who"><?= e($user['name']) ?></span>
                <?= pb_admin_icon('chevron-down') ?>
            </summary>
            <div>
                <p class="who-card"><strong><?= e($user['name']) ?></strong><span class="muted"><?= e($user['email']) ?></span><span class="pill"><?= e($roles[$user['role']] ?? $user['role']) ?></span></p>
                <a class="menu-item" href="<?= e(pb_url('/admin/account')) ?>"<?= $current === '/admin/account' ? ' aria-current="page"' : '' ?>><?= pb_admin_icon('user') ?><?= e(__('Minha conta')) ?></a>
                <hr>
                <div class="menu-row">
                    <span id="theme-label"><?= e(__('Tema')) ?></span>
                    <div class="seg" role="group" aria-labelledby="theme-label" data-theme-control data-url="<?= e(pb_url('/admin/preferences')) ?>">
                        <?php foreach (['light' => [__('Claro'), 'sun'], 'dark' => [__('Escuro'), 'moon'], 'system' => [__('Sistema'), 'monitor']] as $value => [$label, $icon]): ?>
                            <button type="button" data-theme-choice="<?= $value ?>" aria-pressed="<?= ($savedTheme ?: 'system') === $value ? 'true' : 'false' ?>" title="<?= e($label) ?>" aria-label="<?= e($label) ?>"><?= pb_admin_icon($icon) ?></button>
                        <?php endforeach ?>
                    </div>
                </div>
                <hr>
                <strong class="menu-title"><?= e(__('Idioma do painel')) ?></strong>
                <?php foreach (PB_LOCALES as $code => $name): ?>
                    <form method="post" action="<?= e(pb_url('/admin/panel-language')) ?>">
                        <?= pb_csrf_field() ?>
                        <input type="hidden" name="back" value="<?= e($back) ?>">
                        <button type="submit" name="locale" value="<?= e($code) ?>" class="menu-item" lang="<?= e($code) ?>"<?= $code === pb_locale() ? ' aria-current="true"' : '' ?>><?= e($name) ?></button>
                    </form>
                <?php endforeach ?>
                <p class="help"><?= e(__('Só o painel, só para você. O site não muda.')) ?></p>
                <hr>
                <form method="post" action="<?= e(pb_url('/admin/logout')) ?>">
                    <?= pb_csrf_field() ?>
                    <button type="submit" class="menu-item"><?= pb_admin_icon('log-out') ?><?= e(__('Sair')) ?></button>
                </form>
            </div>
        </details>
    </div>
</header>
<div class="shell">
    <nav class="nav" id="pb-nav" aria-label="<?= e(__('Painel')) ?>">
        <div class="rail">
            <?php foreach ($nav['sections'] as $section): ?>
                <a href="<?= e(pb_url($section['url'])) ?>" title="<?= e($section['label']) ?>" aria-label="<?= e($section['label']) ?>"<?= $section['active'] ? ' aria-current="true"' : '' ?>>
                    <?= pb_admin_icon($section['icon']) ?><?php if ($section['dot']): ?><span class="dot" aria-hidden="true"></span><?php endif ?>
                </a>
            <?php endforeach ?>
        </div>
        <div class="side">
            <?php foreach ($nav['sections'] as $section): ?>
                <section class="nav-group" data-section="<?= e($section['id']) ?>"<?= $section['open'] ? ' data-open' : '' ?> aria-labelledby="nav-<?= e($section['id']) ?>">
                    <h2 id="nav-<?= e($section['id']) ?>"><?= e($section['label']) ?></h2>
                    <ul>
                        <?php foreach ($section['items'] as $item): ?>
                            <?php if (!empty($item['divider'])): ?><li class="sep" aria-hidden="true"></li><?php continue; endif ?>
                            <li>
                                <a href="<?= e(pb_url($item['url'])) ?>"<?= $item['current'] ? ' aria-current="page"' : '' ?>>
                                    <?= pb_admin_icon($item['icon']) ?><span><?= e($item['label']) ?></span><?php if ($item['count']): ?><span class="count"><?= (int) $item['count'] ?></span><?php endif ?>
                                </a>
                            </li>
                        <?php endforeach ?>
                    </ul>
                </section>
            <?php endforeach ?>
            <p class="side-foot">PageBrick <?= e(PB_VERSION) ?></p>
        </div>
    </nav>
    <div class="scrim" data-nav-close></div>
<?php else: ?>
<?php if (!empty($narrow)): // sign-in, forgot and new password: a card in the middle of the screen ?>
<div class="auth-wrap">
<?php endif ?>
<header class="guest"><span class="brand" role="img" aria-label="PageBrick"></span></header>
<?php endif ?>
<main id="main"<?= !empty($studio) ? ' class="studio-main"' : (!empty($editor) || !empty($wide) ? ' class="wide"' : (!empty($narrow) ? ' class="narrow"' : '')) ?>>
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
<?php if ($user): ?>
</div>
<?php elseif (!empty($narrow)): ?>
    <footer class="auth-foot"><a href="<?= e(pb_url('/')) ?>"><?= pb_admin_icon('arrow-left') ?><?= e(__('Ver o site')) ?></a></footer>
</div>
<?php endif ?>
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
            <button type="button" data-media-id="<?= (int) $m['id'] ?>" data-thumb="<?= e(pb_media_url($m, 'thumb')) ?>" data-alt="<?= e($m['alt']) ?>">
                <img src="<?= e(pb_media_url($m, 'thumb')) ?>" alt="<?= e($m['alt'] ?: $m['original_name']) ?>" loading="lazy">
            </button>
        <?php endforeach ?>
    </div>
</dialog>
<?php endif ?>
</body>
</html>
