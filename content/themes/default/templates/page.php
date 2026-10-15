<?php
/**
 * A simple page: its own content (title, introduction, photo, text) and the pieces the client added, in the order chosen in the panel.
 *
 * @var PbGroup $page
 * @var PbValue $title
 */
?>
<?= pb_sections($page, ['content'], fn($section) => pb_include(__DIR__ . '/parts/page-content.php', ['page' => $page, 'title' => $title])) ?>
