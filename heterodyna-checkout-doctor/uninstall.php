<?php
/** Permanently delete local data after uninstall, never on deactivation. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }
global $wpdb;
$table = $wpdb->prefix . 'hcd_events';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
delete_option( 'hcd_monitoring_enabled' );
delete_option( 'hcd_retention_days' );
delete_option( 'hcd_last_scan' );
delete_option( 'hcd_integration_tests' );
delete_option( 'hcd_browser_observer_enabled' );
delete_option( 'hcd_payment_visibility_enabled' );
delete_option( 'hcd_store_api_observer_enabled' );
delete_transient( 'hcd_store_api_observations' );
delete_transient( 'hcd_payment_visibility_samples' );
delete_transient( 'hcd_browser_probe_samples' );
// Remove expired or active per-user lock rows when the plugin is uninstalled.
$lock_pattern = $wpdb->esc_like( 'hcd_active_scan_' ) . '%';
$wpdb->query( $wpdb->prepare(
    "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE %s",
    $lock_pattern
) );
wp_clear_scheduled_hook( 'hcd_cleanup_events' );
