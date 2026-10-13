<?php
/**
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
<?php if (!$page->image->isEmpty()): ?>
    <figure class="wrap page-image"><?= $page->image->img(lazy: false) ?></figure>
<?php endif ?>
<?php if (!$page->body->isEmpty()): ?>
    <div class="wrap narrow section-tight"><div class="prose"><?= $page->body ?></div></div>
<?php endif ?>
