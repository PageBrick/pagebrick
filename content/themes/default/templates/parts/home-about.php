<?php
/**
 * A section of the home page (templates/home.php prints them in the order the client chose in the panel).
 * @var PbGroup $page
 */
$about = $page->about;
?>
<?php if ($about->visible() && (!$about->title->isEmpty() || !$about->text->isEmpty())): ?>
    <section class="section">
        <div class="wrap split<?= $about->image->isEmpty() ? ' split--single' : '' ?>">
            <div>
                <?php if (!$about->title->isEmpty()): ?><h2><?= $about->title ?></h2><?php endif ?>
                <div class="prose"><?= $about->text ?></div>
            </div>
            <?php if (!$about->image->isEmpty()): ?>
                <figure class="framed"><?= $about->image->img() ?></figure>
            <?php endif ?>
        </div>
    </section>
<?php endif ?>
