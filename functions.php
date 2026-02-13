<?php

/**
 * Child skin functions for WOONWOON – Search filters only.
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

// Search ranges: Zimmer, Pauschalmiete, Verfügbar ab
add_filter( 'immomakler_search_enabled_ranges', 'woonwoon_search_ranges', 20 );

function woonwoon_search_ranges( $ranges ) {
	$currency = function_exists( 'immomakler_get_currency_from_iso' )
		? immomakler_get_currency_from_iso( ImmoMakler_Options::get( 'default_currency_iso' ) )
		: 'EUR';
	return [
		'immomakler_search_rooms'       => [
			'label'       => __( 'Zimmer', 'immomakler' ),
			'slug'        => 'zimmer',
			'unit'        => '',
			'decimals'    => 1,
			'meta_key'    => 'anzahl_zimmer',
			'slider_step' => 0.5,
		],
		'immomakler_search_pauschalmiete' => [
			'label'       => __( 'Pauschalmiete', 'immomakler' ),
			'slug'        => 'pauschalmiete',
			'unit'        => $currency,
			'decimals'    => 0,
			'meta_key'    => 'pauschalmiete',
			'slider_step' => 100,
		],
		'immomakler_search_verfuegbar_ab' => [
			'label'       => __( 'Verfügbar ab', 'immomakler' ),
			'slug'        => 'verfuegbar_ab',
			'unit'        => '',
			'decimals'    => 0,
			'meta_key'    => 'verfuegbar_ab',
			'slider_step' => 1,
		],
	];
}

// Clear cached range values once so sliders get correct min/max (fixes NaN)
add_action( 'init', function () {
	if ( ! get_option( 'woonwoon_search_transients_cleared' ) ) {
		foreach ( [ 'pauschalmiete', 'verfuegbar_ab' ] as $slug ) {
			delete_transient( "immomakler_upto_value_max_{$slug}" );
			delete_transient( "immomakler_max_meta_value_{$slug}" );
			delete_transient( "immomakler_min_meta_value_{$slug}" );
		}
		update_option( 'woonwoon_search_transients_cleared', 1 );
	}
}, 5 );

// Fix NaN on sliders when DB has no data
add_filter( 'immomakler_search_min_meta_value', function ( $v, $key ) {
	if ( $key === 'pauschalmiete' && ( $v === null || $v === '' ) ) return 0;
	if ( $key === 'verfuegbar_ab' && ( $v === null || $v === '' ) ) return 20200101;
	return $v;
}, 10, 2 );
add_filter( 'immomakler_search_max_meta_value', function ( $v, $key ) {
	if ( $key === 'pauschalmiete' && ( $v === null || $v === '' || (float) $v <= 0 ) ) return 5000;
	if ( $key === 'verfuegbar_ab' && ( $v === null || $v === '' || (float) $v <= 0 ) ) return 20301231;
	return $v;
}, 10, 2 );

// Register query vars for search params (so they are kept in URL)
add_filter( 'query_vars', function ( $vars ) {
	return array_merge( $vars, [
		'regionaler_zusatz',
		'von-zimmer', 'bis-zimmer',
		'von-pauschalmiete', 'bis-pauschalmiete',
		'von-verfuegbar_ab', 'bis-verfuegbar_ab',
	] );
} );

// Ortsteil field in search form
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

// Apply search filters – runs after plugin (101)
add_action( 'pre_get_posts', 'woonwoon_search_pre_get_posts', 101 );

function woonwoon_search_pre_get_posts( WP_Query $query ) {
	$pt = $query->get( 'post_type' );
	if ( $pt !== 'immomakler_object' ) {
		return;
	}

	$get_param = function ( $key ) use ( $query ) {
		$v = $query->get( $key );
		if ( $v !== '' && $v !== null ) return $v;
		if ( isset( $_GET[ $key ] ) ) return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
		if ( isset( $_POST[ $key ] ) ) return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		return '';
	};

	$meta_query = (array) $query->get( 'meta_query' );

	// Regionaler Zusatz: meta LIKE OR location term LIKE
	$regionaler = $get_param( 'regionaler_zusatz' );
	if ( $regionaler !== '' ) {
		$GLOBALS['woonwoon_regionaler'] = $regionaler;
		add_filter( 'posts_where', 'woonwoon_posts_where_regionaler', 10, 2 );
	}

	// verfuegbar_ab: plugin uses numeric, we need DATE
	$von = (int) $get_param( 'von-verfuegbar_ab' ) ?: 20200101;
	$bis = (int) $get_param( 'bis-verfuegbar_ab' ) ?: 20301231;
	$out = [];
	foreach ( $meta_query as $c ) {
		$k = $c['key'] ?? '';
		if ( $k === 'verfuegbar_ab' ) {
			$out[] = [
				'key'     => 'verfuegbar_ab',
				'value'   => [ woonwoon_yyyymmdd( $von ), woonwoon_yyyymmdd( $bis ) ],
				'type'    => 'DATE',
				'compare' => 'BETWEEN',
			];
		} else {
			$out[] = $c;
		}
	}
	$query->set( 'meta_query', $out );
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

function woonwoon_yyyymmdd( $n ) {
	$n = (int) $n;
	if ( $n <= 0 ) return '2020-01-01';
	$y = (int) floor( $n / 10000 );
	$m = max( 1, min( 12, (int) floor( ( $n % 10000 ) / 100 ) ) );
	$d = max( 1, min( 31, (int) ( $n % 100 ) ) );
	return sprintf( '%04d-%02d-%02d', $y, $m, $d );
}

