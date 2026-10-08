<?php
// FROZEN: an agency theme built on PageBrick 1.0 (see ../../../README.md). Never edit.
/** @var PbGroup $page @var PbValue $title */
?>
<h1><?= $title ?></h1>
<p class="offer">OFERTA: <?= $page->offer ?> até <?= $page->deadline ?></p>
<ul>
    <?php foreach ($page->perks as $perk): ?><li><?= $perk->icon->img() ?><?= $perk->title ?></li><?php endforeach ?>
</ul>
<?php if ($page->cta->visible() && !$page->cta->label->isEmpty()): ?><a class="cta" href="<?= $page->cta->link ?>"><?= $page->cta->label ?></a><?php endif ?>
