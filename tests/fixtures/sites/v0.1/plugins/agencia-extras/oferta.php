<?php
// FROZEN: an agency plugin built on PageBrick 0.1 (see ../../README.md). Never edit.
/** @var PbValue $title @var string $time */
?>
<h1><?= $title ?></h1>
<p>AGENCIA-OFERTA</p>
<form method="post" action="<?= e(pb_url('/agencia/lead')) ?>">
    <input type="hidden" name="t" value="<?= e($time) ?>">
    <input type="hidden" name="s" value="<?= e(pb_sign('lead|' . $time)) ?>">
    <input name="email" type="email">
</form>
<?php $home = pb_page_find(pb_home_page_id()); ?>
<a href="<?= e(pb_page_url($home)) ?>"><?= e($home['title']) ?></a>
<?php foreach (pb_page_list() as $p): ?><span data-slug="<?= e(pb_slugify($p['title'])) ?>"></span><?php endforeach ?>
<?= pb_sanitize_html('<p onclick="x()">limpo</p>') ?>
<a href="<?= e(pb_link_url('page:' . pb_home_page_id())) ?>">link</a>
