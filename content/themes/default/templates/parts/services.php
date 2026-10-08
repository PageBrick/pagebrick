<?php /** @var PbList $items */ ?>
<ol class="services">
    <?php foreach ($items as $i => $item): ?>
        <li>
            <?php if (!$item->image->isEmpty()): ?><?= $item->image->img('service-img') ?><?php endif ?>
            <span class="service-num" aria-hidden="true"><?= sprintf('%02d', $i + 1) ?></span>
            <h3><?= $item->title ?></h3>
            <?php if (!$item->text->isEmpty()): ?><p><?= $item->text ?></p><?php endif ?>
        </li>
    <?php endforeach ?>
</ol>
