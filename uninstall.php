<?php
/**
 * Fired when the plugin is uninstalled.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin option.
delete_option( 'apo_settings' );

// Delete all per-term order meta entries.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk cleanup on uninstall, no caching needed.
$wpdb->delete( $wpdb->termmeta, [ 'meta_key' => '_apo_order' ] );

// Delete stale transients.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk cleanup on uninstall.
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_apo_stale_%' OR option_name LIKE '_transient_timeout_apo_stale_%'"
);
