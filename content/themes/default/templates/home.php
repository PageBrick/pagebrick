<?php
/**
 * The sections of the home page, in the order the client chose in the panel (theme.php lists them in 'sections').
 * Each one is a file in parts/; pb_section() only marks it while the client is editing, so visitors get the same HTML.
 *
 * @var PbGroup $page   this page's fields (see theme.php)
 * @var PbValue $title
 */
?>
<?php foreach ($page->order(['hero', 'about', 'services', 'numbers', 'testimonials', 'plugins', 'cta']) as $section): ?>
<?= pb_section($section, pb_include(__DIR__ . "/parts/home-$section.php", ['page' => $page, 'title' => $title])) ?>
<?php endforeach ?>
