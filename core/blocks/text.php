<?php
/**
 * Piece "text": plain HTML, no CSS, on purpose: the theme draws it. To change it, copy this file to templates/blocks/text.php in the theme.
 * @var PbGroup $block  the piece's fields: title, text
 * @var string  $type   'text'
 * @var string  $id     the piece's name on the page
 * @var PbGroup $page   the page's content
 */
?>
<section class="pb-block pb-block-text">
    <?php if (!$block->title->isEmpty()): ?><h2><?= $block->title ?></h2><?php endif ?>
    <?php if (!$block->text->isEmpty()): ?><div class="pb-block-body"><?= $block->text ?></div><?php endif ?>
</section>
