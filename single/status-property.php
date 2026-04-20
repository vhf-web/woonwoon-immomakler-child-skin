<?php
/**
 * Property status panel – child override.
 * Uses term slug 'offen' (and 'reserviert') for checks so the "available" state
 * works correctly with TranslatePress/English locale (plugin uses translated
 * label which breaks has_term() when locale is en).
 */
if ( ! has_term( '', 'immomakler_object_status', get_the_ID() ) ) {
	return;
}

$term_objects = wp_get_post_terms( get_the_ID(), 'immomakler_object_status', [ 'fields' => 'all' ] );
// Exclude "offen" from the displayed terms (by slug; DB slug is always 'offen').
$terms_for_heading = [];
foreach ( $term_objects as $term_object ) {
	if ( $term_object->slug === 'offen' ) {
		continue;
	}
	$terms_for_heading[] = $term_object->name;
}
$terms_for_heading = array_map( 'esc_html', $terms_for_heading );

if ( empty( $terms_for_heading ) ) {
	return;
}

$has_offen = has_term( 'offen', 'immomakler_object_status', get_the_ID() );
$has_reserviert = has_term( 'reserviert', 'immomakler_object_status', get_the_ID() );
$show_unavailable_message = ! $has_offen && ! $has_reserviert;
?>
<div class="panel panel-default property-status
<?php
foreach ( $term_objects as $term_object ) {
	echo ' property-status-' . esc_attr( $term_object->slug );
}
?>
">
	<div class="panel-heading">
		<h2><?php echo implode( ', ', $terms_for_heading ); ?></h2>
	</div>
	<?php if ( $show_unavailable_message ) : ?>
	<div class="panel-body">
		<p>
		<?php
		if ( \ImmoMakler\Data\Property_Helper::is_reference() ) {
			echo esc_html( apply_filters( 'immomakler_object_status_message', __( 'Dieses Objekt wurde bereits erfolgreich vermittelt.', 'immomakler' ), get_the_ID() ) );
		} else {
			echo esc_html( apply_filters( 'immomakler_object_status_message', __( 'Dieses Objekt ist zur Zeit leider nicht verfügbar.', 'immomakler' ), get_the_ID() ) );
		}
		?>
		</p>
	</div>
	<?php endif; ?>
</div>
