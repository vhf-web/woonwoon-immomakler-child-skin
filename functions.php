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
	return __( 'Ergebnisse anzeigen', 'immomakler-child-skin' );
}, 20, 2 );
// Tracking: GA4 Event auf "Anfrage senden" Button
add_action( 'wp_footer', 'woonwoon_anfragen_tracking' );
function woonwoon_anfragen_tracking() {
	if ( ! is_singular( 'immomakler_object' ) ) {
		return;
	}
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		var btn = document.querySelector('button.submit.btn.btn-primary');
		if (btn) {
			btn.addEventListener('click', function() {
				gtag('event', 'anfrage_gesendet', {
					'send_to': 'G-JLFWVX7YYY',
					'event_category': 'Kontaktformular',
					'event_label': document.title,
					'page_url': window.location.href,
					'posts_id': document.querySelector('input[name="posts"]')
								? document.querySelector('input[name="posts"]').value
								: 'unbekannt'
				});
			});
		}
	});
	</script>
	<?php
}

/**
 * English translations for child-skin search filter strings (no .po for child).
 * When locale is English, translate so /en/ filter labels show in English.
 */
add_filter( 'gettext', function ( $translated, $text, $domain ) {
	if ( $domain !== 'immomakler-child-skin' ) {
		return $translated;
	}
	$is_english = ( strpos( get_locale(), 'en' ) === 0 );
	if ( ! $is_english && function_exists( 'trp_get_current_language' ) ) {
		$is_english = ( trp_get_current_language() === 'en' );
	}
	if ( ! $is_english ) {
		return $translated;
	}
	$map = [
		'Zimmer auswählen'   => 'Select rooms',
		'Bezirk / Ortsteil'  => 'District / area',
		'Bezirk wählen'      => 'Select district',
		'{0} ausgewählt'     => '{0} selected',
		'Ergebnisse anzeigen' => 'Show results',
		'ID, Straße, Ort, Objekttitel oder Merkmal' => 'ID, street, city, title or feature',
	];
	return isset( $map[ $text ] ) ? $map[ $text ] : $translated;
}, 10, 3 );

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
		'2026-04-01.1',
		true
	);
}, 30 );

// Allow `woonwoon_q` on the main query (URL + $query->get).
add_filter(
	'query_vars',
	static function ( array $vars ): array {
		$vars[] = 'woonwoon_q';
		return $vars;
	}
);

// Archive subtitle
add_filter( 'immomakler_archive_subheadline', function ( $title ) {
	return 'Appartments';
} );

// No taxonomy dropdowns ("Alle Orte" etc.)
add_filter( 'immomakler_search_enabled_taxonomies', function ( $taxonomies ) {
	return [];
} );
add_action( 'immomakler_search_taxonomies_row', function () {}, 5 );

// Avoid redirecting directly to single object when searching by Objekt-ID.
// This keeps the archive markup intact for AJAX search and prevents JS errors.
add_filter( 'immomakler_search_for_id_redirect_to_post', '__return_false' );

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

// Remove objektnr_extern from inline archive data items – it is rendered
// separately below the price via the hook below.
add_filter( 'immomakler_property_data_archive_keys', function ( $keys ) {
	return array_values( array_diff( (array) $keys, [ 'objektnr_extern' ] ) );
}, 20 );

// Render Objekt-ID in grey/small font after price in archive cards.
add_action( 'immomakler_archive_property_details_bottom', function () {
	$post_id  = get_the_ID();
	if ( ! $post_id ) {
		return;
	}
	$objektnr = trim( (string) get_post_meta( $post_id, 'objektnr_extern', true ) );
	if ( $objektnr === '' ) {
		return;
	}
	echo '<div class="woonwoon-objektnr notranslate" translate="no">Objekt-ID: ' . esc_html( $objektnr ) . '</div>';
} );

/* ------------------------------------------------------------
 * Single page: subtitle + "merken" label
 * ------------------------------------------------------------ */

