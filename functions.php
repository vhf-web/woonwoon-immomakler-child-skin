<?php
add_filter( 'body_class', 'immomakler_body_classes' );
/**
 * @param string[] $classes
 *
 * @return string[]
 */
function immomakler_body_classes( array $classes ): array {
	if ( is_immomakler_page() ) {
		$classes[] = 'immomakler-page bla bla bla';
	}

	return $classes;
}


