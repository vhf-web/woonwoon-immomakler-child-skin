<?php
/**
 * Objektdaten panel – child override.
 * Wraps Objekt-ID value in <span class="no-translate"> so it is not auto-translated.
 */
$property_data = new \ImmoMakler\Data\PropertyData();
?>

<div class="property-details panel panel-default">
	<div class="panel-heading"><h2><?php esc_html_e( 'Objektdaten', 'immomakler' ); ?></h2></div>
	<ul class="list-group">
		<?php do_action( 'immomakler_single_details_begin' ); ?>

		<?php foreach ( $property_data->get_single_keys() as $key ) : ?>
			<?php if ( $property_data->show( $key ) ) : ?>
			<li class="list-group-item data-<?php echo sanitize_key( $key ); ?>">
				<div class="row">
					<div class="dt col-sm-5"><?php echo esc_html( $property_data->get_label( $key ) ); ?></div>
					<div class="dd col-sm-7"><?php
					if ( $key === 'objektnr_extern' ) {
						echo '<span class="no-translate">' . wp_kses_post( $property_data->get_value( $key ) ) . '</span>';
					} else {
						echo wp_kses_post( $property_data->get_value( $key ) );
					}
					?></div>
				</div>
			</li>
			<?php endif; ?>
		<?php endforeach; ?>

		<?php do_action( 'immomakler_single_details_before_price' ); ?>

		<?php foreach ( $property_data->get_single_price_keys() as $key ) : ?>
			<?php if ( $property_data->show( $key ) ) : ?>
			<li class="list-group-item data-<?php echo sanitize_key( $key ); ?>">
				<div class="row price">
					<div class="dt col-sm-5"><?php echo esc_html( $property_data->get_label( $key ) ); ?></div>
					<div class="dd col-sm-7"><?php echo wp_kses_post( $property_data->get_value( $key ) ); ?></div>
				</div>
			</li>
			<?php endif; ?>
		<?php endforeach; ?>

		<?php do_action( 'immomakler_single_details_end' ); ?>

		<?php
		// Slugs must stay 'offen' / 'reserviert' — __( 'offen' ) becomes e.g. "open" in English and breaks has_term().
		if ( apply_filters( 'immomakler_show_contactform', true, get_the_ID() ) && ( has_term( 'offen', 'immomakler_object_status', get_the_ID() ) || has_term( 'reserviert', 'immomakler_object_status', get_the_ID() ) ) ) :
			?>
		<li class="list-group-item property-details-direktanfrage-row">
			<div class="property-details-direktanfrage">
				<a href="#immomakler-contactform-panel" class="btn btn-primary btn-direktanfrage"><?php esc_html_e( 'Direktanfrage', 'immomakler' ); ?></a>
			</div>
		</li>
		<?php endif; ?>
	</ul>
</div> <!-- .property-details -->
