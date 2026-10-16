<?php
/**
 * Piece "image_text": a photo beside a text. The HTML comes in the order the client chose (photo first when it is on the left).
 * @var PbGroup $block  title, text, image, side ('left' or 'right': where the photo goes)
 */
$left = $block->side->raw() === 'left';
$photo = $block->image->isEmpty() ? '' : '<div class="pb-block-image">' . $block->image->img(sizes: 'auto') . '</div>';
$words = ($block->title->isEmpty() ? '' : '<h2>' . $block->title . '</h2>') . ($block->text->isEmpty() ? '' : '<div class="pb-block-body">' . $block->text . '</div>');
?>
<section class="pb-block pb-block-image_text pb-side-<?= $left ? 'left' : 'right' ?>">
    <?= $left ? $photo : '' ?>
    <div class="pb-block-words"><?= $words ?></div>
    <?= $left ? '' : $photo ?>
</section>
