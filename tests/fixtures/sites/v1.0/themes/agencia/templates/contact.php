<?php
// FROZEN: an agency theme built on PageBrick 1.0 (see ../../../README.md). Never edit.
/** @var PbGroup $page @var PbGroup $site @var PbValue $title */
?>
<h1><?= $title ?></h1>
<p><?= $page->intro ?></p>
<?php if (!$site->contact->phone->isEmpty()): ?><a href="<?= $site->contact->phone ?>"><?= e($site->contact->phone->raw()) ?></a><?php endif ?>
<div class="hours"><?= $site->contact->hours ?></div>
<?= pb_slot('contact') ?>
<?= $page->body ?>
