<?php
/**
 * Piece "cards": a list of cards, each with a title, a text, a photo and (optionally) a link.
 * @var PbGroup $block  title, intro, items (title, text, image, link), link_label, link
 */
?>
<section class="pb-block pb-block-cards">
    <?php if (!$block->title->isEmpty()): ?><h2><?= $block->title ?></h2><?php endif ?>
    <?php if (!$block->intro->isEmpty()): ?><p class="pb-block-intro"><?= $block->intro ?></p><?php endif ?>
    <?php if (!$block->items->isEmpty()): ?>
        <ul class="pb-cards">
            <?php foreach ($block->items as $item): ?>
                <li class="pb-card">
                    <?= $item->image->img() ?>
                    <?php if (!$item->title->isEmpty()): ?>
                        <h3><?= $item->link->isEmpty() ? $item->title : '<a href="' . e($item->link->url()) . '">' . $item->title . '</a>' ?></h3>
                    <?php endif ?>
                    <?php if (!$item->text->isEmpty()): ?><p><?= $item->text ?></p><?php endif ?>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
    <?php if (!$block->link->isEmpty() && !$block->link_label->isEmpty()): ?>
        <p class="pb-block-more"><a href="<?= e($block->link->url()) ?>"><?= $block->link_label ?></a></p>
    <?php endif ?>
</section>
