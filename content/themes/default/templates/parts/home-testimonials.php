<?php
/**
 * A section of the home page (templates/home.php prints them in the order the client chose in the panel).
 * @var PbGroup $page
 */
$testimonials = $page->testimonials;
?>
<?php if ($testimonials->visible() && !$testimonials->items->isEmpty()): ?>
    <section class="section section--tint">
        <div class="wrap">
            <?php if (!$testimonials->title->isEmpty()): ?><h2><?= $testimonials->title ?></h2><?php endif ?>
            <div class="quotes">
                <?php foreach ($testimonials->items as $item): ?>
                    <figure class="quote">
                        <blockquote><p><?= $item->quote ?></p></blockquote>
                        <figcaption>
                            <strong><?= $item->name ?></strong>
                            <?php if (!$item->role->isEmpty()): ?><span><?= $item->role ?></span><?php endif ?>
                        </figcaption>
                    </figure>
                <?php endforeach ?>
            </div>
        </div>
    </section>
<?php endif ?>
