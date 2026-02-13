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
	if ( ! get_option( 'woonwoon_search_transients_cleared' ) ) {
		foreach ( [ 'pauschalmiete' ] as $slug ) {
			delete_transient( "immomakler_upto_value_max_{$slug}" );
			delete_transient( "immomakler_max_meta_value_{$slug}" );
			delete_transient( "immomakler_min_meta_value_{$slug}" );
		}
		update_option( 'woonwoon_search_transients_cleared', 1 );
	}
}, 5 );

add_filter( 'immomakler_search_min_meta_value', function ( $v, $key ) {
	if ( $key === 'pauschalmiete' && ( $v === null || $v === '' ) ) return 0;
	return $v;
}, 10, 2 );
add_filter( 'immomakler_search_max_meta_value', function ( $v, $key ) {
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
	$selected = isset( $_GET['regionaler_zusatz'] ) ? sanitize_text_field( wp_unslash( $_GET['regionaler_zusatz'] ) ) : '';

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

// Regionaler Zusatz + Pauschalmiete (runs after plugin apply_ranges at 100)
add_action( 'pre_get_posts', 'woonwoon_search_pre_get_posts', 101 );

function woonwoon_search_pre_get_posts( WP_Query $query ) {
	if ( $query->get( 'post_type' ) !== 'immomakler_object' ) return;

	// --- Regionaler Zusatz ---
	$regionaler = '';
	if ( isset( $_GET['regionaler_zusatz'] ) ) {
		$regionaler = sanitize_text_field( wp_unslash( $_GET['regionaler_zusatz'] ) );
	}
	if ( $regionaler !== '' ) {
		$GLOBALS['woonwoon_regionaler'] = $regionaler;
		add_filter( 'posts_where', 'woonwoon_posts_where_regionaler', 10, 2 );
	}

	// --- Pauschalmiete: handle entirely ourselves (plugin has type-mismatch bugs) ---
	$von_raw = isset( $_GET['von-pauschalmiete'] ) ? trim( $_GET['von-pauschalmiete'] ) : '';
	$bis_raw = isset( $_GET['bis-pauschalmiete'] ) ? trim( $_GET['bis-pauschalmiete'] ) : '';

	if ( $von_raw === '' && $bis_raw === '' ) {
		return; // no pauschalmiete params → nothing to do
	}

	$von = abs( floatval( str_replace( ',', '.', $von_raw ) ) );
	$bis = abs( floatval( str_replace( ',', '.', $bis_raw ) ) );

	$meta_query = $query->get( 'meta_query' );
	if ( ! is_array( $meta_query ) ) {
		$meta_query = [];
	}

	// Remove any existing pauschalmiete clause the plugin may have added
	foreach ( $meta_query as $key => $clause ) {
		if ( $key === 'relation' ) continue;
		if ( isset( $clause['key'] ) && $clause['key'] === 'pauschalmiete' ) {
			unset( $meta_query[ $key ] );
		}
	}

	// Add our own clause: BETWEEN + NOT EXISTS (so posts without pauschalmiete still show)
	if ( $bis > 0 && $von <= $bis ) {
		$meta_query[] = [
			'relation' => 'OR',
			[
				'key'     => 'pauschalmiete',
				'value'   => [ $von, $bis ],
				'type'    => 'NUMERIC',
				'compare' => 'BETWEEN',
			],
			[
				'key'     => 'pauschalmiete',
				'compare' => 'NOT EXISTS',
			],
		];
	}

	$query->set( 'meta_query', $meta_query );
}

function woonwoon_posts_where_regionaler( $where, WP_Query $query ) {
	$r = $GLOBALS['woonwoon_regionaler'] ?? '';
	if ( $r === '' || $query->get( 'post_type' ) !== 'immomakler_object' ) {
		remove_filter( 'posts_where', 'woonwoon_posts_where_regionaler', 10 );
		return $where;
	}
	global $wpdb;
	$like = '%' . $wpdb->esc_like( $r ) . '%';
	$tax = apply_filters( 'immomakler_location_taxonomy', 'immomakler_object_location' );
	$meta_sql = $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='regionaler_zusatz' AND meta_value LIKE %s",
		$like
	);
	$term_sql = $wpdb->prepare(
		"SELECT tr.object_id FROM {$wpdb->term_relationships} tr
		INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id=tt.term_taxonomy_id
		INNER JOIN {$wpdb->terms} t ON tt.term_id=t.term_id
		WHERE tt.taxonomy=%s AND t.name LIKE %s",
		$tax,
		$like
	);
	$where .= " AND ( {$wpdb->posts}.ID IN ({$meta_sql}) OR {$wpdb->posts}.ID IN ({$term_sql}) )";
	unset( $GLOBALS['woonwoon_regionaler'] );
	remove_filter( 'posts_where', 'woonwoon_posts_where_regionaler', 10 );
	return $where;
}
