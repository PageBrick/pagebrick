<?php
/**
 * One post.
 * @var array   $post    with 'categories'
 * @var PbGroup $fields  summary, image, body
 * @var PbValue $title
 */
?>
<article>
    <header class="page-head">
        <div class="wrap narrow">
            <p class="blog-meta">
                <a href="<?= e(pbb_url()) ?>">← <?= e(pbb_title()) ?></a> ·
                <time datetime="<?= e($post['published_on']) ?>"><?= e(pb_date($post['published_on'])) ?></time>
                <?php foreach ($post['categories'] as $category): ?>
                    · <a href="<?= e(pbb_url(pbb_category_prefix() . '/' . $category['slug'])) ?>"><?= e($category['name']) ?></a>
                <?php endforeach ?>
            </p>
            <h1><?= $title ?></h1>
            <?php if (!$fields->summary->isEmpty()): ?><p class="lead"><?= $fields->summary ?></p><?php endif ?>
        </div>
    </header>
    <?php if (!$fields->image->isEmpty()): ?>
        <figure class="wrap page-image"><?= $fields->image->img(lazy: false) ?></figure>
    <?php endif ?>
    <div class="wrap narrow section-tight"><div class="prose"><?= $fields->body ?></div></div>
</article>
