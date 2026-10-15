<?php
/**
 * Piece "faq": questions that open to show the answer (the browser's own <details>, no script).
 * @var PbGroup $block  title, items (question, answer)
 */
?>
<section class="pb-block pb-block-faq">
    <?php if (!$block->title->isEmpty()): ?><h2><?= $block->title ?></h2><?php endif ?>
    <?php foreach ($block->items as $item): ?>
        <details class="pb-faq-item">
            <summary><?= $item->question ?></summary>
            <div class="pb-block-body"><?= $item->answer ?></div>
        </details>
    <?php endforeach ?>
</section>
