<?php
/**
 * @var PbGroup $page   this page's fields (see theme.php)
 * @var PbValue $title
 */
$hero = $page->hero;
$about = $page->about;
$services = $page->services;
$numbers = $page->numbers;
$testimonials = $page->testimonials;
?>
<?php if ($hero->visible() && !$hero->title->isEmpty()): ?>
    <section class="hero<?= $hero->image->isEmpty() ? ' hero--text' : '' ?>">
        <div class="wrap hero-grid">
            <div class="hero-copy">
                <h1><?= $hero->title ?></h1>
                <?php if (!$hero->text->isEmpty()): ?><p class="lead"><?= $hero->text ?></p><?php endif ?>
                <?php if (!$hero->button_label->isEmpty() && $hero->button_link->url() !== ''): ?>
                    <a class="button button--large" href="<?= $hero->button_link ?>"><?= $hero->button_label ?></a>
                <?php endif ?>
            </div>
            <?php if (!$hero->image->isEmpty()): ?>
                <figure class="hero-image"><?= $hero->image->img(lazy: false) ?></figure>
            <?php endif ?>
        </div>
    </section>
<?php else: ?>
    <h1 class="visually-hidden"><?= $title ?></h1>
<?php endif ?>

<?php if ($about->visible() && (!$about->title->isEmpty() || !$about->text->isEmpty())): ?>
    <section class="section">
        <div class="wrap split<?= $about->image->isEmpty() ? ' split--single' : '' ?>">
            <div>
                <?php if (!$about->title->isEmpty()): ?><h2><?= $about->title ?></h2><?php endif ?>
                <div class="prose"><?= $about->text ?></div>
            </div>
            <?php if (!$about->image->isEmpty()): ?>
                <figure class="framed"><?= $about->image->img() ?></figure>
            <?php endif ?>
        </div>
    </section>
<?php endif ?>

<?php if ($services->visible() && !$services->items->isEmpty()): ?>
    <section class="section section--tint">
        <div class="wrap">
            <div class="section-head">
                <?php if (!$services->title->isEmpty()): ?><h2><?= $services->title ?></h2><?php endif ?>
                <?php if (!$services->intro->isEmpty()): ?><p class="lead"><?= $services->intro ?></p><?php endif ?>
            </div>
            <?= pb_include(__DIR__ . '/parts/services.php', ['items' => $services->items]) ?>
            <?php if (!$services->link_label->isEmpty() && $services->link->url() !== ''): ?>
                <p class="more"><a href="<?= $services->link ?>"><?= $services->link_label ?> →</a></p>
            <?php endif ?>
        </div>
    </section>
<?php endif ?>

<?php if ($numbers->visible() && !$numbers->items->isEmpty()): ?>
    <section class="section">
        <div class="wrap">
            <?php if (!$numbers->title->isEmpty()): ?><h2><?= $numbers->title ?></h2><?php endif ?>
            <dl class="numbers">
                <?php foreach ($numbers->items as $item): ?>
                    <div><dt><?= $item->value ?></dt><dd><?= $item->label ?></dd></div>
                <?php endforeach ?>
            </dl>
        </div>
    </section>
<?php endif ?>

<?php if ($testimonials->visible() && !$testimonials->items->isEmpty()): ?>
    <section class="section section--tint">
        <div class="wrap">
            <?php if (!$testimonials->title->isEmpty()): ?><h2><?= $testimonials->title ?></h2><?php endif ?>
            <div class="quotes">
                <?php foreach ($testimonials->items as $item): ?>
                    <figure class="quote">
                        <blockquote><p><?= $item->quote ?></p></blockquote>
                        <figcaption>
                            <strong><?= $item->name ?></strong>
                            <?php if (!$item->role->isEmpty()): ?><span><?= $item->role ?></span><?php endif ?>
                        </figcaption>
                    </figure>
                <?php endforeach ?>
            </div>
        </div>
    </section>
<?php endif ?>

<?= pb_slot('home') /* plugins can add sections here, like the latest news */ ?>

<?= pb_include(__DIR__ . '/parts/cta.php', ['cta' => $page->cta]) ?>
