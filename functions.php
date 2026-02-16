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
 * Mirror pauschalmiete out of immomakler_metadata container
 * ------------------------------------------------------------ */

/**
 * Read pauschalmiete from immomakler_metadata (array) and mirror to dedicated meta keys.
 * - pauschalmiete (raw string)
 * - pauschalmiete_numeric (float)
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
		// No valid container -> clean mirrors
		delete_post_meta( $post_id, 'pauschalmiete' );
		delete_post_meta( $post_id, 'pauschalmiete_numeric' );
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
		delete_post_meta( $post_id, 'pauschalmiete' );
		delete_post_meta( $post_id, 'pauschalmiete_numeric' );
	}
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
					'key'     => 'immomakler_metadata',
					'compare' => 'EXISTS',
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

/* ------------------------------------------------------------
 * Ranges: keep Zimmer + Fläche from plugin, add Pauschalmiete, remove Kaltmiete + Kaufpreis
 * ------------------------------------------------------------ */

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
		// Use normalized numeric field for correct comparisons.
		'meta_key'    => 'pauschalmiete_numeric',
		'slider_step' => 100,
	];

	return $ranges;
}

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

	$meta_query = $query->get( 'meta_query' );
	if ( ! is_array( $meta_query ) ) {
		$meta_query = [];
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

		if ( $bis > 0 && $von <= $bis ) {
			// Remove the plugin's `pauschalmiete_numeric` clause (if already present), replace with OR group.
			foreach ( $meta_query as $k => $clause ) {
				if ( $k === 'relation' || ! is_array( $clause ) ) {
					continue;
				}
				if ( isset( $clause['key'] ) && $clause['key'] === 'pauschalmiete_numeric' ) {
					unset( $meta_query[ $k ] );
				}
			}

			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'     => 'pauschalmiete_numeric',
					'value'   => [ $von, $bis ],
					'type'    => 'NUMERIC',
					'compare' => 'BETWEEN',
				],
				[
					'relation' => 'AND',
					[
						'key'     => 'pauschalmiete_numeric',
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => 'pauschalmiete',
						'value'   => [ $von, $bis ],
						'type'    => 'NUMERIC',
						'compare' => 'BETWEEN',
					],
				],
			];
		}
	}

	$query->set( 'meta_query', $meta_query );
}
