<?php
/**
 * A section of the home page (templates/home.php prints them in the order the client chose in the panel).
 * @var PbGroup $page
 */
$services = $page->services;
?>
<?php if ($services->visible() && !$services->items->isEmpty()): ?>
    <section class="section section--tint">
        <div class="wrap">
            <div class="section-head">
                <?php if (!$services->title->isEmpty()): ?><h2><?= $services->title ?></h2><?php endif ?>
                <?php if (!$services->intro->isEmpty()): ?><p class="lead"><?= $services->intro ?></p><?php endif ?>
            </div>
            <?= pb_include(__DIR__ . '/services.php', ['items' => $services->items]) ?>
            <?php if (!$services->link_label->isEmpty() && $services->link->url() !== ''): ?>
                <p class="more"><a href="<?= $services->link ?>"><?= $services->link_label ?> →</a></p>
            <?php endif ?>
        </div>
    </section>
<?php endif ?>
