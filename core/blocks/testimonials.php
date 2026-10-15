<?php
/**
 * Piece "testimonials": what clients say.
 * @var PbGroup $block  title, items (quote, name, role)
 */
?>
<section class="pb-block pb-block-testimonials">
    <?php if (!$block->title->isEmpty()): ?><h2><?= $block->title ?></h2><?php endif ?>
    <?php foreach ($block->items as $item): ?>
        <figure class="pb-quote">
            <blockquote><p><?= $item->quote ?></p></blockquote>
            <?php if (!$item->name->isEmpty() || !$item->role->isEmpty()): ?>
                <figcaption>
                    <?php if (!$item->name->isEmpty()): ?><strong><?= $item->name ?></strong><?php endif ?>
                    <?php if (!$item->role->isEmpty()): ?><span><?= $item->role ?></span><?php endif ?>
                </figcaption>
            <?php endif ?>
        </figure>
    <?php endforeach ?>
</section>
