<?php
// FROZEN: an agency theme built on PageBrick 0.1 (see ../../../README.md). Never edit.
/** @var PbGroup $page @var PbValue $title */
?>
<h1><?= $title ?></h1>
<p><?= $page->intro ?></p>
<?php foreach ($page->items as $item): ?>
    <article><h2><?= $item->title ?></h2><p><?= $item->text ?></p></article>
<?php endforeach ?>
<?php if ($page->cta->visible()): ?><p class="cta"><?= $page->cta->title ?></p><?php endif ?>
