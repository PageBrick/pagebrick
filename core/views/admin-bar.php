<?php
/**
 * The bar on the site for people signed in to the panel (pb_admin_bar() in core/panel.php): a host element, its template
 * (the bar draws itself inside a shadow root, away from the theme's CSS) and the script that wakes it up.
 * @var string $mode     'live', 'construction' or 'maintenance'
 * @var string $editUrl  where this page is edited, or '' when it isn't one of the panel's pages
 * @var string $stamp    what the site looked like when this page was made
 */
$asset = fn(string $file) => pb_url("core/assets/$file") . '?v=' . filemtime(PB_ROOT . "/core/assets/$file");
$modes = ['construction' => __('Em construção'), 'maintenance' => __('Em manutenção')];
$back = __('Voltar ao painel');
$hide = __('Ocultar a barra');
$show = __('Mostrar a barra do PageBrick');
?>
<pb-bar id="pb-bar" style="display:block;height:40px" data-poll="<?= e(pb_url('/admin/live')) ?>" data-stamp="<?= e($stamp) ?>"></pb-bar>
<template id="pb-bar-template">
<style>
:host { all: initial; }
*, *::before, *::after { box-sizing: border-box; }
[hidden] { display: none !important; }
.bar { position: fixed; z-index: 2147483000; right: 0; bottom: 0; left: 0; display: flex; align-items: center; gap: .125rem; height: 40px; padding: 0 .5rem;
    background: rgb(23 25 35 / .94); -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px); border-top: 1px solid rgb(255 255 255 / .08);
    color: #e8e9ef; font: 13px/1 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
a, button { all: unset; box-sizing: border-box; display: inline-flex; align-items: center; gap: .4rem; height: 28px; padding: 0 .6rem; border-radius: 8px;
    color: inherit; font: inherit; white-space: nowrap; cursor: pointer; }
a:hover, button:hover { background: rgb(255 255 255 / .1); }
a:focus-visible, button:focus-visible { outline: 2px solid #f0714d; outline-offset: 1px; }
.brand { padding: 0 .5rem; }
.brand img { display: block; width: auto; height: 18px; }
.sep { flex: none; width: 1px; height: 16px; margin: 0 .35rem; background: rgb(255 255 255 / .16); }
.ico { flex: none; width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.chip { padding: 3px 8px; border-radius: 6px; background: rgb(252 211 77 / .16); color: #fcd34d; font-weight: 600; }
.dot { flex: none; width: 8px; height: 8px; border-radius: 50%; background: #6b7080; }
[aria-checked="true"] .dot { background: #5fd08a; box-shadow: 0 0 0 3px rgb(95 208 138 / .22); }
.msg { display: inline-flex; align-items: center; gap: .25rem; margin-left: .5rem; color: #fcd34d; }
.msg button { background: rgb(255 255 255 / .12); font-weight: 600; }
.grow { flex: 1; }
.tab { position: fixed; z-index: 2147483000; bottom: .75rem; left: .75rem; justify-content: center; width: 34px; height: 34px; padding: 0; border-radius: 50%;
    background: rgb(23 25 35 / .92); box-shadow: 0 2px 10px rgb(0 0 0 / .3); opacity: .75; }
.tab:hover { background: #171923; opacity: 1; }
.tab img { display: block; width: auto; height: 16px; }
@media (max-width: 640px) { .label { display: none; } a, button { padding: 0 .5rem; } }
@media print { .bar, .tab { display: none !important; } }
</style>
<div class="bar" role="toolbar" aria-label="PageBrick">
    <a class="brand" href="<?= e(pb_url('/admin')) ?>" title="<?= e($back) ?>" aria-label="<?= e('PageBrick: ' . $back) ?>"><img src="<?= e(pb_url('core/assets/mark-dark.svg')) ?>" alt="" width="14" height="18"></a>
    <?php if (isset($modes[$mode])): ?>
        <span class="chip" title="<?= e($mode === 'maintenance' ? __('O site está em manutenção: só quem está logado no painel vê o conteúdo.') : __('O site está em construção: só quem está logado no painel vê o conteúdo.')) ?>"><?= e($modes[$mode]) ?></span>
        <a href="<?= e(pb_url('/admin')) ?>"><?= e(__('Mudar no painel')) ?></a>
    <?php endif ?>
    <span class="sep"></span>
    <?php if ($editUrl !== ''): ?>
        <a href="<?= e($editUrl) ?>"><?= pb_admin_icon('pencil') ?><span class="label"><?= e(__('Editar página')) ?></span></a>
    <?php endif ?>
    <button type="button" data-reload title="<?= e(__('Atualizar a página')) ?>"><?= pb_admin_icon('refresh') ?><span class="label"><?= e(__('Atualizar')) ?></span></button>
    <button type="button" role="switch" aria-checked="true" data-live title="<?= e(__('A página se atualiza sozinha quando algo é salvo no painel.')) ?>"><i class="dot"></i><span class="label"><?= e(__('Ao vivo')) ?></span></button>
    <button type="button" data-phone title="<?= e(__('Ver o site numa janela do tamanho de um celular')) ?>"><?= pb_admin_icon('smartphone') ?><span class="label"><?= e(__('Celular')) ?></span></button>
    <span class="msg" role="status" hidden><span><?= e(__('O site mudou.')) ?></span><button type="button" data-reload><?= e(__('Atualizar')) ?></button></span>
    <span class="grow"></span>
    <button type="button" data-hide title="<?= e($hide) ?>" aria-label="<?= e($hide) ?>"><?= pb_admin_icon('x') ?></button>
</div>
<button type="button" class="tab" data-show hidden title="<?= e($show) ?>" aria-label="<?= e($show) ?>"><img src="<?= e(pb_url('core/assets/mark-dark.svg')) ?>" alt="" width="12" height="16"></button>
</template>
<script src="<?= e($asset('admin-bar.js')) ?>" defer></script>
