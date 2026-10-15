<?php
/**
 * Piece "video": a YouTube video. Only the video's id is ever used (pb_youtube_id()), and it plays from youtube-nocookie.com.
 * @var PbGroup $block  title, url, caption
 */
$video = pb_youtube_id((string) $block->url->raw());
?>
<?php if ($video !== ''): ?>
<section class="pb-block pb-block-video">
    <?php if (!$block->title->isEmpty()): ?><h2><?= $block->title ?></h2><?php endif ?>
    <figure class="pb-video">
        <iframe src="https://www.youtube-nocookie.com/embed/<?= e($video) ?>" title="<?= e($block->title->isEmpty() ? __('Vídeo') : (string) $block->title->raw()) ?>" loading="lazy" allowfullscreen
                allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin"></iframe>
        <?php if (!$block->caption->isEmpty()): ?><figcaption><?= $block->caption ?></figcaption><?php endif ?>
    </figure>
</section>
<?php endif ?>
