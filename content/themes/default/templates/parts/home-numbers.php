<?php
/**
 * A section of the home page (templates/home.php prints them in the order the client chose in the panel).
 * @var PbGroup $page
 */
$numbers = $page->numbers;
?>
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
