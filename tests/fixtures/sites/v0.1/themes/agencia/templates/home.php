<?php
// FROZEN: an agency theme built on PageBrick 0.1 (see ../../../README.md). Never edit.
/** @var PbGroup $page @var PbValue $title @var bool $isHome */
$hero = $page->hero;
?>
<?php if ($hero->visible() && !$hero->title->isEmpty()): ?>
    <section class="hero" data-home="<?= $isHome ? 'yes' : 'no' ?>">
        <h1><?= $hero->title ?></h1>
        <p><?= $hero->text ?></p>
        <?php if ($hero->button_link->url() !== ''): ?><a href="<?= $hero->button_link ?>"><?= $hero->button_label ?></a><?php endif ?>
        <?php if (!$hero->image->isEmpty()): ?>
            <?= $hero->image->img('hero-img', 'full', lazy: false) ?>
            <link rel="preload" as="image" href="<?= e($hero->image->url('thumb')) ?>">
        <?php endif ?>
        <?php if (!$page->video->isEmpty()): ?><a class="video" href="<?= $page->video ?>">Assista</a><?php endif ?>
    </section>
<?php else: ?>
    <h1><?= $title ?></h1>
<?php endif ?>

<?php if ($page->about->visible()): ?>
    <section><h2><?= $page->about->title ?></h2><?= $page->about->text ?><?= $page->about->image->img() ?></section>
<?php endif ?>

<?php if ($page->services->visible() && count($page->services->items) > 0): ?>
    <section>
        <h2><?= $page->services->title ?> (<?= count($page->services->items) ?>)</h2>
        <?php foreach ($page->services->items as $i => $item): ?>
            <article data-i="<?= $i ?>"><h3><?= $item->title ?></h3><p><?= $item->text ?></p><?= $item->image->img('', 'thumb') ?></article>
        <?php endforeach ?>
    </section>
<?php endif ?>

<?php foreach (['numbers', 'testimonials'] as $section): ?>
    <?php if ($page->$section->visible() && !$page->$section->items->isEmpty()): ?>
        <section class="<?= $section ?>"><?= $page->$section->title ?></section>
    <?php endif ?>
<?php endforeach ?>

<?= pb_slot('home') ?>

<?php if ($page->cta->visible()): ?>
    <section class="cta"><?= $page->cta->title ?> <a href="<?= $page->cta->button_link ?>"><?= $page->cta->button_label ?></a> <?= e($page->cta->button_link->raw()) ?></section>
<?php endif ?>
