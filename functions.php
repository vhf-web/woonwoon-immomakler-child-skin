<?php
add_action('admin_notices', function () {
    echo '<div class="notice notice-info"><p><strong>ChildSkin:</strong> functions.php ist geladen.</p></div>';
});



add_action('plugins_loaded', function () {
    if ( ! defined('IMMOMAKLER_VERSION') ) return;

    add_filter('immomakler_archive_headline', function ($headline) {
        return 'Unsere Immobilien';
    });

    add_filter('immomakler_archive_subheadline', function ($sub) {
        return 'Alle Angebote';
    });

}, 99);

