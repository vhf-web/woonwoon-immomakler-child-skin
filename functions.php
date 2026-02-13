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

// Ortsteil field
add_action( 'immomakler_search_form_after_ranges', function () {
	$val = isset( $_GET['regionaler_zusatz'] ) ? sanitize_text_field( wp_unslash( $_GET['regionaler_zusatz'] ) )
		: ( isset( $_POST['regionaler_zusatz'] ) ? sanitize_text_field( wp_unslash( $_POST['regionaler_zusatz'] ) ) : '' );
	?>
	<div class="immomakler-search-regionaler-zusatz col-xs-12 col-sm-3">
		<label for="immomakler-search-regionaler-zusatz" class="range-label"><?php esc_html_e( 'Ortsteil / Bezirk', 'immomakler' ); ?></label>
		<input type="text" name="regionaler_zusatz" id="immomakler-search-regionaler-zusatz" class="form-control" value="<?php echo esc_attr( $val ); ?>" placeholder="<?php esc_attr_e( 'z.B. Kreuzberg, Mitte', 'immomakler' ); ?>">
	</div>
	<?php
} );

// Regionaler Zusatz + Pauschalmiete fixes (runs after plugin apply_ranges at 100)
add_action( 'pre_get_posts', 'woonwoon_search_pre_get_posts', 101 );

function woonwoon_search_pre_get_posts( WP_Query $query ) {
	if ( $query->get( 'post_type' ) !== 'immomakler_object' ) return;

	$get_param = function ( $key ) use ( $query ) {
		$v = $query->get( $key );
		if ( $v !== '' && $v !== null ) return $v;
		if ( isset( $_GET[ $key ] ) ) return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
		if ( isset( $_POST[ $key ] ) ) return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		return '';
	};

	// Regionaler Zusatz: meta LIKE OR location taxonomy
	$regionaler = $get_param( 'regionaler_zusatz' );
	if ( $regionaler !== '' ) {
		$GLOBALS['woonwoon_regionaler'] = $regionaler;
		add_filter( 'posts_where', 'woonwoon_posts_where_regionaler', 10, 2 );
	}

	// Pauschalmiete: plugin creates meta_query even at default range (type mismatch in === check).
	// Posts WITHOUT pauschalmiete meta (e.g. Bauprojekte) get excluded. Fix: add NOT EXISTS + =0 OR clause.
	$meta_query = $query->get( 'meta_query' );
	if ( is_array( $meta_query ) ) {
		foreach ( $meta_query as $i => $clause ) {
			if ( isset( $clause['key'] ) && $clause['key'] === 'pauschalmiete' && ! isset( $clause['relation'] ) ) {
				$meta_query[ $i ] = [
					'relation' => 'OR',
					$clause,
					[
						'key'     => 'pauschalmiete',
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => 'pauschalmiete',
						'value'   => '',
						'compare' => '=',
					],
					[
						'key'     => 'pauschalmiete',
						'value'   => 0,
						'type'    => 'numeric',
						'compare' => '=',
					],
				];
				$query->set( 'meta_query', $meta_query );
				break;
			}
		}
	}
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
