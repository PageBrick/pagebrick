<?php
/**
 * Latest posts on the home page.
 * @var array  $posts
 * @var string $title
 */
?>
<section class="section">
    <div class="wrap">
        <div class="section-head"><h2><?= e($title) ?></h2></div>
        <?= pb_include(__DIR__ . '/cards.php', ['posts' => $posts]) ?>
        <p class="more"><a href="<?= e(pbb_url()) ?>"><?= e(__('Ver todos')) ?> →</a></p>
    </div>
</section>
