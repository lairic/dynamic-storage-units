<?php
/**
 * Plugin Name: Dynamic Storage Units
 * Description: API-driven storage unit listings with WordPress controlled presentation brought to you by Metric Moose.
 * Version: 2.6.3
 * Author: Metric Moose
 * Author URI: https://metricmoose.com
 * Text Domain: dynamic-storage-units
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DSU_VERSION', '2.6.3' );
define( 'DSU_LICENSE_SERVER', 'https://dsu-license-server.vercel.app' );
define( 'DSU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DSU_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DSU_OPTION_API', 'dsu_api_settings' );
define( 'DSU_OPTION_IMAGES', 'dsu_image_mappings' );
define( 'DSU_OPTION_CONFIGS', 'dsu_display_configs' );
define( 'DSU_OPTION_CATEGORIES', 'dsu_size_categories' );
define( 'DSU_OPTION_SCHEMA', 'dsu_schema_settings' );
define( 'DSU_OPTION_FEATURE_ICONS', 'dsu_feature_icons' );
define( 'DSU_OPTION_UNIT_TYPES',    'dsu_unit_types' );
define( 'DSU_OPTION_DEFAULT_CONFIG','dsu_default_config' );
define( 'DSU_OPTION_SOURCE_MAP',    'dsu_source_map' );

function dsu_get_size_categories() {
	$cats = get_option( DSU_OPTION_CATEGORIES, null );
	if ( $cats === null || $cats === false ) {
		return [
			[ 'slug' => 'locker',       'label' => 'Locker',       'min_sqft' => 0,   'max_sqft' => 20,   'description' => 'Compact personal storage' ],
			[ 'slug' => 'small',        'label' => 'Small',        'min_sqft' => 21,  'max_sqft' => 75,   'description' => 'Size of a small closet' ],
			[ 'slug' => 'medium',       'label' => 'Medium',       'min_sqft' => 76,  'max_sqft' => 150,  'description' => 'Fits a 1-bedroom apartment' ],
			[ 'slug' => 'large',        'label' => 'Large',        'min_sqft' => 151, 'max_sqft' => 300,  'description' => 'Fits a 1–2 bedroom home' ],
			[ 'slug' => 'extra-large',  'label' => 'Extra-Large',  'min_sqft' => 301, 'max_sqft' => 9999, 'description' => 'Fits a house' ],
			[ 'slug' => 'parking',      'label' => 'Parking',      'min_sqft' => 0,   'max_sqft' => 9999, 'description' => 'Vehicle storage' ],
		];
	}
	return $cats;
}

function dsu_get_unit_types() {
	$types = get_option( DSU_OPTION_UNIT_TYPES, null );
	if ( $types === null || $types === false || ! is_array( $types ) || empty( $types ) ) {
		return [
			[ 'slug' => 'storage', 'label' => 'Storage' ],
			[ 'slug' => 'parking', 'label' => 'Parking' ],
		];
	}
	return $types;
}

require_once DSU_PLUGIN_DIR . 'includes/class-cache.php';
require_once DSU_PLUGIN_DIR . 'includes/class-api.php';
require_once DSU_PLUGIN_DIR . 'includes/class-feature-icons.php';
require_once DSU_PLUGIN_DIR . 'includes/class-waitlist.php';
require_once DSU_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once DSU_PLUGIN_DIR . 'includes/class-schema.php';
require_once DSU_PLUGIN_DIR . 'includes/class-license.php';
require_once DSU_PLUGIN_DIR . 'includes/class-updater.php';
require_once DSU_PLUGIN_DIR . 'includes/class-admin.php';

function dsu_init() {
	new DSU_Admin();
	new DSU_Shortcode();
	new DSU_Waitlist();
	new DSU_Schema();
	new DSU_Updater( __FILE__ );
}
add_action( 'plugins_loaded', 'dsu_init' );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=dynamic-storage-units' ) ) . '">' . __( 'Settings', 'dynamic-storage-units' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
} );