// Subtitle formatting:
// - Single: unverändert (Plugin-Standard, inkl. "zur Miete" -> "Wohnen auf Zeit")
// - Archive: "Straße, PLZ Ort, Bezirk" (z.B. "Zimmerstraße, 13507 Berlin, Tegel")
add_filter( 'immomakler_property_subtitle', function ( $subtitle ) {
	$subtitle = (string) $subtitle;

	// Single-Seite: nichts ändern.
	if ( function_exists( 'is_immomakler_single' ) && is_immomakler_single() ) {
		return $subtitle;
	}

	$post_id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
	if ( $post_id <= 0 ) {
		return $subtitle;
	}

	$plz = trim( (string) get_post_meta( $post_id, 'plz', true ) );
	$ort = trim( (string) get_post_meta( $post_id, 'ort', true ) );

	$regionaler_zusatz = trim( (string) get_post_meta( $post_id, 'regionaler_zusatz', true ) );
	$district          = function_exists( 'woonwoon_clean_regionaler_zusatz_for_address' )
		? woonwoon_clean_regionaler_zusatz_for_address( $regionaler_zusatz )
		: $regionaler_zusatz;

	// Fallback: sublocality wenn kein regionaler_zusatz vorhanden.
	if ( $district === '' ) {
		$sublocality = trim( (string) get_post_meta( $post_id, 'sublocality', true ) );
		if ( $sublocality !== '' && strpos( $ort, $sublocality ) === false ) {
			$district = $sublocality;
		}
	}

	$base_city = trim( $plz . ' ' . $ort );
	if ( $base_city === '' ) {
		return $subtitle;
	}

	// Straße (ohne Hausnummer im Archiv).
	// hide_street wird hier bewusst ignoriert – Straße soll im Archiv sichtbar sein.
	$street = '';
	if ( ! apply_filters( 'immomakler_hide_address', false ) ) {
		$street = trim( (string) get_post_meta( $post_id, 'strasse', true ) );
	}

	$parts = [];
	if ( $street !== '' ) {
		$parts[] = $street;
	}
	$parts[] = $base_city;
	if ( $district !== '' && $district !== $ort ) {
		$parts[] = $district;
	}

	return implode( ', ', $parts );
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

	// Verfügbar ab (availability date or free text like "ab sofort") from container.
	// Store all values for display; date search only filters when value is YYYY-MM-DD.
	$va_raw = isset( $data['verfuegbar_ab'] ) ? (string) $data['verfuegbar_ab'] : '';
	$va_raw = trim( $va_raw );
	if ( $va_raw !== '' ) {
		update_post_meta( $post_id, 'verfuegbar_ab', $va_raw );
	} else {
		delete_post_meta( $post_id, 'verfuegbar_ab' );
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

/**
 * Backfill `verfuegbar_ab` from the container meta (admin only).
 * Uses a temporary meta key to avoid reprocessing objects that have no verfuegbar_ab in the container.
 */
add_action( 'admin_init', function () {
	if ( get_option( 'woonwoon_verfuegbar_ab_from_container_migrated' ) ) {
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
					'key'     => '_woonwoon_verfuegbar_ab_migration_done',
					'compare' => 'NOT EXISTS',
				],
			],
		]
	);

	if ( empty( $q->posts ) ) {
		update_option( 'woonwoon_verfuegbar_ab_from_container_migrated', 1 );
		return;
	}

	foreach ( $q->posts as $pid ) {
		woonwoon_mirror_from_immomakler_metadata( (int) $pid );
		update_post_meta( (int) $pid, '_woonwoon_verfuegbar_ab_migration_done', '1' );
	}
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
	echo '<div class="range-label">' . esc_html__( 'Zimmer', 'immomakler' ) . '</div>';
	echo '<select class="selectpicker form-control" name="zimmer_multi[]" multiple data-width="100%" data-actions-box="false" data-selected-text-format="count > 1" data-count-selected-text="' . esc_attr__( '{0} ausgewählt', 'immomakler-child-skin' ) . '" title="' . esc_attr__( 'Zimmer auswählen', 'immomakler-child-skin' ) . '" data-track="immomakler_filter_rooms">';
	foreach ( $options as $value => $label ) {
		$is_selected = in_array( (string) $value, $selected, true ) ? ' selected' : '';
		$lbl = ( $value === '5plus' ) ? '5+' : $label;
		echo '<option value="' . esc_attr( (string) $value ) . '"' . $is_selected . '>' . esc_html( $lbl ) . '</option>';
	}
	echo '</select>';
	echo '</fieldset>';

	// Area (m²) - second column
	echo '<fieldset class="immomakler-search-range woonwoon-filter-field woonwoon-filter-area">';
	echo '<div class="range-label">' . esc_html__( 'Fläche', 'immomakler' ) . ' (m²)</div>';
	echo '<div class="woonwoon-minmax">';
	echo '<input class="form-control" type="number" inputmode="numeric" min="0" step="1" name="von-qm" placeholder="' . esc_attr__( 'Min', 'immomakler' ) . '" value="' . esc_attr( $qm_min ) . '" data-track="immomakler_filter_area_min">';
	echo '<input class="form-control" type="number" inputmode="numeric" min="0" step="1" name="bis-qm" placeholder="' . esc_attr__( 'Max', 'immomakler' ) . '" value="' . esc_attr( $qm_max ) . '" data-track="immomakler_filter_area_max">';
	echo '</div>';
	echo '</fieldset>';

	// Rent (EUR) - third column (pauschalmiete_numeric)
	echo '<fieldset class="immomakler-search-range woonwoon-filter-field woonwoon-filter-rent">';
	echo '<div class="range-label">' . esc_html__( 'Miete', 'immomakler' ) . ' (' . esc_html( $currency ) . ')</div>';
	echo '<div class="woonwoon-minmax">';
	echo '<input class="form-control" type="number" inputmode="numeric" min="0" step="50" name="von-pauschalmiete" placeholder="' . esc_attr__( 'Min', 'immomakler' ) . '" value="' . esc_attr( $rent_min ) . '" data-track="immomakler_filter_rent_min">';
	echo '<input class="form-control" type="number" inputmode="numeric" min="0" step="50" name="bis-pauschalmiete" placeholder="' . esc_attr__( 'Max', 'immomakler' ) . '" value="' . esc_attr( $rent_max ) . '" data-track="immomakler_filter_rent_max">';
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
	echo '<select class="selectpicker form-control" name="bezirk[]" multiple data-width="100%" data-actions-box="false" data-live-search="true" data-selected-text-format="count > 1" data-count-selected-text="' . esc_attr__( '{0} ausgewählt', 'immomakler-child-skin' ) . '" title="' . esc_attr__( 'Bezirk wählen', 'immomakler-child-skin' ) . '" data-track="immomakler_filter_bezirk">';
	foreach ( $bezirk_options as $v ) {
		$is_selected = in_array( (string) $v, $bezirk_selected, true ) ? ' selected' : '';
		echo '<option value="' . esc_attr( (string) $v ) . '"' . $is_selected . '>' . esc_html( (string) $v ) . '</option>';
	}
	echo '</select>';
	echo '</fieldset>';

	// Verfügbar ab (availability date) – from immomakler_metadata.verfuegbar_ab (YYYY-MM-DD)
	$verfuegbar_ab = '';
	if ( isset( $_GET['verfuegbar_ab'] ) ) {
		$verfuegbar_ab = sanitize_text_field( (string) wp_unslash( $_GET['verfuegbar_ab'] ) );
	} elseif ( isset( $_POST['verfuegbar_ab'] ) ) {
		$verfuegbar_ab = sanitize_text_field( (string) wp_unslash( $_POST['verfuegbar_ab'] ) );
	}
	if ( $verfuegbar_ab !== '' && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $verfuegbar_ab ) ) {
		$verfuegbar_ab = '';
	}
	echo '<fieldset class="immomakler-search-range woonwoon-filter-field woonwoon-filter-verfuegbar-ab">';
	echo '<div class="range-label">' . esc_html__( 'Verfügbar ab', 'immomakler-child-skin' ) . '</div>';
	echo '<input class="form-control" type="date" name="verfuegbar_ab" value="' . esc_attr( $verfuegbar_ab ) . '" data-track="immomakler_filter_verfuegbar_ab">';
	echo '</fieldset>';

	// Freitext-Suche über mehrere Felder (Objekt-ID, Adresse, Bezirk, etc.)
	$search_keyword = '';
	if ( isset( $_GET['woonwoon_q'] ) ) {
		$search_keyword = sanitize_text_field( (string) wp_unslash( $_GET['woonwoon_q'] ) );
	} elseif ( isset( $_POST['woonwoon_q'] ) ) {
		$search_keyword = sanitize_text_field( (string) wp_unslash( $_POST['woonwoon_q'] ) );
	}

	echo '<fieldset class="immomakler-search-range woonwoon-filter-field woonwoon-filter-objektid">';
	echo '<div class="range-label">' . esc_html__( 'Suche', 'immomakler-child-skin' ) . '</div>';
	echo '<div class="woonwoon-minmax">';
	echo '<input class="form-control" type="text" name="woonwoon_q" placeholder="' . esc_attr__( 'ID, Straße, Ort, Objekttitel oder Merkmal', 'immomakler-child-skin' ) . '" value="' . esc_attr( $search_keyword ) . '" data-track="immomakler_search_freitext" autocomplete="off">';
	echo '</div>';
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

/**
 * Build spelling variants for German freetext search (ß/ss, Straße/Strasse, umlauts ↔ ae/oe/ue).
 *
 * @return string[] Unique non-empty strings (original first when present).
 */
function woonwoon_german_freitext_variants( string $keyword ): array {
	$keyword = trim( $keyword );
	if ( $keyword === '' ) {
		return [];
	}

	$out = [];
	$add = static function ( string $v ) use ( &$out ): void {
		$v = trim( $v );
		if ( $v !== '' && ! in_array( $v, $out, true ) ) {
			$out[] = $v;
		}
	};

	$add( $keyword );

	// Umlauts + ß -> ASCII-style digraphs (Drakestraße -> Drakestrasse, Köln -> Koeln).
	$from_u = [ 'ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß' ];
	$to_a   = [ 'ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss' ];
	$folded = str_replace( $from_u, $to_a, $keyword );
	$add( $folded );

	// Kein globales ae/oe/ue -> Umlaut (zerstört z. B. "Goethestrasse").
	// Stattdessen: häufige reine ASCII-Schreibweisen von Ortsnamen (exakter String, case-insensitive).
	$ascii_city = mb_strtolower( $keyword, 'UTF-8' );
	$city_map   = apply_filters(
		'woonwoon_german_freitext_ascii_city_map',
		[
			'koeln'       => 'Köln',
			'muenchen'    => 'München',
			'nuernberg'   => 'Nürnberg',
			'duesseldorf' => 'Düsseldorf',
			'fuessen'     => 'Füssen',
			'ruesselsheim'=> 'Rüsselsheim',
		]
	);
	if ( isset( $city_map[ $ascii_city ] ) ) {
		$add( $city_map[ $ascii_city ] );
	}

	// Straße / Strasse (word boundary).
	if ( preg_match( '/\bstrasse\b/iu', $keyword ) ) {
		$add( preg_replace( '/\bstrasse\b/iu', 'straße', $keyword ) );
		$add( preg_replace( '/\bstrasse\b/iu', 'Straße', $keyword ) );
	}
	if ( preg_match( '/\bstraße\b/iu', $keyword ) ) {
		$add( preg_replace( '/\bstraße\b/iu', 'strasse', $keyword ) );
		$add( preg_replace( '/\bstraße\b/iu', 'Strasse', $keyword ) );
	}

	// Plural / compound: …strassen… -> …straßen…
	if ( preg_match( '/strassen/iu', $keyword ) && ! preg_match( '/straße/iu', $keyword ) ) {
		$add( preg_replace( '/strassen/iu', 'straßen', $keyword ) );
		$add( preg_replace( '/strassen/iu', 'Straßen', $keyword ) );
	}

	// ß <-> ss (word-internal), avoids touching "Strasse" already handled above.
	if ( strpos( $keyword, 'ß' ) !== false || strpos( $keyword, 'ẞ' ) !== false ) {
		$add( str_replace( [ 'ß', 'ẞ' ], [ 'ss', 'SS' ], $keyword ) );
	}
	if ( strpos( $keyword, 'ß' ) === false && strpos( $keyword, 'ss' ) !== false
		&& ! preg_match( '/\bstrasse\b/iu', $keyword ) ) {
		$try = preg_replace( '/(?<=\p{L})ss(?=\p{L})/u', 'ß', $keyword );
		if ( is_string( $try ) && $try !== $keyword ) {
			$add( $try );
		}
	}

	// Strip combining marks (Köln -> Koln) to match ASCII-only stored values.
	if ( function_exists( 'normalizer_normalize' ) && class_exists( 'Normalizer', false ) ) {
		$n = normalizer_normalize( $keyword, \Normalizer::FORM_D );
		if ( is_string( $n ) && $n !== '' ) {
			$stripped = preg_replace( '/\p{M}/u', '', $n );
			if ( is_string( $stripped ) ) {
				$add( $stripped );
			}
		}
	}

	/**
	 * Filter: add or remove variants (e.g. site-specific synonyms).
	 *
	 * @param string[] $variants
	 * @return string[]
	 */
	return apply_filters( 'woonwoon_german_freitext_variants', $out, $keyword );
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
	 *
	 * IMPORTANT for TranslatePress:
	 * We always use the real taxonomy term slug `wohnung` here, independent
	 * of the current UI language. Using I18n_Helper would generate a slug
	 * from the translated label (e.g. "Apartment" -> "apartment") on /en/,
	 * which does not match the stored term slug and would result in 0 hits.
	 */
	$taxonomy    = apply_filters( 'immomakler_property_type_taxonomy', 'immomakler_object_type' );
	$wohnung_slug = 'wohnung';

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
	// Also allow passing zimmer_multi via WP_Query args (e.g. from shortcodes).
	if ( empty( $zimmer_multi ) ) {
		$zimmer_from_query = $query->get( 'zimmer_multi' );
		if ( is_string( $zimmer_from_query ) && $zimmer_from_query !== '' ) {
			$zimmer_multi = explode( ',', $zimmer_from_query );
		} elseif ( is_array( $zimmer_from_query ) ) {
			$zimmer_multi = $zimmer_from_query;
		}
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

	/**
	 * Verfügbar ab (availability date):
	 * - Uses GET/POST param `verfuegbar_ab` (YYYY-MM-DD)
	 * - Filters meta_key `verfuegbar_ab` (mirrored from immomakler_metadata)
	 * - Shows objects available from the selected date or later
	 * - Includes text values: "ab sofort" (= from now), "bis Ende des Jahres" (= available until end of year)
	 */
	$verfuegbar_ab_val = $query->get( 'verfuegbar_ab' );
	if ( $verfuegbar_ab_val === null || $verfuegbar_ab_val === '' ) {
		$verfuegbar_ab_val = isset( $_GET['verfuegbar_ab'] ) ? trim( (string) wp_unslash( $_GET['verfuegbar_ab'] ) ) : '';
	}
	if ( $verfuegbar_ab_val === null || $verfuegbar_ab_val === '' ) {
		$verfuegbar_ab_val = isset( $_POST['verfuegbar_ab'] ) ? trim( (string) wp_unslash( $_POST['verfuegbar_ab'] ) ) : '';
	}
	if ( $verfuegbar_ab_val !== '' && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $verfuegbar_ab_val ) ) {
		$meta_query[] = [
			'relation' => 'OR',
			[
				'key'     => 'verfuegbar_ab',
				'value'   => $verfuegbar_ab_val,
				'type'    => 'DATE',
				'compare' => '>=',
			],
			[
				'key'     => 'verfuegbar_ab',
				'value'   => [ 'ab sofort', 'sofort' ],
				'compare' => 'IN',
			],
			[
				'key'     => 'verfuegbar_ab',
				'value'   => 'bis Ende',
				'compare' => 'LIKE',
			],
		];
	}

	/**
	 * Freitext-Suche:
	 * - Uses GET/POST param `woonwoon_q`
	 * - Matches:
	 *   - Objekt-ID Felder (objektnr_extern_normalized, objektnr_extern, objektnr_intern) per RLIKE Prefix
	 *   - Adresse / Standort / Titel per LIKE, mit Varianten für ß/ss, Straße/Strasse, Umlaute ↔ ae/oe/ue (siehe woonwoon_german_freitext_variants)
	 *   - Gesamte immomakler_metadata (Fallback)
	 */
	$keyword = $query->get( 'woonwoon_q' );
	if ( $keyword === null || $keyword === '' ) {
		if ( isset( $_GET['woonwoon_q'] ) ) {
			$keyword = wp_unslash( $_GET['woonwoon_q'] );
		} elseif ( isset( $_POST['woonwoon_q'] ) ) {
			$keyword = wp_unslash( $_POST['woonwoon_q'] );
		}
	}
	$keyword = is_string( $keyword ) ? trim( (string) $keyword ) : '';
	$keyword = sanitize_text_field( $keyword );

	if ( $keyword !== '' ) {
		$or_search = [ 'relation' => 'OR' ];

		// Objekt-ID Suche (ähnlich wie das Plugin, aber ohne Redirect).
		if ( class_exists( '\ImmoMakler\Helpers\String_Helper' ) ) {
			$normalized = \ImmoMakler\Helpers\String_Helper::normalize_objektnr_extern( $keyword );
			$pattern    = apply_filters( 'immomakler_search_object_id_rlike', '^%s' );

			if ( $normalized !== '' ) {
				$or_search[] = [
					'key'     => 'objektnr_extern_normalized',
					'value'   => sprintf( $pattern, $normalized ),
					'compare' => 'RLIKE',
				];
			}

			// Fallback auf rohe Objekt-ID Felder.
			$pattern_raw = sprintf( $pattern, $keyword );
			$or_search[] = [
				'key'     => apply_filters( 'immomakler_search_for_id_meta_key', 'objektnr_extern' ),
				'value'   => $pattern_raw,
				'compare' => 'RLIKE',
			];
			$or_search[] = [
				'key'     => 'objektnr_intern',
				'value'   => $pattern_raw,
				'compare' => 'RLIKE',
			];
		}

		$text_variants = woonwoon_german_freitext_variants( $keyword );

		// Adresse, Ort, Titel, Bezirk (LIKE) — alle Schreibvarianten (ß/ss, Umlaute, …).
		$like_meta_keys = [
			'strasse',
			'hausnummer',
			'plz',
			'ort',
			'objekttitel',
			'regionaler_zusatz_clean',
			'regionaler_zusatz',
		];
		foreach ( $text_variants as $variant ) {
			foreach ( $like_meta_keys as $meta_key ) {
				$or_search[] = [
					'key'     => $meta_key,
					'value'   => $variant,
					'compare' => 'LIKE',
				];
			}
		}

		// Fallback: gesamtes Metadata-Containerfeld durchsuchen.
		foreach ( $text_variants as $variant ) {
			$or_search[] = [
				'key'     => 'immomakler_metadata',
				'value'   => $variant,
				'compare' => 'LIKE',
			];
		}

		if ( count( $or_search ) > 1 ) {
			$meta_query[] = $or_search;
		}
	}

	$query->set( 'meta_query', $meta_query );
}

/* ------------------------------------------------------------
 * Shortcode: Region apartments (max N cards, same as archive)
 * ------------------------------------------------------------
 * Delegates to [immomakler-archive]; pre_get_posts adds Wohnung filter automatically.
 * Usage: [woonwoon_region_apartments region="Schöneberg"]
 *        [woonwoon_region_apartments region="Kreuzberg" limit="6" columns="2"]
 */

add_action( 'init', function () {
	add_shortcode( 'woonwoon_region_apartments', 'woonwoon_shortcode_region_apartments' );
} );

/**
 * Shortcode callback: output [immomakler-archive] filtered by region (regionaler_zusatz_clean).
 * Wohnung-only is applied by woonwoon_search_pre_get_posts when the plugin runs its query.
 *
 * @param array<string,string> $atts Shortcode attributes: region (required), limit (default 4), columns (optional).
 * @return string HTML output.
 */
function woonwoon_shortcode_region_apartments( $atts = [] ): string {
	$atts = shortcode_atts(
		[
			'region'  => '',
			'limit'   => '4',
			'columns' => '',
		],
		$atts,
		'woonwoon_region_apartments'
	);

	$region = trim( (string) $atts['region'] );
	if ( $region === '' ) {
		return '';
	}

	$limit = absint( $atts['limit'] );
	if ( $limit < 1 ) {
		$limit = 4;
	}

	// Delegate to plugin shortcode; pre_get_posts adds Wohnung filter to its query.
	$sc = '[immomakler-archive meta_key="regionaler_zusatz_clean" meta_value="' . esc_attr( $region ) . '" limit="' . $limit . '"';
	if ( $atts['columns'] !== '' && absint( $atts['columns'] ) > 0 ) {
		$sc .= ' columns="' . absint( $atts['columns'] ) . '"';
	}
	$sc .= ']';

	return do_shortcode( $sc );
}

/* ------------------------------------------------------------
 * Shortcode: Zimmer apartments (filter by room count)
 * ------------------------------------------------------------
 * Delegates to [immomakler-archive]; wohnung-only + room filter is applied
 * by woonwoon_search_pre_get_posts when the plugin runs its query.
 *
 * Usage examples:
 *   [woonwoon_zimmer_apartments zimmer="1" limit="4"]
 *   [woonwoon_zimmer_apartments zimmer="2,3" limit="4" columns="2"]
 *   [woonwoon_zimmer_apartments zimmer="5plus" limit="4"]
 */

add_action( 'init', function () {
	add_shortcode( 'woonwoon_zimmer_apartments', 'woonwoon_shortcode_zimmer_apartments' );
} );

/**
 * Shortcode callback: output [immomakler-archive] filtered by room counts.
 * Uses internal query var `zimmer_multi` which is interpreted by
 * woonwoon_search_pre_get_posts (supports values like "1,2,5plus").
 *
 * @param array<string,string> $atts Shortcode attributes: zimmer (required), limit (default 4), columns (optional).
 * @return string HTML output.
 */
function woonwoon_shortcode_zimmer_apartments( $atts = [] ): string {
	$atts = shortcode_atts(
		[
			'zimmer'  => '',
			'limit'   => '4',
			'columns' => '',
		],
		$atts,
		'woonwoon_zimmer_apartments'
	);

	$zimmer_raw = trim( (string) $atts['zimmer'] );
	if ( $zimmer_raw === '' ) {
		return '';
	}

	$parts = array_values(
		array_unique(
			array_filter(
				array_map(
					static function ( $v ) {
						return sanitize_text_field( trim( (string) $v ) );
					},
					explode( ',', $zimmer_raw )
				),
				static function ( $v ) {
					return $v !== '';
				}
			)
		)
	);

	if ( empty( $parts ) ) {
		return '';
	}

	$limit = absint( $atts['limit'] );
	if ( $limit < 1 ) {
		$limit = 4;
	}

	// Delegate to plugin shortcode; pre_get_posts adds Wohnung + zimmer_multi filter.
	$zimmer_multi_value = implode( ',', $parts );
	$sc                 = '[immomakler-archive limit="' . $limit . '" zimmer_multi="' . esc_attr( $zimmer_multi_value ) . '"';
	if ( $atts['columns'] !== '' && absint( $atts['columns'] ) > 0 ) {
		$sc .= ' columns="' . absint( $atts['columns'] ) . '"';
	}
	$sc .= ']';

	return do_shortcode( $sc );
}
