<?php
/**
 * Template to render the contact details box.
 *
 * @package ImmoMakler
 */

?>
<?php if ( ImmoMakler_Options::get( 'show_contacts' ) ) : ?>
	<?php $contact_data = new \ImmoMakler\Data\ContactData(); ?>

	<div class="property-contact panel panel-default">
		<div class="panel-heading">
			<h2><?php esc_html_e( 'Kontaktdaten', 'immomakler' ); ?></h2>
		</div>
		<div class="panel-body">

		<?php do_action( 'immomakler_single_contact_begin' ); ?>

		<?php
		$kontaktperson_post_id = get_post_meta( get_the_ID(), 'kontaktperson_post_id', true );
		$kontaktperson_name    = $contact_data->show( 'kontaktperson_name' ) ? $contact_data->get_value( 'kontaktperson_name' ) : '';;
		$kontaktperson_vorname = $contact_data->show( 'kontaktperson_vorname' ) ? $contact_data->get_value( 'kontaktperson_vorname' ) : '';
		$kontaktperson_firma   = $contact_data->show( 'kontaktperson_firma' ) ? $contact_data->get_value( 'kontaktperson_firma' ) : '';
		?>

		<?php if ( $kontaktperson_post_id && has_post_thumbnail( $kontaktperson_post_id ) ) : ?>
		<div class="row">
			<div class="col-sm-4 col-sm-push-8 contact-photo thumbnail">
				<?php
				echo get_the_post_thumbnail(
					$kontaktperson_post_id,
					'immomakler-person-thumb',
					[
						'alt'     => join( ', ', [ join( ' ', [ $kontaktperson_vorname, $kontaktperson_name ] ), $kontaktperson_firma ] ),
						'loading' => 'lazy',
					]
				);
				?>
			</div>
			<div class="col-sm-8 col-sm-pull-4">
		<?php endif; ?>

				<ul class="list-group">

					<?php foreach ( $contact_data->get_single_keys() as $key ) : ?>
						<?php if ( $contact_data->show( $key ) ) : ?>

						<li class="list-group-item">
							<div class="row">
								<div class="dt col-sm-5"><?php echo esc_html( $contact_data->get_label( $key ) ); ?></div>
								<div class="dd col-sm-7"><?php echo wp_kses_post( $contact_data->get_value( $key ) ); ?></div>
							</div>
						</li>

						<?php endif; ?>
					<?php endforeach; ?>

					<?php if ( apply_filters( 'immomakler_show_contactform', true, get_the_ID() ) && ( has_term( 'offen', 'immomakler_object_status', get_the_ID() ) || has_term( 'reserviert', 'immomakler_object_status', get_the_ID() ) ) ) : ?>
						<li class="list-group-item hidden-print">
							<a href="#immomakler-contactform-panel" role="button" class="btn btn-primary btn-block property-contact-form-cta-button"><?php esc_html_e( 'zum Kontaktformular', 'immomakler' ); ?></a>
						</li>
					<?php endif; ?>

				</ul>

			<?php do_action( 'immomakler_single_contact_bottom' ); ?>

		<?php if ( $kontaktperson_post_id && has_post_thumbnail( $kontaktperson_post_id ) ) : ?>
			</div>
		</div> <!-- .row -->
		<?php endif; ?>

		</div> <!-- .panel-body -->
	</div> <!-- .property-contact -->
<?php endif; // End of option show_contacts. ?>
