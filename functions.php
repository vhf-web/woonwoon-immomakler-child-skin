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
 * Hide "Kauf/Miete" (immomakler_object_vermarktung) from the search form.
 *
 * This uses the official search filter so the first dropdown in
 * the ImmoMakler search form no longer shows Kauf/Miete at all.
 * All your objects are Miete, so this filter is unnecessary there.
 */
add_filter( 'immomakler_search_enabled_taxonomies', 'woonwoon_immomakler_search_hide_vermarktung' );

function woonwoon_immomakler_search_hide_vermarktung( array $taxonomies ): array {
	$taxonomies = array_diff( $taxonomies, [ 'immomakler_object_vermarktung' ] );
	return array_values( $taxonomies );
}


