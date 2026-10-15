<?php
/**
 * The services page's own content: title, introduction and the list of services.
 * @var PbGroup $page
 * @var PbValue $title
 */
?>
<header class="page-head">
    <div class="wrap narrow">
        <h1><?= $title ?></h1>
        <?php if (!$page->intro->isEmpty()): ?><p class="lead"><?= $page->intro ?></p><?php endif ?>
    </div>
</header>
<?php if (!$page->items->isEmpty()): ?>
    <section class="section-tight">
        <div class="wrap"><?= pb_include(__DIR__ . '/services.php', ['items' => $page->items]) ?></div>
    </section>
<?php endif ?>
