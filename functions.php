<?php

/**
 * Child skin helper functions for WOONWOON.
 */

/**
 * Change ONLY the archive subtitle ("Alle Immobilien") to a fixed label.
 *
 * This is the small H2 under the big "Immobilienangebot" headline
 * on the archive page. Single property titles remain untouched.
 */
add_filter( 'immomakler_archive_subheadline', 'woonwoon_immomakler_archive_subheadline' );

function woonwoon_immomakler_archive_subheadline( string $title ): string {
	return 'Appartments';
}

/**
 * Remove the "Vermarktungsart" (immomakler_object_vermarktung) taxonomy
 * from the list of available taxonomies.
 *
 * This effectively removes the Kauf/Miete filter from ImmoMakler's
 * generic taxonomy handling (e.g. archive titles, some search facets),
 * which is fine here because all objects are on "Miete" only.
 */
add_filter( 'immomakler_available_taxonomies', 'woonwoon_immomakler_remove_vermarktung_taxonomy', 20 );

/**
 * @param string[] $taxonomies
 *
 * @return string[]
 */
function woonwoon_immomakler_remove_vermarktung_taxonomy( array $taxonomies ): array {
	$taxonomies = array_diff( $taxonomies, [ 'immomakler_object_vermarktung' ] );

	// Re-index array keys to keep it clean.
	return array_values( $taxonomies );
}

/**
 * Hide taxonomy dropdowns in the ImmoMakler search form.
 *
 * We remove:
 * - immomakler_object_vermarktung (Kauf/Miete)
 * - immomakler_object_nutzungsart
 * - immomakler_object_type
 * - immomakler_object_location (Ort)
 */
add_filter( 'immomakler_search_enabled_taxonomies', 'woonwoon_immomakler_search_hide_taxonomies' );

function woonwoon_immomakler_search_hide_taxonomies( array $taxonomies ): array {
	$taxonomies = array_diff(
		$taxonomies,
		[
			'immomakler_object_vermarktung',
			'immomakler_object_nutzungsart',
			'immomakler_object_type',
			'immomakler_object_location',
		]
	);

	return array_values( $taxonomies );
}

/**
 * Remove "Alle Orte" (and any taxonomy) dropdown from search form.
 * Plugin may override enabled_taxonomies via option "search_show_taxonomies";
 * by hooking the row we skip the dropdown loop entirely.
 */
add_action( 'immomakler_search_taxonomies_row', 'woonwoon_immomakler_search_no_taxonomy_dropdowns', 5 );

function woonwoon_immomakler_search_no_taxonomy_dropdowns() {
	// Intentionally output nothing so no "Alle Orte" or other taxonomy dropdowns appear.
}

/**
 * Remove some single-property detail fields from "Objektdaten".
 *
 * Removed:
 * - Objekt-ID (objektnr_extern)
 * - Objekttyp (objekt_typen)
 * - Adresse (adresse)
 * - Mindestmietdauer (min_mietdauer)
 * - Maximale Mietdauer (max_mietdauer)
 * - Energieträger (energietraeger)
 */
add_filter( 'immomakler_property_data_single_keys', 'woonwoon_immomakler_hide_single_detail_keys', 10, 2 );

function woonwoon_immomakler_hide_single_detail_keys( array $keys, $post_id ): array {
	$remove = [
		'objektnr_extern',
		'objekt_typen',
		'adresse',
		'min_mietdauer',
		'max_mietdauer',
		'energietraeger',
	];

	return array_values( array_diff( $keys, $remove ) );
}

/**
 * Remove Kaltmiete and Kaufpreis from Objektdaten (price block).
 * Pauschalmiete and Verfügbar ab remain (in price keys / single keys).
 */
add_filter( 'immomakler_property_data_single_price_keys', 'woonwoon_immomakler_hide_price_keys', 10, 2 );

function woonwoon_immomakler_hide_price_keys( array $keys, $post_id ): array {
	$remove = [
		'kaltmiete',
		'nettokaltmiete',
		'kaufpreis',
		'kaufpreisnetto',
		'kaufpreisbrutto',
		'kaufpreisust',
		'kaufpreis_freitext',
	];
	return array_values( array_diff( $keys, $remove ) );
}

/**
 * Advanced search: only Zimmer, Pauschalmiete, Verfügbar ab.
 * (Fläche, Kaltmiete, Kaufpreis removed.)
 */
add_filter( 'immomakler_search_enabled_ranges', 'woonwoon_immomakler_search_ranges', 20 );

