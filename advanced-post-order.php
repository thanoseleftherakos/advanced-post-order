<?php
/**
 * Plugin Name:       Advanced Post Order
 * Plugin URI:        https://wordpress.org/plugins/advanced-post-order/
 * Description:       Drag-and-drop post ordering with per-taxonomy-term support. Reorder posts globally or within specific categories/tags.
 * Version:           1.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Bracket
 * Author URI:        https://bracket.gr
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       advanced-post-order
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'APO_VERSION', '1.1.0' );
define( 'APO_PATH', plugin_dir_path( __FILE__ ) );
define( 'APO_URL', plugin_dir_url( __FILE__ ) );

require_once APO_PATH . 'includes/class-apo-core.php';
require_once APO_PATH . 'includes/class-apo-settings.php';
require_once APO_PATH . 'includes/class-apo-ajax.php';
require_once APO_PATH . 'includes/class-apo-admin.php';
require_once APO_PATH . 'includes/class-apo-query.php';

/**
 * Initialize plugin on plugins_loaded.
 */
function apo_init() {
	APO_Settings::init();
	APO_Ajax::init();
	APO_Admin::init();
	APO_Query::init();

	// WPML compatibility.
	if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
		require_once APO_PATH . 'includes/class-apo-compat-wpml.php';
		APO_Compat_WPML::init();
	}

	// Polylang compatibility.
	if ( function_exists( 'pll_current_language' ) ) {
		require_once APO_PATH . 'includes/class-apo-compat-polylang.php';
		APO_Compat_Polylang::init();
	}
}
add_action( 'plugins_loaded', 'apo_init' );

/**
 * Activation hook — add term_order column if missing.
 */
function apo_activate() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time activation check, no caching needed.
	$row = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->terms} LIKE 'term_order'" );
	if ( empty( $row ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required schema change on activation.
		$wpdb->query( "ALTER TABLE {$wpdb->terms} ADD `term_order` INT(4) NULL DEFAULT '0'" );
	}
}
register_activation_hook( __FILE__, 'apo_activate' );

/**
 * Add Settings link to plugin action links.
 *
 * @param array $links Existing links.
 * @return array
 */
function apo_plugin_action_links( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=advanced-post-order' ) ) . '">'
		. esc_html__( 'Settings', 'advanced-post-order' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'apo_plugin_action_links' );
