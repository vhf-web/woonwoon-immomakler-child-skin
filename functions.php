<?php
/**
 * Child skin functions for WOONWOON – Search filters.
 *
 * Zimmer + Fläche: plugin default (no custom code).
 * Pauschalmiete: add filter "bis X €". Kaltmiete + Kaufpreis removed.
 *
 * IMPORTANT:
 * WP ImmoMakler stores pauschalmiete inside `immomakler_metadata` (container meta).
 * WP_Query cannot filter inside that container.
 * Solution: mirror pauschalmiete into dedicated meta keys:
 *   - pauschalmiete (raw, optional)
 *   - pauschalmiete_numeric (float, for filtering)
 */

/* ------------------------------------------------------------
 * UI tweaks
 * ------------------------------------------------------------ */

// Filter bar redesign: keep advanced filters visible (no collapse UX).
add_filter( 'immomakler_search_hide_advanced', '__return_false' );

// Selectpicker: remove the "markiert auswählen" done button.
add_filter( 'immomakler_search_selectpicker_show_done_button', '__return_false' );

// Primary CTA label.
add_filter( 'immomakler_search_button_text', function ( $text, $count ) {
	$count = absint( $count );
	if ( $count > 0 ) {
		return __( '%s Ergebnisse anzeigen', 'immomakler-child-skin' );
	}
	return __( 'Ergebnisse anzeigen', 'immomakler-child-skin' );
}, 20, 2 );

// Enqueue small JS to rearrange buttons + init selectpicker after AJAX refresh.
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() ) {
		return;
	}
	$is_archive = false;
	if ( function_exists( 'is_immomakler_archive' ) ) {
		$is_archive = (bool) is_immomakler_archive();
	} elseif ( function_exists( 'is_post_type_archive' ) ) {
		$is_archive = (bool) is_post_type_archive( 'immomakler_object' );
	}
	if ( ! $is_archive ) {
		return;
	}
	wp_enqueue_script(
		'woonwoon-immomakler-filterbar',
		plugins_url( 'js/woonwoon-immomakler-filterbar.js', __FILE__ ),
		[ 'jquery' ],
		'2026-02-20.1',
		true
	);
}, 30 );

// Archive subtitle
add_filter( 'immomakler_archive_subheadline', function ( $title ) {
	return 'Appartments';
} );

// No taxonomy dropdowns ("Alle Orte" etc.)
add_filter( 'immomakler_search_enabled_taxonomies', function ( $taxonomies ) {
	return [];
} );
add_action( 'immomakler_search_taxonomies_row', function () {}, 5 );

/* ------------------------------------------------------------
 * Single page: hide some data rows
 * ------------------------------------------------------------ */

// Remove Energieträger + Mindest-/Maximale Mietdauer from property detail panel.
add_filter( 'immomakler_property_data_single_keys', function ( $keys, $post_id ) {
	if ( ! is_array( $keys ) ) {
		return $keys;
	}
	return array_values( array_diff( $keys, [ 'energietraeger', 'min_mietdauer', 'max_mietdauer' ] ) );
}, 20, 2 );

/* ------------------------------------------------------------
 * Single page: subtitle + "merken" label
 * ------------------------------------------------------------ */

// Subtitle formatting:
// - Single: "... zur Miete" -> "Wohnen auf Zeit"
// - Archive cards: "PLZ Ort, Wohnung" -> "PLZ Ort, Kreuzberg" (regionaler_zusatz)
add_filter( 'immomakler_property_subtitle', function ( $subtitle ) {
	$subtitle = (string) $subtitle;

	$is_single = function_exists( 'is_immomakler_single' ) && is_immomakler_single();
	if ( $is_single ) {
		// Typical format: "PLZ Ort, Objektart zur Miete" -> "PLZ Ort, Wohnen auf Zeit"
		$subtitle = preg_replace( '/,\s*[^,]+?\s+zur\s+Miete\s*$/u', ', Wohnen auf Zeit', $subtitle );
		// Fallback if format differs: just replace the suffix.
		$subtitle = preg_replace( '/\s+zur\s+Miete\s*$/u', ' Wohnen auf Zeit', $subtitle );
		return $subtitle;
	}

	// Archive/listing: prefer regionaler_zusatz (district) instead of object type.
	$post_id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
	if ( $post_id <= 0 ) {
		return $subtitle;
	}

	$plz  = trim( (string) get_post_meta( $post_id, 'plz', true ) );
	$ort  = trim( (string) get_post_meta( $post_id, 'ort', true ) );
	$base = trim( trim( $plz . ' ' . $ort ) );
	if ( $base === '' ) {
		return $subtitle;
	}

	$regionaler_zusatz = trim( (string) get_post_meta( $post_id, 'regionaler_zusatz', true ) );
	$district          = function_exists( 'woonwoon_clean_regionaler_zusatz_for_address' )
		? woonwoon_clean_regionaler_zusatz_for_address( $regionaler_zusatz )
		: $regionaler_zusatz;

	// Fallback: use sublocality if no regionaler_zusatz is present.
	if ( $district === '' ) {
		$sublocality = trim( (string) get_post_meta( $post_id, 'sublocality', true ) );
		if ( $sublocality !== '' && strpos( $ort, $sublocality ) === false ) {
			$district = $sublocality;
		}
	}

	if ( $district === '' || $district === $ort ) {
		return $base;
	}

	return $base . ', ' . $district;
}, 20 );

// Capitalize the favorites button label.
add_filter( 'immomakler_cart_add_to_cart', function ( $caption ) {
	return __( 'Merken', 'immomakler' );
}, 20 );

/* ------------------------------------------------------------
 * Single page: Adresse cleanup (regionaler_zusatz)
 * ------------------------------------------------------------ */

