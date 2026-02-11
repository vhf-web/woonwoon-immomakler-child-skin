<?php
add_action('plugins_loaded', function () {

    // nur wenn WP-ImmoMakler aktiv ist
    if ( ! defined('IMMOMAKLER_VERSION') ) {
        return;
    }

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

}, 20);
