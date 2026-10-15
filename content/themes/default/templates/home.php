<?php
/**
 * The sections of the home page, in the order the client chose in the panel (theme.php lists them in 'sections'), with the pieces the client added.
 * Each theme section is a file in parts/; pb_sections() marks them while the client is editing, so visitors get the same HTML.
 *
 * @var PbGroup $page   this page's fields (see theme.php)
 * @var PbValue $title
 */
?>
<?= pb_sections($page, ['hero', 'about', 'services', 'numbers', 'testimonials', 'plugins', 'cta'], fn($section) => pb_include(__DIR__ . "/parts/home-$section.php", ['page' => $page, 'title' => $title])) ?>
