<?php /** @var array $posts published posts with 'categories' */ ?>
<ul class="blog-list">
    <?php foreach ($posts as $post): $fields = pbb_values($post); ?>
        <li>
            <a href="<?= e(pbb_post_url($post)) ?>">
                <?= $fields->image->img('blog-img', 'thumb') ?>
                <h3><?= e($post['title']) ?></h3>
            </a>
            <p class="blog-meta">
                <time datetime="<?= e($post['published_on']) ?>"><?= e(pb_date($post['published_on'])) ?></time>
                <?php foreach ($post['categories'] as $category): ?>
                    · <a href="<?= e(pbb_url(pbb_category_prefix() . '/' . $category['slug'])) ?>"><?= e($category['name']) ?></a>
                <?php endforeach ?>
            </p>
            <?php if (!$fields->summary->isEmpty()): ?><p><?= $fields->summary ?></p><?php endif ?>
        </li>
    <?php endforeach ?>
</ul>
