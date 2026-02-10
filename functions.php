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
		'label'       => 'Pauschalmiete',
		'slug'        => 'pauschalmiete',
		'unit'        => '&euro;',
		'decimals'    => 0,
		'meta_key'    => 'kaltmiete',
		'slider_step' => 100,
	);

	return $ranges;
}

/**
 * Suche: Dropdown-Taxonomien einschränken (nur Verfügbarkeit, keine Kauf/Miete etc.).
 *
 * Doku:
 * https://www.wp-immomakler.de/docs-artikel/suchmaske-im-wp-immomakler-customizer-individuell-gestalten
 */
add_filter( 'immomakler_search_enabled_taxonomies', 'immomakler_child_skin_search_taxonomies' );
function immomakler_child_skin_search_taxonomies( $taxonomies ) {
	// Zeige nur noch Verfügbarkeit/Status in der Suchmaske.
	// Folgende Dropdowns werden damit entfernt:
	// - immomakler_object_vermarktung (Kauf/Miete)
	// - immomakler_object_nutzungsart
	// - immomakler_object_type
	// - immomakler_object_location (Ort)
	return array(
		'immomakler_object_status',
	);
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

