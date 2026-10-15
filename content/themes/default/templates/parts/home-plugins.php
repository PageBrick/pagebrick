<?php
/**
 * A section of the home page (templates/home.php prints them in the order the client chose in the panel).
 * Where plugins add their own sections, like the latest news.
 */
?>
<?= pb_slot('home') /* plugins can add sections here, like the latest news */ ?>
