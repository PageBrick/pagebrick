<?php
// FROZEN: an agency theme built on PageBrick 0.1 (see ../../README.md). Never edit.
/** @var PbGroup $site @var PbValue $siteName @var string $content */
$color = $site->identity->color->raw() ?: '#d24e2b';
$whatsapp = pb_whatsapp_url($site->contact->whatsapp->raw(), $site->contact->whatsapp_message->raw());
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<link rel="stylesheet" href="<?= e(pb_theme_url('style.css')) ?>">
<?= pb_head(['image' => $site->identity->share_image->url()]) ?>
<style>:root { --a: <?= e($color) ?>; --b: <?= e(pb_text_color_on($color)) ?>; --c: <?= e(pb_readable_color($color)) ?>; --d: <?= e($site->agency->accent->raw()) ?>; }</style>
</head>
<body data-contrast="<?= round(pb_contrast($color, '#ffffff'), 1) ?>">
<header>
    AGENCIA-LAYOUT
    <a href="<?= e(pb_url('/')) ?>"><?= $site->identity->logo->isEmpty() ? $siteName : $site->identity->logo->img('logo', 'thumb', lazy: false) ?></a>
    <?= pb_menu_html('main', 'agencia-menu') ?>
    <?= pb_menu_html('top') ?>
    <?php if ($whatsapp !== ''): ?><a class="wa" href="<?= e($whatsapp) ?>">WhatsApp</a><?php endif ?>
    <?php if (!$site->agency->slogan->isEmpty()): ?><p class="slogan"><?= $site->agency->slogan ?></p><?php endif ?>
</header>
<main><?= $content ?></main>
<footer>
    <ul><?php foreach (pb_menu('footer') as $item): ?><li><a href="<?= e($item['url']) ?>"<?= $item['current'] ? ' aria-current="page"' : '' ?>><?= e($item['label']) ?></a></li><?php endforeach ?></ul>
    <?php if (!$site->contact->address->isEmpty()): ?><a href="<?= e(pb_map_url($site->contact->address->raw())) ?>"><?= $site->contact->address ?></a><?php endif ?>
    <?php if (!$site->contact->email->isEmpty()): ?><a href="<?= $site->contact->email ?>"><?= e($site->contact->email->raw()) ?></a><?php endif ?>
    <?php if (pb_is_logged_in()): ?><a href="<?= e(pb_absolute_url('/admin')) ?>">Painel</a><?php endif ?>
    © <?= $siteName ?>
</footer>
<?= pb_footer() ?>
</body>
</html>
