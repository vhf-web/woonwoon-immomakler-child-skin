<?php
// Hier bei Bedarf Filter und Actions für WP-ImmoMakler eintragen

! defined( 'ABSPATH' ) and exit;

/**
 * Suche: "Mehr Optionen" (Ranges) um Zimmer & Preis konfigurieren.
 *
 * Hinweis: WP-ImmoMakler blendet je nach Auswahl (Kauf/Miete) automatisch
 * den jeweils nicht passenden Preis-Filter aus (Kaufpreis vs. Kaltmiete).
 *
 * Doku:
 * https://www.wp-immomakler.de/docs-artikel/suchmaske-im-wp-immomakler-customizer-individuell-gestalten
 */
add_filter( 'immomakler_search_enabled_ranges', 'immomakler_child_skin_search_ranges' );
function immomakler_child_skin_search_ranges( $ranges ) {
	if ( ! is_array( $ranges ) ) {
		$ranges = array();
	}

	$ranges['immomakler_search_rooms'] = array(
		'label'       => 'Zimmer',
		'slug'        => 'zimmer',
		'unit'        => '',
		'decimals'    => 1,
		'meta_key'    => 'anzahl_zimmer',
		'slider_step' => 0.5,
	);

	// Preis (Miete)
	$ranges['immomakler_search_price_rent'] = array(
		'label'       => 'Preis',
		'slug'        => 'kaltmiete',
		'unit'        => '&euro;',
		'decimals'    => 0,
		'meta_key'    => 'kaltmiete',
		'slider_step' => 100,
	);

	return $ranges;
}

/**
 * Suche: Dropdown-Reihenfolge/aktivierte Taxonomien ergänzen (Ort & Verfügbarkeit).
 *
 * Doku:
 * https://www.wp-immomakler.de/docs-artikel/suchmaske-im-wp-immomakler-customizer-individuell-gestalten
 */
add_filter( 'immomakler_search_enabled_taxonomies', 'immomakler_child_skin_search_taxonomies' );
function immomakler_child_skin_search_taxonomies( $taxonomies ) {
	if ( ! is_array( $taxonomies ) ) {
		$taxonomies = array();
	}

	$required = array(
		'immomakler_object_location', // Ort
		'immomakler_object_status',   // Verfügbarkeit / Status
	);

	foreach ( $required as $taxonomy ) {
		if ( ! in_array( $taxonomy, $taxonomies, true ) ) {
			$taxonomies[] = $taxonomy;
		}
	}

	return $taxonomies;
}

/**
 * Optional: Label in der Suche/Backend von "Status" auf "Verfügbarkeit" anpassen
 * (nur Beschriftung; Slugs/Rewrite bleiben unverändert).
 */
add_filter( 'immomakler_taxomomy_immomakler_object_status_args', 'immomakler_child_skin_status_taxonomy_labels' );
function immomakler_child_skin_status_taxonomy_labels( $args ) {
	if ( ! is_array( $args ) ) {
		$args = array();
	}

	$labels = isset( $args['labels'] ) && is_array( $args['labels'] ) ? $args['labels'] : array();
	$labels['name']          = 'Verfügbarkeit';
	$labels['singular_name'] = 'Verfügbarkeit';

	$args['labels'] = $labels;
	return $args;
}

/**
 * Listenansicht: Anzahl Spalten (Immobilien pro Zeile) im Archiv.
 *
 * Mit diesem Filter wird die Anzahl der Spalten auf 4 gesetzt.
 * WP-ImmoMakler berechnet daraus passende Bootstrap-Klassen (z.B. col-md-3).
 */
add_filter( 'immomakler_number_of_columns_archive', 'immomakler_child_skin_number_of_columns_archive' );
function immomakler_child_skin_number_of_columns_archive( $cols ) {
	return 4;
}
