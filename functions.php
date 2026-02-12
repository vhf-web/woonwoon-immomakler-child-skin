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
 */
add_filter( 'immomakler_search_enabled_taxonomies', 'woonwoon_immomakler_search_hide_taxonomies' );

function woonwoon_immomakler_search_hide_taxonomies( array $taxonomies ): array {
	$taxonomies = array_diff(
		$taxonomies,
		[
			'immomakler_object_vermarktung',
			'immomakler_object_nutzungsart',
			'immomakler_object_type',
		]
	);

	return array_values( $taxonomies );
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
 */
add_filter( 'immomakler_property_data_single_keys', 'woonwoon_immomakler_hide_single_detail_keys', 10, 2 );

function woonwoon_immomakler_hide_single_detail_keys( array $keys, $post_id ): array {
	$remove = [
		'objektnr_extern',
		'objekt_typen',
		'adresse',
		'min_mietdauer',
		'max_mietdauer',
	];

	return array_values( array_diff( $keys, $remove ) );
}

