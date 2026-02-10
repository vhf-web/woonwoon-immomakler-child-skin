<?php
// Hier bei Bedarf Filter und Actions für WP-ImmoMakler eintragen.

// Sicherstellen, dass WP-ImmoMakler geladen ist, bevor wir seine Hooks/Meta-Felder nutzen.
if ( defined( 'IMMOMAKLER_VERSION' ) ) {

	/**
	 * Numerische Suchbereiche (Slider) einschränken:
	 * - Anzahl Zimmer
	 * - Pauschalmiete
	 */
	if ( ! function_exists( 'my_immomakler_search_ranges' ) ) {
		add_filter( 'immomakler_search_enabled_ranges', 'my_immomakler_search_ranges' );
		function my_immomakler_search_ranges( $ranges ) {
			return array(
				'immomakler_search_rooms'         => array(
					'label'       => 'Anzahl Zimmer',
					'slug'        => 'zimmer',       // URL-Parameter: von-zimmer / bis-zimmer.
					'unit'        => '',
					'decimals'    => 1,
					'meta_key'    => 'anzahl_zimmer',
					'slider_step' => 0.5,
				),
				'immomakler_search_pauschalmiete' => array(
					'label'       => 'Pauschalmiete',
					'slug'        => 'pauschalmiete', // URL-Parameter: von-pauschalmiete / bis-pauschalmiete.
					'unit'        => '€',
					'decimals'    => 0,
					'meta_key'    => 'pauschalmiete',
					'slider_step' => 100,
				),
			);
		}
	}

	/**
	 * Filter für regionaler_zusatz (feste Liste / Dropdown).
	 * Erwartet ein Formularfeld: <select name="regionaler_zusatz">…</select>.
	 */
	if ( ! function_exists( 'my_immomakler_filter_by_regionaler_zusatz' ) ) {
		add_action( 'pre_get_posts', 'my_immomakler_filter_by_regionaler_zusatz' );
		function my_immomakler_filter_by_regionaler_zusatz( $query ) {
			if ( is_admin() || ! $query->is_main_query() ) {
				return;
			}

			if ( $query->get( 'post_type' ) !== 'immomakler_object' ) {
				return;
			}

			$regionaler_zusatz = isset( $_GET['regionaler_zusatz'] ) ? trim( wp_unslash( $_GET['regionaler_zusatz'] ) ) : '';

			if ( $regionaler_zusatz === '' ) {
				return;
			}

			$meta_query = $query->get( 'meta_query' );
			if ( ! is_array( $meta_query ) ) {
				$meta_query = array();
			}

			$meta_query[] = array(
				'key'     => 'regionaler_zusatz',
				'value'   => $regionaler_zusatz,
				'compare' => '=',
			);

			$query->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * Filter für "Verfügbar ab" – ab einem gewählten Datum (einschließlich).
	 * Erwartet ein Formularfeld: <input type="date" name="verfuegbar_ab_min" …>.
	 * Da die Metadaten als 'YYYY-MM-DD' gespeichert sind, können wir DATE-Vergleiche nutzen.
	 */
	if ( ! function_exists( 'my_immomakler_filter_by_verfuegbar_ab' ) ) {
		add_action( 'pre_get_posts', 'my_immomakler_filter_by_verfuegbar_ab' );
		function my_immomakler_filter_by_verfuegbar_ab( $query ) {
			if ( is_admin() || ! $query->is_main_query() ) {
				return;
			}

			if ( $query->get( 'post_type' ) !== 'immomakler_object' ) {
				return;
			}

			$input = isset( $_GET['verfuegbar_ab_min'] ) ? trim( wp_unslash( $_GET['verfuegbar_ab_min'] ) ) : '';

			if ( $input === '' ) {
				return;
			}

			// Erwartetes Format: 'YYYY-MM-DD' (z. B. 2026-04-20), passend zum gespeicherten Meta-Wert.
			$meta_query = $query->get( 'meta_query' );
			if ( ! is_array( $meta_query ) ) {
				$meta_query = array();
			}

			$meta_query[] = array(
				'key'     => 'verfuegbar_ab',
				'value'   => $input,
				'type'    => 'DATE',
				'compare' => '>=',
			);

			$query->set( 'meta_query', $meta_query );
		}
	}
}