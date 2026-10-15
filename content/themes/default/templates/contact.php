<?php
/**
 * The contact page: its own content (title, contact data, text, the form of the contact plugin) and the pieces the client added, in the order chosen in the panel.
 *
 * @var PbGroup $page
 * @var PbGroup $site
 * @var PbValue $title
 */
?>
<?= pb_sections($page, ['content'], fn($section) => pb_include(__DIR__ . '/parts/contact-content.php', ['page' => $page, 'site' => $site, 'title' => $title])) ?>
