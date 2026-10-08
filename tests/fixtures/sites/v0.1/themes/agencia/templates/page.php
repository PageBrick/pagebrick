<?php
// FROZEN: an agency theme built on PageBrick 0.1 (see ../../../README.md). Never edit.
/** @var PbGroup $page @var PbValue $title */
?>
<h1><?= $title ?></h1>
<p class="intro"><?= $page->intro ?></p>
<?= $page->image->img() ?>
<div class="body"><?= $page->body ?></div>
