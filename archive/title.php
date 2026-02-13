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
<h1 class="archive-title">Our Appartments</h1>
<h2 class="archive-subtitle"><?php echo esc_html( immomakler_archive_subheadline() ); ?></h2>

<?php if ( class_exists( 'ImmoMakler_Options' ) && ImmoMakler_Options::get( 'show_orderby' ) ) : ?>
	<div class="immomakler-child-orderby">
		<?php immomakler_get_template_part( 'archive/orderby', 'property' ); ?>
	</div>
<?php endif; ?>

