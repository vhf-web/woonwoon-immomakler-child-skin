<?php
/**
 * Objektdaten panel – child override.
 * Adds translate="no" and class "notranslate" for Objekt-ID and address/street
 * so browser/plugin automatic translation does not translate them.
 */
$property_data = new \ImmoMakler\Data\PropertyData();

// Keys that must not be auto-translated (Objekt-ID, address/street).
$no_translate_keys = apply_filters( 'immomakler_single_data_notranslate_keys', [ 'objektnr_extern', 'adresse' ] );
$no_translate_keys = is_array( $no_translate_keys ) ? $no_translate_keys : [ 'objektnr_extern', 'adresse' ];
?>

<div class="property-details panel panel-default">
	<div class="panel-heading"><h2><?php esc_html_e( 'Objektdaten', 'immomakler' ); ?></h2></div>
	<ul class="list-group">
		<?php do_action( 'immomakler_single_details_begin' ); ?>

		<?php foreach ( $property_data->get_single_keys() as $key ) : ?>
			<?php if ( $property_data->show( $key ) ) : ?>
			<?php
			$no_translate = in_array( $key, $no_translate_keys, true );
			$li_class     = 'list-group-item data-' . sanitize_key( $key );
			if ( $no_translate ) {
				$li_class .= ' notranslate';
			}
			$li_attr = $no_translate ? ' translate="no"' : '';
			?>
			<li class="<?php echo esc_attr( $li_class ); ?>"<?php echo $li_attr; ?>>
				<div class="row">
					<div class="dt col-sm-5"><?php echo esc_html( $property_data->get_label( $key ) ); ?></div>
					<div class="dd col-sm-7"><?php echo wp_kses_post( $property_data->get_value( $key ) ); ?></div>
				</div>
			</li>
			<?php endif; ?>
		<?php endforeach; ?>

		<?php do_action( 'immomakler_single_details_before_price' ); ?>

		<?php foreach ( $property_data->get_single_price_keys() as $key ) : ?>
			<?php if ( $property_data->show( $key ) ) : ?>
			<?php
			$no_translate = in_array( $key, $no_translate_keys, true );
			$li_class     = 'list-group-item data-' . sanitize_key( $key );
			if ( $no_translate ) {
				$li_class .= ' notranslate';
			}
			$li_attr = $no_translate ? ' translate="no"' : '';
			?>
			<li class="<?php echo esc_attr( $li_class ); ?>"<?php echo $li_attr; ?>>
				<div class="row price">
					<div class="dt col-sm-5"><?php echo esc_html( $property_data->get_label( $key ) ); ?></div>
					<div class="dd col-sm-7"><?php echo wp_kses_post( $property_data->get_value( $key ) ); ?></div>
				</div>
			</li>
			<?php endif; ?>
		<?php endforeach; ?>

		<?php do_action( 'immomakler_single_details_end' ); ?>
	</ul>
</div> <!-- .property-details -->
