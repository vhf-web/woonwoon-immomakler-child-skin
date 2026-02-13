<?php

/**
 * Child skin functions for WOONWOON – Search filters.
 *
 * Zimmer + Fläche: plugin default (no custom code).
 * Pauschalmiete: add filter "bis X €". Kaltmiete + Kaufpreis removed. 
 */

// Archive subtitle
add_filter( 'immomakler_archive_subheadline', function ( $title ) {
	return 'Appartments';
} );

// No taxonomy dropdowns ("Alle Orte" etc.)
add_filter( 'immomakler_search_enabled_taxonomies', function ( $taxonomies ) {
	return [];
} );
add_action( 'immomakler_search_taxonomies_row', function () {}, 5 );

// Search ranges: keep Zimmer + Fläche from plugin, add Pauschalmiete, remove Kaltmiete + Kaufpreis
add_filter( 'immomakler_search_enabled_ranges', 'woonwoon_search_ranges', 20 );

function woonwoon_search_ranges( $ranges ) {
	unset( $ranges['immomakler_search_price_rent'], $ranges['immomakler_search_price_buy'] );
	$currency = function_exists( 'immomakler_get_currency_from_iso' )
		? immomakler_get_currency_from_iso( ImmoMakler_Options::get( 'default_currency_iso' ) )
		: 'EUR';
	$ranges['immomakler_search_pauschalmiete'] = [
		'label'       => __( 'Pauschalmiete max.', 'immomakler' ),
		'slug'        => 'pauschalmiete',
		'unit'        => $currency,
		'decimals'    => 0,
		'meta_key'    => 'pauschalmiete',
		'slider_step' => 100,
	];
	return $ranges;
}

// Fix NaN on Pauschalmiete slider when DB has no data
add_action( 'init', function () {
	if ( ! get_option( 'woonwoon_search_transients_cleared_v2' ) ) {
		foreach ( [ 'pauschalmiete' ] as $slug ) {
			delete_transient( "immomakler_upto_value_max_{$slug}" );
			delete_transient( "immomakler_max_meta_value_{$slug}" );
			delete_transient( "immomakler_min_meta_value_{$slug}" );
		}
		delete_transient( 'woonwoon_pauschalmiete_max_numeric' );
		update_option( 'woonwoon_search_transients_cleared_v2', 1 );
	}
}, 5 );

add_filter( 'immomakler_search_min_meta_value', function ( $v, $key ) {
	if ( $key === 'pauschalmiete' && ( $v === null || $v === '' ) ) return 0;
	return $v;
}, 10, 2 );
add_filter( 'immomakler_search_max_meta_value', function ( $v, $key ) {
	global $wpdb;

	if ( $key !== 'pauschalmiete' ) return $v;

	$max = get_transient( 'woonwoon_pauschalmiete_max_numeric' );
	if ( $max === false ) {
		$expr = "CASE
			WHEN pm.meta_value LIKE '%,%' THEN REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(pm.meta_value), 'EUR', ''), '€', ''), ' ', ''), '.', ''), ',', '.')
			ELSE REPLACE(REPLACE(REPLACE(REPLACE(TRIM(pm.meta_value), 'EUR', ''), '€', ''), ' ', ''), ',', '')
		END";
		$max = (float) $wpdb->get_var(
			"SELECT MAX(CAST({$expr} AS DECIMAL(12,2)))
			 FROM {$wpdb->postmeta} pm
			 WHERE pm.meta_key = 'pauschalmiete'
			   AND pm.meta_value IS NOT NULL
			   AND pm.meta_value != ''"
		);
		set_transient( 'woonwoon_pauschalmiete_max_numeric', $max, DAY_IN_SECONDS );
	}

	if ( $max > 0 ) return $max;
	if ( $key === 'pauschalmiete' && ( $v === null || $v === '' || (float) $v <= 0 ) ) return 5000;
	return $v;
}, 10, 2 );

// Register regionaler_zusatz as query var (plugin reads von-/bis- params from $_GET directly)
add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'regionaler_zusatz';
	return $vars;
} );

// Ortsteil dropdown (populated from all unique regionaler_zusatz meta values)
add_action( 'immomakler_search_form_after_ranges', function () {
	$selected = isset( $_GET['regionaler_zusatz'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['regionaler_zusatz'] ) ) ) : '';

	// Get all unique non-empty regionaler_zusatz values from DB (cached for 12h)
	$cache_key = 'woonwoon_regionaler_zusatz_options';
	$options   = get_transient( $cache_key );
	if ( false === $options ) {
		global $wpdb;
		$options = $wpdb->get_col(
			"SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
			 WHERE meta_key = 'regionaler_zusatz'
			   AND meta_value != ''
			   AND meta_value IS NOT NULL
			 ORDER BY meta_value ASC"
		);
		// Filter out numeric-only codes (plugin sometimes stores region codes)
		$options = array_filter( $options, function ( $v ) {
			return preg_match( '/[a-zA-ZäöüÄÖÜß]/', $v );
		} );
		set_transient( $cache_key, $options, 12 * HOUR_IN_SECONDS );
	}
	?>
	<div class="immomakler-search-regionaler-zusatz col-xs-12 col-sm-3">
		<label for="immomakler-search-regionaler-zusatz" class="range-label"><?php esc_html_e( 'Ortsteil / Bezirk', 'immomakler' ); ?></label>
		<select name="regionaler_zusatz" id="immomakler-search-regionaler-zusatz" class="form-control" aria-label="<?php esc_attr_e( 'Ortsteil / Bezirk', 'immomakler' ); ?>">
			<option value=""><?php esc_html_e( 'Alle Ortsteile', 'immomakler' ); ?></option>
			<?php foreach ( $options as $ortsteil ) : ?>
				<option value="<?php echo esc_attr( $ortsteil ); ?>" <?php selected( $selected, $ortsteil ); ?>>
					<?php echo esc_html( $ortsteil ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>
	<?php
} );

// Regionaler Zusatz (plugin handles range filters itself)
add_action( 'pre_get_posts', 'woonwoon_search_pre_get_posts', 101 );

function woonwoon_is_immomakler_query( WP_Query $query ) {
	$post_type = $query->get( 'post_type' );
	if ( $post_type === 'immomakler_object' ) return true;
	if ( is_array( $post_type ) && in_array( 'immomakler_object', $post_type, true ) ) return true;
	if ( method_exists( $query, 'is_post_type_archive' ) && $query->is_post_type_archive( 'immomakler_object' ) ) return true;
	return false;
}

function woonwoon_search_pre_get_posts( WP_Query $query ) {
	// Only touch frontend property queries.
	if ( is_admin() ) return;
	if ( ! woonwoon_is_immomakler_query( $query ) ) return;

	$meta_query = $query->get( 'meta_query' );
	if ( ! is_array( $meta_query ) ) {
		$meta_query = [];
	}

	// --- Regionaler Zusatz ---
	$regionaler = $query->get( 'regionaler_zusatz' );
	if ( $regionaler === null || $regionaler === '' ) {
		$regionaler = isset( $_GET['regionaler_zusatz'] ) ? wp_unslash( $_GET['regionaler_zusatz'] ) : '';
	}
	$regionaler = trim( sanitize_text_field( (string) $regionaler ) );
	if ( $regionaler !== '' ) {
		$meta_query[] = [
			'key'     => 'regionaler_zusatz',
			'value'   => $regionaler,
			'compare' => 'LIKE',
		];
	}

	$query->set( 'meta_query', $meta_query );
}
