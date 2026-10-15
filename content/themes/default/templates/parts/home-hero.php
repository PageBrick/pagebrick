<?php
/**
 * A section of the home page (templates/home.php prints them in the order the client chose in the panel).
 * @var PbGroup $page
 * @var PbValue $title
 */
$hero = $page->hero;
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
