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
foreach ($GLOBALS['pb_admin_pages'] ?? [] as $slug => $page) {
    if ($user && pb_has_role($user, $page['role'])) {
        $nav["/admin/p/$slug"] = $page['label'];
    }
}
$brokenPlugins = [];
$system = []; // technical screens, grouped so the menu stays on one line (administrators only)
if ($user && pb_has_role($user, 'admin')) {
    $system = [
        '/admin/themes' => __('Temas'),
        '/admin/plugins' => __('Plugins'),
        '/admin/updates' => __('Atualizações'),
        '/admin/email' => __('E-mail'),
        '/admin/users' => __('Usuários'),
    ];
    $available = pb_plugins_available();
    foreach (pb_plugin_states() as $slug => $state) {
        if (!empty($state['error'])) {
            $brokenPlugins[] = $available[$slug]['name'] ?? $slug;
        }
    }
}
?>
<!doctype html>
<html lang="<?= e(pb_locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(isset($title) ? "$title · PageBrick" : 'PageBrick') ?></title>
<link rel="icon" href="<?= e($asset('favicon.svg')) ?>" type="image/svg+xml">
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
<body>
<?php if ($user): ?>
<header class="top">
    <a class="brand" href="<?= e(pb_url('/admin')) ?>">PageBrick</a>
    <nav aria-label="<?= e(__('Painel')) ?>">
        <?php foreach ($nav as $path => $label): ?>
            <a href="<?= e(pb_url($path)) ?>"<?= str_starts_with($current, $path) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach ?>
        <?php if ($system): $inSystem = array_filter(array_keys($system), fn($path) => str_starts_with($current, $path)); ?>
            <details class="nav-more">
                <summary<?= $inSystem ? ' aria-current="page"' : '' ?>><?= e(__('Sistema')) ?></summary>
                <div>
                    <?php foreach ($system as $path => $label): ?>
                        <a href="<?= e(pb_url($path)) ?>"<?= str_starts_with($current, $path) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
                    <?php endforeach ?>
                </div>
            </details>
        <?php endif ?>
    </nav>
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
<main<?= !empty($editor) ? ' class="wide"' : '' ?>>
    <?php if ($user && pb_safe_mode()): ?>
        <form class="flash error" method="post" action="<?= e(pb_url('/admin/safe-mode/exit')) ?>">
            <?= pb_csrf_field() ?>
            <strong><?= e(__('Modo de segurança:')) ?></strong> <?= e(__('nenhum plugin está rodando para você agora.')) ?>
            <?php if (empty($GLOBALS['pb_config']['safe_mode'])): ?><button type="submit" class="link"><?= e(__('Sair do modo de segurança')) ?></button><?php endif ?>
        </form>
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
