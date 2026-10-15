<?php
/**
 * The services page: its own content (title, introduction, the list), the closing call and the pieces the client added, in the order chosen in the panel.
 *
 * @var PbGroup $page
 * @var PbValue $title
 */
?>
<?= pb_sections($page, ['content', 'cta'], fn($section) => $section === 'cta'
    ? pb_include(__DIR__ . '/parts/cta.php', ['cta' => $page->cta])
    : pb_include(__DIR__ . '/parts/services-content.php', ['page' => $page, 'title' => $title])) ?>
