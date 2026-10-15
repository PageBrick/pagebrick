<?php
/**
 * Piece "numbers": figures that show experience.
 * @var PbGroup $block  title, items (value, label)
 */
?>
<section class="pb-block pb-block-numbers">
    <?php if (!$block->title->isEmpty()): ?><h2><?= $block->title ?></h2><?php endif ?>
    <?php if (!$block->items->isEmpty()): ?>
        <ul class="pb-numbers">
            <?php foreach ($block->items as $item): ?>
                <li class="pb-number"><strong><?= $item->value ?></strong> <span><?= $item->label ?></span></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</section>
