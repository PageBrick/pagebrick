<?php
/**
 * Piece "cta" (call to action): an invitation with a button.
 * @var PbGroup $block  title, text, button_label, button_link
 */
?>
<section class="pb-block pb-block-cta">
    <?php if (!$block->title->isEmpty()): ?><h2><?= $block->title ?></h2><?php endif ?>
    <?php if (!$block->text->isEmpty()): ?><p><?= $block->text ?></p><?php endif ?>
    <?php if (!$block->button_label->isEmpty() && !$block->button_link->isEmpty()): ?>
        <p><a class="pb-button" href="<?= e($block->button_link->url()) ?>"><?= $block->button_label ?></a></p>
    <?php endif ?>
</section>