function woonwoon_immomakler_search_ranges( array $ranges ): array {
	$currency = \immomakler_get_currency_from_iso( ImmoMakler_Options::get( 'default_currency_iso' ) );
	return [
		'immomakler_search_rooms'      => [
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

/**
 * Clear range transients once so Pauschalmiete/Verfügbar ab sliders get correct min/max (avoid NaN).
 */
add_action( 'init', 'woonwoon_immomakler_clear_search_range_transients_once', 5 );

function woonwoon_immomakler_clear_search_range_transients_once() {
	if ( get_option( 'woonwoon_cleared_search_transients', false ) ) {
		return;
	}
	delete_transient( 'immomakler_upto_value_max_pauschalmiete' );
	delete_transient( 'immomakler_upto_value_max_verfuegbar_ab' );
	delete_transient( 'immomakler_max_meta_value_pauschalmiete' );
	delete_transient( 'immomakler_min_meta_value_pauschalmiete' );
	delete_transient( 'immomakler_max_meta_value_verfuegbar_ab' );
	delete_transient( 'immomakler_min_meta_value_verfuegbar_ab' );
	update_option( 'woonwoon_cleared_search_transients', true );
}

/**
 * Fix NaN on Pauschalmiete/Verfügbar-ab sliders when DB has no or non-numeric values.
 * Plugin uses CAST(meta_value AS UNSIGNED) so empty or date strings yield NULL → 0 → broken slider.
 */
add_filter( 'immomakler_search_min_meta_value', 'woonwoon_immomakler_search_min_meta_value', 10, 2 );
add_filter( 'immomakler_search_max_meta_value', 'woonwoon_immomakler_search_max_meta_value', 10, 2 );

function woonwoon_immomakler_search_min_meta_value( $value, string $meta_key ) {
	if ( $meta_key === 'pauschalmiete' && ( $value === null || $value === '' ) ) {
		return 0;
	}
	if ( $meta_key === 'verfuegbar_ab' && ( $value === null || $value === '' ) ) {
		return 20200101; // YYYYMMDD so slider shows years
	}
	return $value;
}

function woonwoon_immomakler_search_max_meta_value( $value, string $meta_key ) {
	if ( $meta_key === 'pauschalmiete' && ( $value === null || $value === '' || (float) $value <= 0 ) ) {
		delete_transient( 'immomakler_upto_value_max_pauschalmiete' );
		return 5000; // fallback max so slider range is 0–5000
	}
	if ( $meta_key === 'verfuegbar_ab' && ( $value === null || $value === '' || (float) $value <= 0 ) ) {
		delete_transient( 'immomakler_upto_value_max_verfuegbar_ab' );
		return 20301231; // YYYYMMDD
	}
	return $value;
}

/**
 * Register search query vars so GET params are available on the main query.
 */
add_filter( 'query_vars', 'woonwoon_immomakler_search_query_vars' );

function woonwoon_immomakler_search_query_vars( array $vars ): array {
	$vars[] = 'regionaler_zusatz';
	return $vars;
}

/**
 * Add "Regionaler Zusatz" (Ortsteil/Bezirk) text field to advanced search.
 */
add_action( 'immomakler_search_form_after_ranges', 'woonwoon_immomakler_search_regionaler_zusatz_field' );

function woonwoon_immomakler_search_regionaler_zusatz_field() {
	$value = '';
	if ( isset( $_GET['regionaler_zusatz'] ) ) {
		$value = sanitize_text_field( wp_unslash( $_GET['regionaler_zusatz'] ) );
	} elseif ( isset( $_POST['regionaler_zusatz'] ) ) {
		$value = sanitize_text_field( wp_unslash( $_POST['regionaler_zusatz'] ) );
	}
	?>
	<div class="immomakler-search-regionaler-zusatz col-xs-12 col-sm-3">
		<label for="immomakler-search-regionaler-zusatz" class="range-label"><?php esc_html_e( 'Ortsteil / Bezirk', 'immomakler' ); ?></label>
		<input type="text" name="regionaler_zusatz" id="immomakler-search-regionaler-zusatz" class="form-control" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php esc_attr_e( 'z.B. Kreuzberg, Mitte', 'immomakler' ); ?>">
	</div>
	<?php
}

/**
 * Apply search: regionaler_zusatz (LIKE), and fix verfuegbar_ab to DATE comparison.
 * Runs after the plugin's apply_ranges (100).
 */
add_action( 'pre_get_posts', 'woonwoon_immomakler_search_meta_query_fixes', 101 );

function woonwoon_immomakler_search_meta_query_fixes( WP_Query $query ) {
	if ( $query->get( 'post_type' ) !== 'immomakler_object' ) {
		return;
	}
	$meta_query = $query->get( 'meta_query' );
	if ( ! is_array( $meta_query ) ) {
		$meta_query = [];
	}

	// Regionaler Zusatz: search meta AND location taxonomy (e.g. "Kreuzberg" in Ortsteil or as Ort).
	$regionaler = $query->get( 'regionaler_zusatz' );
	if ( ( $regionaler === '' || $regionaler === null ) && isset( $_GET['regionaler_zusatz'] ) ) {
		$regionaler = sanitize_text_field( wp_unslash( $_GET['regionaler_zusatz'] ) );
	}
	if ( ( $regionaler === '' || $regionaler === null ) && isset( $_POST['regionaler_zusatz'] ) ) {
		$regionaler = sanitize_text_field( wp_unslash( $_POST['regionaler_zusatz'] ) );
	}
	if ( $regionaler !== '' && $regionaler !== null ) {
		// Use posts_where so we can match (meta LIKE OR location term name LIKE).
		$GLOBALS['woonwoon_regionaler_search'] = $regionaler;
		add_filter( 'posts_where', 'woonwoon_immomakler_posts_where_regionaler_zusatz', 10, 2 );
	}

	// Replace verfuegbar_ab numeric clause with DATE comparison (plugin stores YYYY-MM-DD)
	$von = $query->get( 'von-verfuegbar_ab' );
	$bis = $query->get( 'bis-verfuegbar_ab' );
	if ( ( $von === '' || $von === null ) && isset( $_GET['von-verfuegbar_ab'] ) ) {
		$von = sanitize_text_field( wp_unslash( $_GET['von-verfuegbar_ab'] ) );
	}
	if ( ( $bis === '' || $bis === null ) && isset( $_GET['bis-verfuegbar_ab'] ) ) {
		$bis = sanitize_text_field( wp_unslash( $_GET['bis-verfuegbar_ab'] ) );
	}
	$von_num = abs( (int) $von );
	$bis_num = abs( (int) $bis );
	$default_max = 20301231;
	if ( $bis_num <= 0 ) {
		$bis_num = $default_max;
	}
	$filtered = [];
	foreach ( $meta_query as $clause ) {
		$key = isset( $clause['key'] ) ? $clause['key'] : '';
		if ( $key === 'verfuegbar_ab' ) {
			// Replace with DATE comparison
			$from_date = woonwoon_immomakler_yyyymmdd_to_date( $von_num ? $von_num : 20200101 );
			$to_date   = woonwoon_immomakler_yyyymmdd_to_date( $bis_num );
			$filtered[] = [
				'key'     => 'verfuegbar_ab',
				'value'   => [ $from_date, $to_date ],
				'type'    => 'DATE',
				'compare' => 'BETWEEN',
			];
			continue;
		}
		$filtered[] = $clause;
	}
	$query->set( 'meta_query', $filtered );
}

/**
 * posts_where: Ortsteil/Bezirk = meta regionaler_zusatz LIKE OR location taxonomy term name LIKE.
 * So "Kreuzberg" finds posts with meta "Kreuzberg" or with Ort (immomakler_object_location) "Kreuzberg".
 */
function woonwoon_immomakler_posts_where_regionaler_zusatz( string $where, WP_Query $query ): string {
	$regionaler = isset( $GLOBALS['woonwoon_regionaler_search'] ) ? $GLOBALS['woonwoon_regionaler_search'] : '';
	if ( $regionaler === '' || $query->get( 'post_type' ) !== 'immomakler_object' ) {
		remove_filter( 'posts_where', 'woonwoon_immomakler_posts_where_regionaler_zusatz', 10 );
		return $where;
	}
	global $wpdb;
	$like = '%' . $wpdb->esc_like( $regionaler ) . '%';
	$meta_ids = $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'regionaler_zusatz' AND meta_value LIKE %s",
		$like
	);
	$location_tax = apply_filters( 'immomakler_location_taxonomy', 'immomakler_object_location' );
	$term_ids_sub = $wpdb->prepare(
		"SELECT tr.object_id FROM {$wpdb->term_relationships} tr
		INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
		INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
		WHERE tt.taxonomy = %s AND t.name LIKE %s",
		$location_tax,
		$like
	);
	$where .= " AND ( {$wpdb->posts}.ID IN ({$meta_ids}) OR {$wpdb->posts}.ID IN ({$term_ids_sub}) )";
	unset( $GLOBALS['woonwoon_regionaler_search'] );
	remove_filter( 'posts_where', 'woonwoon_immomakler_posts_where_regionaler_zusatz', 10 );
	return $where;
}

/**
 * Convert YYYYMMDD number to YYYY-MM-DD string.
 */
function woonwoon_immomakler_yyyymmdd_to_date( $num ) {
	$num = (int) $num;
	if ( $num <= 0 ) {
		return '2020-01-01';
	}
	$y = (int) floor( $num / 10000 );
	$m = (int) floor( ( $num % 10000 ) / 100 );
	$d = (int) ( $num % 100 );
	if ( $m < 1 ) { $m = 1; }
	if ( $m > 12 ) { $m = 12; }
	if ( $d < 1 ) { $d = 1; }
	if ( $d > 31 ) { $d = 31; }
	return sprintf( '%04d-%02d-%02d', $y, $m, $d );
}

