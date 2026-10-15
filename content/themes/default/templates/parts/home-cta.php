<?php
/**
 * A section of the home page (templates/home.php prints them in the order the client chose in the panel).
 * @var PbGroup $page
 */
?>
<?= pb_include(__DIR__ . '/cta.php', ['cta' => $page->cta]) ?>
