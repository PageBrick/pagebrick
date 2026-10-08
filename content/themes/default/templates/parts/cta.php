<?php /** @var PbGroup $cta */ ?>
<?php if ($cta->visible() && !$cta->title->isEmpty()): ?>
    <section class="cta">
        <div class="wrap cta-row">
            <div>
                <h2><?= $cta->title ?></h2>
                <?php if (!$cta->text->isEmpty()): ?><p><?= $cta->text ?></p><?php endif ?>
            </div>
            <?php if (!$cta->button_label->isEmpty() && $cta->button_link->url() !== ''): ?>
                <a class="button button--large button--inverse" href="<?= $cta->button_link ?>"><?= $cta->button_label ?></a>
            <?php endif ?>
        </div>
    </section>
<?php endif ?>
