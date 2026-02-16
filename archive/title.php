<?php
/**
 * Child skin override: archive titles.
 *
 * H1: fixed "Our Appartments."
 * H2: archive subheadline from ImmoMakler (filtered in functions.php).
 *
 * @package ImmoMakler Child Skin
 */

?>
<h1 class="archive-title">Apartments</h1>
<h2 class="archive-subtitle"><?php echo esc_html( immomakler_archive_subheadline() ); ?></h2>