function woonwoon_clean_regionaler_zusatz_for_address( string $regionaler_zusatz ): string {
	$r = trim( $regionaler_zusatz );
	if ( $r === '' ) return '';

	// If stored like "(Charlottenburg)" -> "Charlottenburg"
	if ( preg_match( '/^\((.+)\)$/u', $r, $m ) ) {
		$r = trim( $m[1] );
	}

	// If stored like "Charlottenburg (Charlottenburg)" -> "Charlottenburg"
	if ( preg_match( '/^(.+?)\s*\(\s*(.+?)\s*\)\s*$/u', $r, $m ) ) {
		$a = trim( $m[1] );
		$b = trim( $m[2] );
		if ( $a !== '' && $a === $b ) {
			$r = $b;
		}
	}

	return trim( $r );
}

/**
 * Mirror `regionaler_zusatz` into a normalized meta key for filtering.
 * Stores:
 * - regionaler_zusatz_clean (string)
 */
function woonwoon_mirror_regionaler_zusatz_clean( int $post_id ): void {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return;
	}
	if ( get_post_type( $post_id ) !== 'immomakler_object' ) {
		return;
	}

	$raw = trim( (string) get_post_meta( $post_id, 'regionaler_zusatz', true ) );
	if ( $raw === '' ) {
		delete_post_meta( $post_id, 'regionaler_zusatz_clean' );
		delete_transient( 'woonwoon_regionaler_zusatz_clean_options' );
		return;
	}

	$clean = trim( woonwoon_clean_regionaler_zusatz_for_address( $raw ) );
	if ( $clean === '' ) {
		delete_post_meta( $post_id, 'regionaler_zusatz_clean' );
		delete_transient( 'woonwoon_regionaler_zusatz_clean_options' );
		return;
	}

	update_post_meta( $post_id, 'regionaler_zusatz_clean', $clean );
	delete_transient( 'woonwoon_regionaler_zusatz_clean_options' );
}

// Keep mirror updated.
add_action( 'updated_post_meta', function ( $meta_id, $post_id, $meta_key, $meta_value ) {
	if ( $meta_key !== 'regionaler_zusatz' ) {
		return;
	}
	woonwoon_mirror_regionaler_zusatz_clean( (int) $post_id );
}, 10, 4 );

add_action( 'added_post_meta', function ( $meta_id, $post_id, $meta_key, $meta_value ) {
	if ( $meta_key !== 'regionaler_zusatz' ) {
		return;
	}
	woonwoon_mirror_regionaler_zusatz_clean( (int) $post_id );
}, 10, 4 );

add_action( 'save_post_immomakler_object', function ( $post_id ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	woonwoon_mirror_regionaler_zusatz_clean( (int) $post_id );
}, 25 );

add_action( 'immomakler_after_publish_post', function ( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return;
	}
	woonwoon_mirror_regionaler_zusatz_clean( $post_id );
}, 25 );

/**
 * Backfill `regionaler_zusatz_clean` for existing objects in small batches (admin only).
 */
