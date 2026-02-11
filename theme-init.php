<?php
/*
Plugin Name: WP-ImmoMakler Child Skin
Plugin URI: https://www.wp-immomakler.de
Description: Child Skin for WP-ImmoMakler®
Version: 1.5
Author: 49heroes GmbH & Co. KG
Author URI: https://49heroes.com
Text Domain: immomakler-child-skin
License: (c) 2015-2023, 49heroes GmbH & Co. KG. GPLv2
*/

! defined( 'ABSPATH' ) and exit;

add_filter( 'immomakler_available_skins', 'immomakler_child_skin_add' );
function immomakler_child_skin_add( $skins ) {
	$skins['child_skin'] = array(
	                            'name'          => 'Custom woonwoon Child Skin', // kann z.B. durch den Namen des Maklerbüros ersetzt werden
	                            'parent_id'     => 'bootstrap3',
	                            'path'          => plugin_dir_path( __FILE__ ),
	                            'url'           => plugin_dir_url( __FILE__ ),
	                            'use_theme_dir' => true
	                        );
	return $skins;
}
