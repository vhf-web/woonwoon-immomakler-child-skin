<?php
// Debug: verify that immomakler-child-skin single template is used.
echo 'CHILD SKIN SINGLE TEMPLATE';
immomakler_get_template_part( 'header', 'single' );
?>

<?php immomakler_get_template_part( 'single/single', 'property' ); ?>

<?php immomakler_get_template_part( 'footer', 'single' );
