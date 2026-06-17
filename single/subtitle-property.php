<?php
/**
 * Property subtitle (address/street line) – child override.
 * Wraps subtitle in notranslate so automatic translation does not translate it.
 */
if ( immomakler_property_subtitle() ) : ?>
<h2 class="property-subtitle notranslate" translate="no"><span class="glyphicon glyphicon-map-marker"></span> <?php echo immomakler_property_subtitle(); ?></h2>
<?php endif; ?>
