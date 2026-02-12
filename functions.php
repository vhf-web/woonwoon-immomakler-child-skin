<?php

/**
 * Child skin helper functions for WOONWOON.
 */

/**
 * Force ImmoMakler titles to a fixed label.
 *
 * This affects the big H1 title in the ImmoMakler templates
 * and also places where immomakler_single_title() /
 * immomakler_archive_title() are used.
 */
add_filter( 'immomakler_single_title', 'woonwoon_immomakler_title_override' );
add_filter( 'immomakler_archive_title', 'woonwoon_immomakler_title_override' );

/**
 * @param string $title Original title.
 *
 * @return string
 */
function woonwoon_immomakler_title_override( string $title ): string {
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

