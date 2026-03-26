<?php
/**
 * Template to render the action buttons in a single property.
 * Default button "Details". More to add with action 'immomakler_single_property_actions'.
 *
 * Fires action 'immomakler_single_property_actions'.
 *
 * @package ImmoMakler
 */

?>
<div class="property-actions btn-group hidden-print">
	<a class="btn btn-default btn-sm"
	   role="button"
	   rel="nofollow"
	   href="<?php echo esc_url_raw( immomakler_back_to_archive_link() ); ?>">
		<span class="glyphicon glyphicon-list"></span>
		<?php esc_html_e( 'Zur Übersicht', 'immomakler' ); ?>
	</a>

	<?php do_action( 'immomakler_single_property_actions' ); ?>

	<?php
	if ( apply_filters( 'immomakler_show_contactform', true, get_the_ID() )
		 && ( has_term( 'offen', 'immomakler_object_status', get_the_ID() )
			  || has_term( 'reserviert', 'immomakler_object_status', get_the_ID() ) ) ) :
		?>
		<a class="btn btn-primary btn-sm" role="button" href="#immomakler-contactform-panel">
			<span class="glyphicon glyphicon-envelope"></span>
			<?php esc_html_e( 'Direktanfrage', 'immomakler' ); ?>
		</a>
	<?php endif; ?>
</div>

<?php immomakler_get_template_part( 'single/navigation', 'property' ); ?>