add_action( 'admin_init', function () {
	if ( get_option( 'woonwoon_regionaler_zusatz_clean_migrated' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$q = new WP_Query(
		[
			'post_type'      => 'immomakler_object',
			'fields'         => 'ids',
			'posts_per_page' => 200,
			'no_found_rows'  => true,
			'meta_query'     => [
				[
					'key'     => 'regionaler_zusatz',
					'compare' => 'EXISTS',
				],
				[
					'key'     => 'regionaler_zusatz_clean',
					'compare' => 'NOT EXISTS',
				],
			],
		]
	);

	if ( empty( $q->posts ) ) {
		update_option( 'woonwoon_regionaler_zusatz_clean_migrated', 1 );
		return;
	}

	foreach ( $q->posts as $pid ) {
		woonwoon_mirror_regionaler_zusatz_clean( (int) $pid );
	}
	delete_transient( 'woonwoon_regionaler_zusatz_clean_options' );
} );

add_filter( 'immomakler_property_data', function ( $property_data, $post_id ) {
	if ( ! is_array( $property_data ) ) return $property_data;
	if ( empty( $property_data['adresse']['value'] ) || ! is_string( $property_data['adresse']['value'] ) ) return $property_data;

	$orig = trim( (string) get_post_meta( (int) $post_id, 'regionaler_zusatz', true ) );
	if ( $orig === '' ) return $property_data;

	$clean = woonwoon_clean_regionaler_zusatz_for_address( $orig );
	if ( $clean === '' || $clean === $orig ) return $property_data;

	$property_data['adresse']['value'] = str_replace(
		'<br>(' . $orig . ')',
		' (' . esc_html( $clean ) . ')',
		$property_data['adresse']['value']
	);

	return $property_data;
}, 30, 2 );

/* ------------------------------------------------------------
 * Archive: add Pauschalmiete sorting
 * ------------------------------------------------------------ */

// Remove default "Preis" sorting (keep Pauschalmiete sorting).
add_filter( 'immomakler_orderby_options', function ( array $options, string $active_order ) : array {
	unset( $options['pricedesc'], $options['priceasc'] );
	return $options;
}, 15, 2 );

// Allow new order keys.
add_filter( 'immomakler_allowed_orderby', function ( $allowed ) {
	if ( ! is_array( $allowed ) ) {
		$allowed = [];
	}
	$allowed[] = 'pauschasc';
	$allowed[] = 'pauschdesc';
	return array_values( array_unique( $allowed ) );
}, 20 );

// Add dropdown options.
add_filter( 'immomakler_orderby_options', function ( array $options, string $active_order ) : array {
	$pausch_options = [
		'pauschdesc' => [
			'label'  => __( 'Pauschalmiete absteigend', 'immomakler' ),
			'href'   => add_query_arg( 'im_order', 'pauschdesc' ),
			'active' => ( $active_order === 'pauschdesc' ),
		],
		'pauschasc'  => [
			'label'  => __( 'Pauschalmiete aufsteigend', 'immomakler' ),
			'href'   => add_query_arg( 'im_order', 'pauschasc' ),
			'active' => ( $active_order === 'pauschasc' ),
		],
	];

	// Insert right after the normal price options if present.
	$merged = [];
	foreach ( $options as $k => $v ) {
		$merged[ $k ] = $v;
		if ( $k === 'priceasc' ) {
			foreach ( $pausch_options as $pk => $pv ) {
				$merged[ $pk ] = $pv;
			}
		}
	}
	// If 'priceasc' wasn't present, append at end.
	foreach ( $pausch_options as $pk => $pv ) {
		if ( ! isset( $merged[ $pk ] ) ) {
			$merged[ $pk ] = $pv;
		}
	}
	return $merged;
}, 20, 2 );

// Make the query sort by `pauschalmiete_numeric`.
add_filter( 'immomakler_orderby_query_params', function ( array $query_params, string $order_key ) : array {
	if ( $order_key !== 'pauschasc' && $order_key !== 'pauschdesc' ) {
		return $query_params;
	}

	$direction = ( $order_key === 'pauschasc' ) ? 'ASC' : 'DESC';

	$meta_query = $query_params['meta_query'] ?? [];
	if ( ! is_array( $meta_query ) ) {
		$meta_query = [];
	}

	$meta_query['orderby_clause'] = [
		'key'     => 'pauschalmiete_numeric',
		'compare' => 'EXISTS',
		'type'    => 'NUMERIC',
	];

	$orderby = [
		'orderby_clause' => $direction,
	];

	// Keep plugin's "status first" sorting if enabled.
	if ( class_exists( 'ImmoMakler_Options' ) && ImmoMakler_Options::get( 'orderby_status' ) ) {
		$meta_query['orderby_status_clause'] = [
			'key'     => 'status_order',
			'compare' => 'EXISTS',
			'type'    => 'NUMERIC',
		];
		$orderby = array_merge( [ 'orderby_status_clause' => 'ASC' ], $orderby );
	}

	$query_params['meta_query']         = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	$query_params['orderby']            = $orderby;
	$query_params['ignore_custom_sort'] = true;

	return $query_params;
}, 20, 2 );

/* ------------------------------------------------------------
 * Archive: fix "n{Gesamt}" results label
 * ------------------------------------------------------------ */

// Ensure the infinite scroll label uses correct placeholders.
add_filter( 'immomakler_search_infinitescroll_amount_label', function ( $label ) {
	// Keep placeholders {shown}/{total} for the JS replacer.
	if ( strpos( (string) $label, '{shown}' ) !== false ) {
		return 'Ergebnisse {shown} von {total}';
	}
	return '{total} Ergebnisse';
} );

/* ------------------------------------------------------------
 * Searchable keys (plugin side indexing / allowed meta keys)
 * ------------------------------------------------------------ */
add_filter( 'immomakler_searchable_postmeta_keys', function ( $keys ) {
	if ( ! is_array( $keys ) ) {
		$keys = [];
	}

	return array_values(
		array_unique(
			array_merge(
				$keys,
				[
					// our mirrored keys:
					'pauschalmiete',
					'pauschalmiete_numeric',

					// other keys you want searchable:
					'warmmiete',
					'preis',
				]
			)
		)
	);
} );

/* ------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------ */

/**
 * Normalize localized price strings to float.
 * Examples: "2.390,00 EUR" -> 2390.00, "1190" -> 1190.00
 */
function woonwoon_normalize_price_to_float( $raw ): float {
	$s = trim( (string) $raw );
	if ( $s === '' ) {
		return 0.0;
	}

	$s = str_ireplace( [ 'eur', '€' ], '', $s );
	$s = preg_replace( '/\s+/', '', $s );

	// If there is a comma, treat comma as decimal separator and strip dots (thousands separators).
	if ( strpos( $s, ',' ) !== false ) {
		$s = str_replace( '.', '', $s );
		$s = str_replace( ',', '.', $s );
	} else {
		// Otherwise strip any commas used as thousands separators.
		$s = str_replace( ',', '', $s );
	}

	// Keep only digits, dot and minus.
	$s = preg_replace( '/[^0-9\.\-]/', '', $s );

	return (float) $s;
}

/* ------------------------------------------------------------
 * Mirror fields out of immomakler_metadata container
 * ------------------------------------------------------------ */

/**
 * Read fields from immomakler_metadata (array) and mirror to dedicated meta keys.
 * - pauschalmiete (raw string)
 * - pauschalmiete_numeric (float)
 * - regionaler_zusatz (raw string)
 * - regionaler_zusatz_clean (normalized string, for filtering)
 */
function woonwoon_mirror_from_immomakler_metadata( int $post_id, $meta_value = null ): void {
	// Get container (if not passed)
	if ( $meta_value === null ) {
		$meta_value = get_post_meta( $post_id, 'immomakler_metadata', true );
	}

	$data = $meta_value;

	// In some contexts it might be serialized string
	if ( is_string( $data ) ) {
		$data = maybe_unserialize( $data );
	}

	if ( ! is_array( $data ) ) {
		// If the container is missing (e.g. because the plugin flattens/deletes it),
		// never delete the real meta keys. Instead, try to compute numeric from existing `pauschalmiete`.
		$raw_existing = trim( (string) get_post_meta( $post_id, 'pauschalmiete', true ) );
		if ( $raw_existing !== '' ) {
			$val_existing = woonwoon_normalize_price_to_float( $raw_existing );
			if ( $val_existing > 0 ) {
				update_post_meta( $post_id, 'pauschalmiete_numeric', $val_existing );
			} else {
				delete_post_meta( $post_id, 'pauschalmiete_numeric' );
			}
		} else {
			delete_post_meta( $post_id, 'pauschalmiete_numeric' );
		}

		// Regionaler Zusatz: keep existing mirrored keys if present; only recompute clean from existing raw key.
		$rz_existing = trim( (string) get_post_meta( $post_id, 'regionaler_zusatz', true ) );
		if ( $rz_existing !== '' ) {
			$rz_clean_existing = trim( woonwoon_clean_regionaler_zusatz_for_address( $rz_existing ) );
			if ( $rz_clean_existing !== '' ) {
				update_post_meta( $post_id, 'regionaler_zusatz_clean', $rz_clean_existing );
			} else {
				delete_post_meta( $post_id, 'regionaler_zusatz_clean' );
			}
		} else {
			delete_post_meta( $post_id, 'regionaler_zusatz_clean' );
		}
		delete_transient( 'woonwoon_regionaler_zusatz_clean_options' );
		return;
	}

	$raw = isset( $data['pauschalmiete'] ) ? (string) $data['pauschalmiete'] : '';
	$raw = trim( $raw );

	if ( $raw !== '' ) {
		// Store raw as separate meta (optional but useful for debugging)
		update_post_meta( $post_id, 'pauschalmiete', $raw );

		$val = woonwoon_normalize_price_to_float( $raw );
		if ( $val > 0 ) {
			update_post_meta( $post_id, 'pauschalmiete_numeric', $val );
		} else {
			delete_post_meta( $post_id, 'pauschalmiete_numeric' );
		}
	} else {
		// Do not delete raw `pauschalmiete` to avoid fighting with plugin imports.
		delete_post_meta( $post_id, 'pauschalmiete_numeric' );
	}

	// Regionaler Zusatz (Bezirk/Ortsteil) from container.
	$rz_raw = isset( $data['regionaler_zusatz'] ) ? (string) $data['regionaler_zusatz'] : '';
	$rz_raw = trim( $rz_raw );
	if ( $rz_raw !== '' ) {
		update_post_meta( $post_id, 'regionaler_zusatz', $rz_raw );
		$rz_clean = trim( woonwoon_clean_regionaler_zusatz_for_address( $rz_raw ) );
		if ( $rz_clean !== '' ) {
			update_post_meta( $post_id, 'regionaler_zusatz_clean', $rz_clean );
		} else {
			delete_post_meta( $post_id, 'regionaler_zusatz_clean' );
		}
	} else {
		// Do not delete raw `regionaler_zusatz` to avoid fighting with plugin imports.
		delete_post_meta( $post_id, 'regionaler_zusatz_clean' );
	}
	delete_transient( 'woonwoon_regionaler_zusatz_clean_options' );
}

/**
 * When the container meta updates, refresh mirrors.
 * This is the most reliable trigger because WP ImmoMakler writes immomakler_metadata.
 */
add_action( 'updated_post_meta', function ( $meta_id, $post_id, $meta_key, $meta_value ) {
	if ( $meta_key !== 'immomakler_metadata' ) {
		return;
	}

	static $running = false;
	if ( $running ) {
		return;
	}
	$running = true;

	woonwoon_mirror_from_immomakler_metadata( (int) $post_id, $meta_value );

	$running = false;
}, 10, 4 );

add_action( 'added_post_meta', function ( $meta_id, $post_id, $meta_key, $meta_value ) {
	if ( $meta_key !== 'immomakler_metadata' ) {
		return;
	}

	static $running = false;
	if ( $running ) {
		return;
	}
	$running = true;

	woonwoon_mirror_from_immomakler_metadata( (int) $post_id, $meta_value );

	$running = false;
}, 10, 4 );

/**
 * Fallback: also try mirroring on save (covers edge cases where container meta doesn't trigger hooks as expected).
 */
add_action( 'save_post_immomakler_object', function ( $post_id ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	woonwoon_mirror_from_immomakler_metadata( (int) $post_id );
}, 20 );

/**
 * After importer publishes/updates a property, mirror again.
 * This covers cases where the plugin flattens/deletes `immomakler_metadata`.
 */
add_action( 'immomakler_after_publish_post', function ( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return;
	}
	if ( get_post_type( $post_id ) !== 'immomakler_object' ) {
		return;
	}
	woonwoon_mirror_from_immomakler_metadata( $post_id );
}, 20 );

/**
 * Backfill mirrors for existing objects in small batches (admin only).
 * This runs until finished and then sets an option flag.
 */
add_action( 'admin_init', function () {
	if ( get_option( 'woonwoon_pauschalmiete_numeric_migrated' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$q = new WP_Query(
		[
			'post_type'      => 'immomakler_object',
			'fields'         => 'ids',
			'posts_per_page' => 100,
			'no_found_rows'  => true,
			'meta_query'     => [
				[
					'relation' => 'OR',
					[
						'key'     => 'immomakler_metadata',
						'compare' => 'EXISTS',
					],
					[
						'key'     => 'pauschalmiete',
						'compare' => 'EXISTS',
					],
				],
				[
					'key'     => 'pauschalmiete_numeric',
					'compare' => 'NOT EXISTS',
				],
			],
		]
	);

	if ( empty( $q->posts ) ) {
		update_option( 'woonwoon_pauschalmiete_numeric_migrated', 1 );
		return;
	}

	foreach ( $q->posts as $pid ) {
		woonwoon_mirror_from_immomakler_metadata( (int) $pid );
	}
} );

/**
 * Backfill `regionaler_zusatz_clean` from the container meta (admin only).
 */
add_action( 'admin_init', function () {
	if ( get_option( 'woonwoon_regionaler_zusatz_clean_from_container_migrated' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$q = new WP_Query(
		[
			'post_type'      => 'immomakler_object',
			'fields'         => 'ids',
			'posts_per_page' => 200,
			'no_found_rows'  => true,
			'meta_query'     => [
				[
					'key'     => 'immomakler_metadata',
					'compare' => 'EXISTS',
				],
				[
					'key'     => 'regionaler_zusatz_clean',
					'compare' => 'NOT EXISTS',
				],
			],
		]
	);

	if ( empty( $q->posts ) ) {
		update_option( 'woonwoon_regionaler_zusatz_clean_from_container_migrated', 1 );
		return;
	}

	foreach ( $q->posts as $pid ) {
		woonwoon_mirror_from_immomakler_metadata( (int) $pid );
	}

	delete_transient( 'woonwoon_regionaler_zusatz_clean_options' );
} );

/* ------------------------------------------------------------
 * Ranges: remove all range sliders/text fields (we render custom fields)
 * ------------------------------------------------------------ */

add_filter( 'immomakler_search_enabled_ranges', 'woonwoon_search_ranges', 20 );

function woonwoon_search_ranges( $ranges ) {
	unset( $ranges['immomakler_search_price_rent'], $ranges['immomakler_search_price_buy'] );
	// Replace plugin ranges with custom fields.
	unset( $ranges['immomakler_search_rooms'] );
	unset( $ranges['immomakler_search_size'] );

	return $ranges;
}

/**
 * Get numeric max for a given meta key (cached).
 */
function woonwoon_get_max_numeric_meta_value( string $meta_key, string $transient_key, int $ttl = DAY_IN_SECONDS ): float {
	global $wpdb;

	$max = get_transient( $transient_key );
	if ( $max !== false ) {
		return (float) $max;
	}

	$meta_key_sql = esc_sql( $meta_key );
	$max          = (float) $wpdb->get_var(
		"SELECT MAX(CAST(pm.meta_value AS DECIMAL(12,2)))
		 FROM {$wpdb->postmeta} pm
		 WHERE pm.meta_key = '{$meta_key_sql}'
		   AND pm.meta_value IS NOT NULL
		   AND pm.meta_value != ''"
	);
	set_transient( $transient_key, $max, $ttl );
	return (float) $max;
}

/**
 * Get Bezirk options from `regionaler_zusatz_clean` (cached).
 *
 * @return string[] list of unique, cleaned values
 */
function woonwoon_get_regionaler_zusatz_options(): array {
	global $wpdb;

	$cached = get_transient( 'woonwoon_regionaler_zusatz_clean_options' );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$rows = $wpdb->get_col(
		"SELECT DISTINCT pm.meta_value
		 FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE p.post_type = 'immomakler_object'
		   AND p.post_status = 'publish'
		   AND pm.meta_key = 'regionaler_zusatz_clean'
		   AND pm.meta_value IS NOT NULL
		   AND pm.meta_value != ''
		 ORDER BY pm.meta_value ASC"
	);

	$vals = [];
	foreach ( (array) $rows as $v ) {
		$v = trim( (string) $v );
		if ( $v === '' ) continue;
		$vals[] = $v;
	}
	$vals = array_values( array_unique( $vals ) );

	// Fallback: if clean options are not yet available, derive from raw key.
	if ( empty( $vals ) ) {
		$rows_raw = $wpdb->get_col(
			"SELECT DISTINCT pm.meta_value
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE p.post_type = 'immomakler_object'
			   AND p.post_status = 'publish'
			   AND pm.meta_key = 'regionaler_zusatz'
			   AND pm.meta_value IS NOT NULL
			   AND pm.meta_value != ''
			 ORDER BY pm.meta_value ASC"
		);
		$tmp = [];
		foreach ( (array) $rows_raw as $raw ) {
			$clean = trim( woonwoon_clean_regionaler_zusatz_for_address( (string) $raw ) );
			if ( $clean === '' ) continue;
			$tmp[] = $clean;
		}
		$vals = array_values( array_unique( $tmp ) );
		sort( $vals, SORT_NATURAL | SORT_FLAG_CASE );
	}

	set_transient( 'woonwoon_regionaler_zusatz_clean_options', $vals, DAY_IN_SECONDS );
	return $vals;
}

/* ------------------------------------------------------------
 * Search form: custom filter grid
 * ------------------------------------------------------------ */

add_action( 'immomakler_search_form_after_ranges', function () {
	if ( is_admin() ) {
		return;
	}

	// Values for min/max fields (keep empty if not set => placeholders visible).
	$qm_min = '';
	$qm_max = '';
	if ( isset( $_GET['von-qm'] ) ) $qm_min = sanitize_text_field( (string) wp_unslash( $_GET['von-qm'] ) );
	if ( isset( $_GET['bis-qm'] ) ) $qm_max = sanitize_text_field( (string) wp_unslash( $_GET['bis-qm'] ) );
	if ( $qm_min === '' && isset( $_POST['von-qm'] ) ) $qm_min = sanitize_text_field( (string) wp_unslash( $_POST['von-qm'] ) );
	if ( $qm_max === '' && isset( $_POST['bis-qm'] ) ) $qm_max = sanitize_text_field( (string) wp_unslash( $_POST['bis-qm'] ) );

	$rent_min = '';
	$rent_max = '';
	if ( isset( $_GET['von-pauschalmiete'] ) ) $rent_min = sanitize_text_field( (string) wp_unslash( $_GET['von-pauschalmiete'] ) );
	if ( isset( $_GET['bis-pauschalmiete'] ) ) $rent_max = sanitize_text_field( (string) wp_unslash( $_GET['bis-pauschalmiete'] ) );
	if ( $rent_min === '' && isset( $_POST['von-pauschalmiete'] ) ) $rent_min = sanitize_text_field( (string) wp_unslash( $_POST['von-pauschalmiete'] ) );
	if ( $rent_max === '' && isset( $_POST['bis-pauschalmiete'] ) ) $rent_max = sanitize_text_field( (string) wp_unslash( $_POST['bis-pauschalmiete'] ) );

	$currency = function_exists( 'immomakler_get_currency_from_iso' )
		? immomakler_get_currency_from_iso( ImmoMakler_Options::get( 'default_currency_iso' ) )
		: 'EUR';

	// Normalize: treat "0" and "full-range max" as empty so placeholders stay visible.
	$qm_min_num = (float) str_replace( ',', '.', (string) $qm_min );
	if ( $qm_min_num <= 0 ) $qm_min = '';
	$qm_max_num = (float) str_replace( ',', '.', (string) $qm_max );
	$qm_db_max  = woonwoon_get_max_numeric_meta_value( 'flaeche', 'woonwoon_flaeche_max_numeric' );
	if ( $qm_max_num <= 0 ) {
		$qm_max = '';
	} elseif ( $qm_db_max > 0 && abs( $qm_max_num - $qm_db_max ) < 0.01 ) {
		$qm_max = '';
	}

	$rent_min_num = (float) str_replace( ',', '.', (string) $rent_min );
	if ( $rent_min_num <= 0 ) $rent_min = '';
	$rent_max_num = (float) str_replace( ',', '.', (string) $rent_max );
	$rent_db_max  = (float) get_transient( 'woonwoon_pauschalmiete_max_numeric' );
	if ( $rent_db_max === 0.0 || $rent_db_max === false ) {
		$rent_db_max = woonwoon_get_max_numeric_meta_value( 'pauschalmiete_numeric', 'woonwoon_pauschalmiete_max_numeric' );
	}
	if ( $rent_max_num <= 0 ) {
		$rent_max = '';
	} elseif ( $rent_db_max > 0 && abs( $rent_max_num - $rent_db_max ) < 0.01 ) {
		$rent_max = '';
	}

	// Rooms (multi select) - first column
	$selected = [];
	if ( isset( $_GET['zimmer_multi'] ) ) {
		$selected = (array) wp_unslash( $_GET['zimmer_multi'] );
	} elseif ( isset( $_POST['zimmer_multi'] ) ) {
		$selected = (array) wp_unslash( $_POST['zimmer_multi'] );
	}
	$selected = array_values(
		array_unique(
			array_filter(
				array_map(
					static function ( $v ) {
						return sanitize_text_field( (string) $v );
					},
					$selected
				),
				static function ( $v ) {
					return $v !== '';
				}
			)
		)
	);

	$options = [
		'1'     => '1',
		'2'     => '2',
		'3'     => '3',
		'4'     => '4',
		'5plus' => '5+',
	];

	echo '<fieldset class="immomakler-search-range woonwoon-filter-field immomakler-search-rooms">';
	echo '<div class="range-label">' . esc_html__( 'Zimmer', 'immomakler-child-skin' ) . '</div>';
	echo '<select class="selectpicker form-control" name="zimmer_multi[]" multiple data-width="100%" data-actions-box="false" data-selected-text-format="count > 1" data-count-selected-text="{0} ausgewählt" title="' . esc_attr__( 'Zimmer auswählen', 'immomakler-child-skin' ) . '">';
	foreach ( $options as $value => $label ) {
		$is_selected = in_array( (string) $value, $selected, true ) ? ' selected' : '';
		$lbl = ( $value === '5plus' ) ? '5+' : $label;
		echo '<option value="' . esc_attr( (string) $value ) . '"' . $is_selected . '>' . esc_html( $lbl ) . '</option>';
	}
	echo '</select>';
	echo '</fieldset>';

	// Area (m²) - second column
	echo '<fieldset class="immomakler-search-range woonwoon-filter-field woonwoon-filter-area">';
	echo '<div class="range-label">' . esc_html__( 'Fläche (m²)', 'immomakler-child-skin' ) . '</div>';
	echo '<div class="woonwoon-minmax">';
	echo '<input class="form-control" type="number" inputmode="numeric" min="0" step="1" name="von-qm" placeholder="' . esc_attr__( 'Min', 'immomakler-child-skin' ) . '" value="' . esc_attr( $qm_min ) . '">';
	echo '<input class="form-control" type="number" inputmode="numeric" min="0" step="1" name="bis-qm" placeholder="' . esc_attr__( 'Max', 'immomakler-child-skin' ) . '" value="' . esc_attr( $qm_max ) . '">';
	echo '</div>';
	echo '</fieldset>';

	// Rent (EUR) - third column (pauschalmiete_numeric)
	echo '<fieldset class="immomakler-search-range woonwoon-filter-field woonwoon-filter-rent">';
	echo '<div class="range-label">' . esc_html__( 'Miete', 'immomakler-child-skin' ) . ' (' . esc_html( $currency ) . ')</div>';
	echo '<div class="woonwoon-minmax">';
	echo '<input class="form-control" type="number" inputmode="numeric" min="0" step="50" name="von-pauschalmiete" placeholder="' . esc_attr__( 'Min', 'immomakler-child-skin' ) . '" value="' . esc_attr( $rent_min ) . '">';
	echo '<input class="form-control" type="number" inputmode="numeric" min="0" step="50" name="bis-pauschalmiete" placeholder="' . esc_attr__( 'Max', 'immomakler-child-skin' ) . '" value="' . esc_attr( $rent_max ) . '">';
	echo '</div>';
	echo '</fieldset>';

	// Bezirk / Ortsteil (regionaler_zusatz)
	$bezirk_selected = [];
	if ( isset( $_GET['bezirk'] ) ) {
		$bezirk_selected = (array) wp_unslash( $_GET['bezirk'] );
	} elseif ( isset( $_POST['bezirk'] ) ) {
		$bezirk_selected = (array) wp_unslash( $_POST['bezirk'] );
	}
	$bezirk_selected = array_values(
		array_unique(
			array_filter(
				array_map(
					static function ( $v ) {
						return sanitize_text_field( (string) $v );
					},
					$bezirk_selected
				),
				static function ( $v ) {
					return $v !== '';
				}
			)
		)
	);

	$bezirk_options = woonwoon_get_regionaler_zusatz_options();
	echo '<fieldset class="immomakler-search-range woonwoon-filter-field woonwoon-filter-bezirk">';
	echo '<div class="range-label">' . esc_html__( 'Bezirk / Ortsteil', 'immomakler-child-skin' ) . '</div>';
	echo '<select class="selectpicker form-control" name="bezirk[]" multiple data-width="100%" data-actions-box="false" data-live-search="true" data-selected-text-format="count > 1" data-count-selected-text="{0} ausgewählt" title="' . esc_attr__( 'Bezirk wählen', 'immomakler-child-skin' ) . '">';
	foreach ( $bezirk_options as $v ) {
		$is_selected = in_array( (string) $v, $bezirk_selected, true ) ? ' selected' : '';
		echo '<option value="' . esc_attr( (string) $v ) . '"' . $is_selected . '>' . esc_html( (string) $v ) . '</option>';
	}
	echo '</select>';
	echo '</fieldset>';
}, 20 );

/* ------------------------------------------------------------
 * Fix NaN on Pauschalmiete slider when DB has no data / cached max values
 * ------------------------------------------------------------ */

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
	if ( $key === 'pauschalmiete' && ( $v === null || $v === '' ) ) {
		return 0;
	}
	return $v;
}, 10, 2 );

add_filter( 'immomakler_search_max_meta_value', function ( $v, $key ) {
	global $wpdb;

	if ( $key !== 'pauschalmiete' ) {
		return $v;
	}

	// Slider max is computed for slug "pauschalmiete" but must use our numeric meta key.
	$max = get_transient( 'woonwoon_pauschalmiete_max_numeric' );
	if ( $max === false ) {
		$max = (float) $wpdb->get_var(
			"SELECT MAX(CAST(pm.meta_value AS DECIMAL(12,2)))
			 FROM {$wpdb->postmeta} pm
			 WHERE pm.meta_key = 'pauschalmiete_numeric'
			   AND pm.meta_value IS NOT NULL
			   AND pm.meta_value != ''"
		);
		set_transient( 'woonwoon_pauschalmiete_max_numeric', $max, DAY_IN_SECONDS );
	}

	if ( $max > 0 ) {
		return $max;
	}

	// fallback default
	if ( $v === null || $v === '' || (float) $v <= 0 ) {
		return 5000;
	}

	return $v;
}, 10, 2 );

/* ------------------------------------------------------------
 * Apply custom filters to frontend queries
 * ------------------------------------------------------------ */

add_action( 'pre_get_posts', 'woonwoon_search_pre_get_posts', 101 );

function woonwoon_is_immomakler_query( WP_Query $query ) {
	$post_type = $query->get( 'post_type' );
	if ( $post_type === 'immomakler_object' ) {
		return true;
	}
	if ( is_array( $post_type ) && in_array( 'immomakler_object', $post_type, true ) ) {
		return true;
	}
	if ( method_exists( $query, 'is_post_type_archive' ) && $query->is_post_type_archive( 'immomakler_object' ) ) {
		return true;
	}
	return false;
}

function woonwoon_search_pre_get_posts( WP_Query $query ) {
	// Only touch frontend property queries.
	if ( is_admin() ) {
		return;
	}
	if ( ! woonwoon_is_immomakler_query( $query ) ) {
		return;
	}

	/**
	 * Force Objektart = Wohnung (taxonomy `immomakler_object_type`).
	 * This keeps the archive/listing limited to apartments only.
	 */
	$taxonomy = apply_filters( 'immomakler_property_type_taxonomy', 'immomakler_object_type' );
	$wohnung_slug = 'wohnung';
	if ( class_exists( '\ImmoMakler\Helpers\I18n_Helper' ) ) {
		$wohnung_slug = \ImmoMakler\Helpers\I18n_Helper::generate_i18n_term_slug( 'Wohnung' );
	} elseif ( function_exists( 'sanitize_title' ) ) {
		$wohnung_slug = sanitize_title( 'Wohnung' );
	}

	$tax_query = $query->get( 'tax_query' );
	if ( ! is_array( $tax_query ) ) {
		$tax_query = [];
	}
	$tax_query[] = [
		'taxonomy'         => $taxonomy,
		'field'            => 'slug',
		'terms'            => [ $wohnung_slug ],
		'include_children' => true,
		'operator'         => 'IN',
	];
	$query->set( 'tax_query', $tax_query );

	$meta_query = $query->get( 'meta_query' );
	if ( ! is_array( $meta_query ) ) {
		$meta_query = [];
	}

	// Area (m²): GET/POST params `von-qm` / `bis-qm` filter meta_key `flaeche`
	$qm_von = $query->get( 'von-qm' );
	$qm_bis = $query->get( 'bis-qm' );
	if ( $qm_von === null || $qm_von === '' ) $qm_von = isset( $_GET['von-qm'] ) ? trim( (string) wp_unslash( $_GET['von-qm'] ) ) : '';
	if ( $qm_bis === null || $qm_bis === '' ) $qm_bis = isset( $_GET['bis-qm'] ) ? trim( (string) wp_unslash( $_GET['bis-qm'] ) ) : '';
	if ( $qm_von === null || $qm_von === '' ) $qm_von = isset( $_POST['von-qm'] ) ? trim( (string) wp_unslash( $_POST['von-qm'] ) ) : '';
	if ( $qm_bis === null || $qm_bis === '' ) $qm_bis = isset( $_POST['bis-qm'] ) ? trim( (string) wp_unslash( $_POST['bis-qm'] ) ) : '';

	$qm_from = abs( floatval( str_replace( ',', '.', (string) $qm_von ) ) );
	$qm_to   = abs( floatval( str_replace( ',', '.', (string) $qm_bis ) ) );
	$qm_db_max = woonwoon_get_max_numeric_meta_value( 'flaeche', 'woonwoon_flaeche_max_numeric' );
	if ( $qm_to > 0 && $qm_db_max > 0 && abs( $qm_to - $qm_db_max ) < 0.01 ) {
		$qm_to = 0; // full range => no filter
	}
	if ( $qm_from > 0 || $qm_to > 0 ) {
		if ( $qm_from > 0 && $qm_to > 0 ) {
			if ( $qm_from > $qm_to ) {
				$tmp     = $qm_from;
				$qm_from = $qm_to;
				$qm_to   = $tmp;
			}
			$meta_query[] = [
				'key'     => 'flaeche',
				'value'   => [ $qm_from, $qm_to ],
				'type'    => 'NUMERIC',
				'compare' => 'BETWEEN',
			];
		} elseif ( $qm_from > 0 ) {
			$meta_query[] = [
				'key'     => 'flaeche',
				'value'   => $qm_from,
				'type'    => 'NUMERIC',
				'compare' => '>=',
			];
		} else {
			$meta_query[] = [
				'key'     => 'flaeche',
				'value'   => $qm_to,
				'type'    => 'NUMERIC',
				'compare' => '<=',
			];
		}
	}

	/**
	 * Pauschalmiete:
	 * The plugin slider uses GET params `von-pauschalmiete` and `bis-pauschalmiete`
	 * and (with our range config) should already filter by meta_key `pauschalmiete_numeric`.
	 *
	 * This block adds a fallback while migration runs:
	 * If some objects still don't have `pauschalmiete_numeric`, try using raw `pauschalmiete`
	 * (only works if it exists, which we now mirror).
	 */
	$von_raw = $query->get( 'von-pauschalmiete' );
	$bis_raw = $query->get( 'bis-pauschalmiete' );

	if ( $von_raw === null || $von_raw === '' ) {
		$von_raw = isset( $_GET['von-pauschalmiete'] ) ? trim( wp_unslash( $_GET['von-pauschalmiete'] ) ) : '';
	}
	if ( $bis_raw === null || $bis_raw === '' ) {
		$bis_raw = isset( $_GET['bis-pauschalmiete'] ) ? trim( wp_unslash( $_GET['bis-pauschalmiete'] ) ) : '';
	}
	if ( $von_raw === null || $von_raw === '' ) {
		$von_raw = isset( $_POST['von-pauschalmiete'] ) ? trim( wp_unslash( $_POST['von-pauschalmiete'] ) ) : '';
	}
	if ( $bis_raw === null || $bis_raw === '' ) {
		$bis_raw = isset( $_POST['bis-pauschalmiete'] ) ? trim( wp_unslash( $_POST['bis-pauschalmiete'] ) ) : '';
	}

	if ( $von_raw !== '' || $bis_raw !== '' ) {
		$von = abs( floatval( str_replace( ',', '.', (string) $von_raw ) ) );
		$bis = abs( floatval( str_replace( ',', '.', (string) $bis_raw ) ) );

		// Remove any existing plugin clause, we replace with robust OR group(s).
		foreach ( $meta_query as $k => $clause ) {
			if ( $k === 'relation' || ! is_array( $clause ) ) {
				continue;
			}
			if ( isset( $clause['key'] ) && $clause['key'] === 'pauschalmiete_numeric' ) {
				unset( $meta_query[ $k ] );
			}
		}

		$op = null;
		$val = null;
		$rent_db_max = (float) get_transient( 'woonwoon_pauschalmiete_max_numeric' );
		if ( $rent_db_max === 0.0 || $rent_db_max === false ) {
			$rent_db_max = woonwoon_get_max_numeric_meta_value( 'pauschalmiete_numeric', 'woonwoon_pauschalmiete_max_numeric' );
		}
		if ( $bis > 0 && $rent_db_max > 0 && abs( $bis - $rent_db_max ) < 0.01 ) {
			$bis = 0; // full range => no filter
		}

		if ( $von > 0 && $bis > 0 ) {
			if ( $von > $bis ) {
				$tmp = $von;
				$von = $bis;
				$bis = $tmp;
			}
			$op  = 'BETWEEN';
			$val = [ $von, $bis ];
		} elseif ( $von > 0 ) {
			$op  = '>=';
			$val = $von;
		} elseif ( $bis > 0 ) {
			$op  = '<=';
			$val = $bis;
		}

		if ( $op && $val !== null ) {
			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'     => 'pauschalmiete_numeric',
					'value'   => $val,
					'type'    => 'NUMERIC',
					'compare' => $op,
				],
				[
					'relation' => 'AND',
					[
						'key'     => 'pauschalmiete_numeric',
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => 'pauschalmiete',
						'value'   => $val,
						'type'    => 'NUMERIC',
						'compare' => $op,
					],
				],
			];
		}
	}

	/**
	 * Zimmer dropdown (multi-select):
	 * - Uses GET/POST param `zimmer_multi[]`
	 * - Filters meta_key `anzahl_zimmer` (plugin default for rooms)
	 */
	$zimmer_multi = [];
	if ( isset( $_GET['zimmer_multi'] ) ) {
		$zimmer_multi = (array) wp_unslash( $_GET['zimmer_multi'] );
	} elseif ( isset( $_POST['zimmer_multi'] ) ) {
		$zimmer_multi = (array) wp_unslash( $_POST['zimmer_multi'] );
	}
	$zimmer_multi = array_values(
		array_unique(
			array_filter(
				array_map(
					static function ( $v ) {
						return sanitize_text_field( (string) $v );
					},
					$zimmer_multi
				),
				static function ( $v ) {
					return $v !== '';
				}
			)
		)
	);

	if ( ! empty( $zimmer_multi ) ) {
		$or = [ 'relation' => 'OR' ];
		foreach ( $zimmer_multi as $v ) {
			if ( $v === '5plus' ) {
				$or[] = [
					'key'     => 'anzahl_zimmer',
					'value'   => 5,
					'type'    => 'NUMERIC',
					'compare' => '>=',
				];
				continue;
			}

			$num = (float) str_replace( ',', '.', (string) $v );
			if ( $num <= 0 ) {
				continue;
			}
			$or[] = [
				'key'     => 'anzahl_zimmer',
				'value'   => $num,
				'type'    => 'NUMERIC',
				'compare' => '=',
			];
		}
		if ( count( $or ) > 1 ) {
			$meta_query[] = $or;
		}
	}

	/**
	 * Bezirk / Ortsteil dropdown (multi-select):
	 * - Uses GET/POST param `bezirk[]`
	 * - Filters meta_key `regionaler_zusatz_clean` (preferred) with fallback to raw `regionaler_zusatz` (LIKE)
	 */
	$bezirk = [];
	if ( isset( $_GET['bezirk'] ) ) {
		$bezirk = (array) wp_unslash( $_GET['bezirk'] );
	} elseif ( isset( $_POST['bezirk'] ) ) {
		$bezirk = (array) wp_unslash( $_POST['bezirk'] );
	}
	$bezirk = array_values(
		array_unique(
			array_filter(
				array_map(
					static function ( $v ) {
						return sanitize_text_field( (string) $v );
					},
					$bezirk
				),
				static function ( $v ) {
					return $v !== '';
				}
			)
		)
	);
	if ( ! empty( $bezirk ) ) {
		$or_bezirk = [ 'relation' => 'OR' ];
		$or_bezirk[] = [
			'key'     => 'regionaler_zusatz_clean',
			'value'   => $bezirk,
			'compare' => 'IN',
		];
		foreach ( $bezirk as $b ) {
			$or_bezirk[] = [
				'key'     => 'regionaler_zusatz',
				'value'   => $b,
				'compare' => 'LIKE',
			];
		}
		$meta_query[] = $or_bezirk;
	}

	$query->set( 'meta_query', $meta_query );
}
