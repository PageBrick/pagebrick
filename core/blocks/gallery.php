<?php
/**
 * Piece "gallery": photos with their captions.
 * @var PbGroup $block  title, items (image, caption)
 */
?>
<section class="pb-block pb-block-gallery">
    <?php if (!$block->title->isEmpty()): ?><h2><?= $block->title ?></h2><?php endif ?>
    <?php if (!$block->items->isEmpty()): ?>
        <ul class="pb-gallery">
            <?php foreach ($block->items as $item): ?>
                <?php if (!$item->image->isEmpty()): ?>
                    <li><figure>
                        <?= $item->image->img(sizes: 'auto') ?>
                        <?php if (!$item->caption->isEmpty()): ?><figcaption><?= $item->caption ?></figcaption><?php endif ?>
                    </figure></li>
                <?php endif ?>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
</section>
