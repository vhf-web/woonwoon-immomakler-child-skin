<?php
// Hier bei Bedarf Filter und Actions für WP-ImmoMakler eintragen.

add_action('plugins_loaded', function () {

    // Erst hier prüfen, ob WP-ImmoMakler wirklich geladen ist
    if ( ! defined('IMMOMAKLER_VERSION') ) {
        return;
    }

    /**
     * Numerische Suchbereiche (Slider) einschränken:
     * - Anzahl Zimmer
     * - Pauschalmiete
     */
    add_filter('immomakler_search_enabled_ranges', function ($ranges) {
        return array(
            'immomakler_search_rooms' => array(
                'label'       => 'Anzahl Zimmer',
                'slug'        => 'zimmer',
                'unit'        => '',
                'decimals'    => 1,
                'meta_key'    => 'anzahl_zimmer',
                'slider_step' => 0.5,
            ),
            'immomakler_search_pauschalmiete' => array(
                'label'       => 'Pauschalmiete',
                'slug'        => 'pauschalmiete',
                'unit'        => '€',
                'decimals'    => 0,
                'meta_key'    => 'pauschalmiete',
                'slider_step' => 100,
            ),
        );
    });

    /**
     * Archive Headline/Subheadline ändern
     */
    add_filter('immomakler_archive_headline', function ($headline) {
        if (is_post_type_archive('immomakler_object')) {
            return 'Unsere Immobilien';
        }
        return $headline;
    });

    add_filter('immomakler_archive_subheadline', function ($subheadline) {
        if (is_post_type_archive('immomakler_object')) {
            return 'Alle Angebote';
        }
        return $subheadline;
    });

    /**
     * Filter für regionaler_zusatz
     */
    add_action('pre_get_posts', function ($query) {
        if (is_admin() || ! $query->is_main_query()) return;

        $pt = $query->get('post_type');
        if ($pt !== 'immomakler_object' && ! (is_array($pt) && in_array('immomakler_object', $pt, true)) ) {
            return;
        }

        $regionaler_zusatz = isset($_GET['regionaler_zusatz']) ? trim(wp_unslash($_GET['regionaler_zusatz'])) : '';
        if ($regionaler_zusatz === '') return;

        $meta_query = $query->get('meta_query');
        if (!is_array($meta_query)) $meta_query = array();

        $meta_query[] = array(
            'key'     => 'regionaler_zusatz',
            'value'   => $regionaler_zusatz,
            'compare' => '=',
        );

        $query->set('meta_query', $meta_query);
    });

    /**
     * Filter für "Verfügbar ab" (>= Datum)
     */
    add_action('pre_get_posts', function ($query) {
        if (is_admin() || ! $query->is_main_query()) return;

        $pt = $query->get('post_type');
        if ($pt !== 'immomakler_object' && ! (is_array($pt) && in_array('immomakler_object', $pt, true)) ) {
            return;
        }

        $input = isset($_GET['verfuegbar_ab_min']) ? trim(wp_unslash($_GET['verfuegbar_ab_min'])) : '';
        if ($input === '') return;

        $meta_query = $query->get('meta_query');
        if (!is_array($meta_query)) $meta_query = array();

        $meta_query[] = array(
            'key'     => 'verfuegbar_ab',
            'value'   => $input,
            'type'    => 'DATE',
            'compare' => '>=',
        );

        $query->set('meta_query', $meta_query);
    });

}, 20);
