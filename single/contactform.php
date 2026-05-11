<?php if ( apply_filters( 'immomakler_show_contactform', true, get_the_ID() ) && ( has_term( 'offen', 'immomakler_object_status', get_the_ID() ) || has_term( 'reserviert', 'immomakler_object_status', get_the_ID() ) ) ) : ?>
	<div class="property-contactform panel panel-default hidden-print" id="immomakler-contactform-panel">
		<div class="panel-heading">
			<h2><?php esc_html_e( 'Direktanfrage', 'immomakler' ); ?></h2>
		</div>
		<div class="panel-body">

			<?php do_action( 'immomakler_before_contactform' ); ?>

			<?php
			if ( \ImmoMakler\Wohnungshelden\Contact::has_valid_contact_form( get_the_ID() ) ) {
				\ImmoMakler\Wohnungshelden\Contact::render_contact_form( get_the_ID() );
			} elseif ( ImmoMakler_Options::get( 'wpforms_active' )
						 && ImmoMakler_Options::get( 'wpforms_form_id' ) ) {
				echo do_shortcode( sprintf( '[wpforms id="%d"]', ImmoMakler_Options::get( 'wpforms_form_id' ) ) );
			} elseif ( ImmoMakler_Options::get( 'contactform_shortcode' ) ) {
				echo do_shortcode( ImmoMakler_Options::get( 'contactform_shortcode' ) );
			} else {
				global $immomakler_contact;
				$immomakler_contact->render_contact_form();
			}
			?>
		</div>
	</div>
<?php else : ?>
	<?php do_action( 'immomakler_no_contactform' ); ?>
<?php endif; ?>
