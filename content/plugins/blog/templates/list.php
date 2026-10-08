<?php
/**
 * The blog's main page, or a category page.
 * @var PbValue     $title
 * @var string      $intro
 * @var array       $posts      published posts with 'categories'
 * @var array|null  $category   null on the main page
 * @var array       $parents    categories above this one (for the "you are here" trail)
 * @var array       $children   subcategories that have posts
 * @var int         $current    page number
 * @var int         $pages
 * @var string      $path       address of this list
 */
?>
<header class="page-head">
    <div class="wrap"><?php /* full width, lined up with the grid of posts below */ ?>
        <?php if ($category): ?>
            <p class="blog-trail">
                <a href="<?= e(pbb_url()) ?>"><?= e(pbb_title()) ?></a>
                <?php foreach ($parents as $parent): ?> › <a href="<?= e(pbb_url(pbb_category_prefix() . '/' . $parent['slug'])) ?>"><?= e($parent['name']) ?></a><?php endforeach ?>
            </p>
        <?php endif ?>
        <h1><?= $title ?></h1>
        <?php if ($intro !== ''): ?><p class="lead"><?= e($intro) ?></p><?php endif ?>
        <?php if ($children): ?>
            <ul class="blog-categories">
                <?php foreach ($children as $child): ?>
                    <li><a href="<?= e(pbb_url(pbb_category_prefix() . '/' . $child['slug'])) ?>"><?= e($child['name']) ?></a></li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </div>
</header>
<section class="section-tight">
    <div class="wrap">
        <?php if (!$posts): ?>
            <p class="lead"><?= e(__('Nenhum texto publicado ainda.')) ?></p>
        <?php endif ?>
        <?= pb_include(__DIR__ . '/cards.php', ['posts' => $posts]) ?>
        <?php if ($pages > 1): ?>
            <nav class="blog-pages" aria-label="<?= e(__('Páginas')) ?>">
                <?php if ($current > 1): ?><a href="<?= e(pb_url($path . '?pagina=' . ($current - 1))) ?>">← <?= e(__('Mais recentes')) ?></a><?php endif ?>
                <span><?= e(sprintf(__('%d de %d'), $current, $pages)) ?></span>
                <?php if ($current < $pages): ?><a href="<?= e(pb_url($path . '?pagina=' . ($current + 1))) ?>"><?= e(__('Mais antigos')) ?> →</a><?php endif ?>
            </nav>
        <?php endif ?>
    </div>
</section>
