<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** First-party, metadata-only event ledger. No billing/payment data is stored. */
final class HCD_Events {
    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'hcd_events';
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $collate = $wpdb->get_charset_collate();
        dbDelta( "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(40) NOT NULL,
            source varchar(24) NOT NULL,
            detail varchar(70) NOT NULL DEFAULT '',
            recorded_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY recorded_at (recorded_at),
            KEY event_type (event_type)
        ) {$collate};" );
        if ( ! wp_next_scheduled( 'hcd_cleanup_events' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'hcd_cleanup_events' );
        }
        if ( false === get_option( 'hcd_monitoring_enabled', false ) ) {
            add_option( 'hcd_monitoring_enabled', 'no', '', false );
        }
        if ( false === get_option( 'hcd_retention_days', false ) ) {
            add_option( 'hcd_retention_days', 14, '', false );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'hcd_cleanup_events' );
    }

    public static function enabled() {
        return 'yes' === get_option( 'hcd_monitoring_enabled', 'no' );
    }

    public static function record( $type, $source = 'woo', $detail = '' ) {
        if ( ! self::enabled() ) { return; }
        $allowed = array( 'classic_validation', 'store_api_error', 'order_created', 'order_failed', 'payment_complete' );
        if ( ! in_array( $type, $allowed, true ) ) { return; }
        global $wpdb;
        // The detail field accepts ONLY a narrow machine-code alphabet. Never save error text.
        $detail = strtolower( (string) $detail );
        $detail = substr( preg_replace( '/[^a-z0-9_-]/', '', $detail ), 0, 70 );
        $wpdb->insert( self::table(), array(
            'event_type' => $type,
            'source' => in_array( $source, array( 'classic', 'blocks', 'order' ), true ) ? $source : 'woo',
            'detail' => $detail,
            'recorded_at' => current_time( 'mysql', true ),
        ), array( '%s', '%s', '%s', '%s' ) );
    }

    public static function recent( $limit = 40 ) {
        global $wpdb;
        $limit = max( 1, min( 250, absint( $limit ) ) );
        $table = self::table();
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT event_type, source, detail, recorded_at FROM {$table} ORDER BY id DESC LIMIT %d",
            $limit
        ), ARRAY_A );
        return is_array( $rows ) ? $rows : array();
    }

    public static function summary( $days = 7 ) {
        global $wpdb;
        $table = self::table();
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - max( 1, min( 90, absint( $days ) ) ) * DAY_IN_SECONDS );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT event_type, COUNT(*) AS total FROM {$table} WHERE recorded_at >= %s GROUP BY event_type",
            $cutoff
        ), ARRAY_A );
        $counts = array( 'classic_validation' => 0, 'store_api_error' => 0, 'order_created' => 0, 'order_failed' => 0, 'payment_complete' => 0 );
        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                if ( isset( $counts[ $row['event_type'] ] ) ) { $counts[ $row['event_type'] ] = (int) $row['total']; }
            }
        }
        return $counts;
    }

    public static function cleanup() {
        global $wpdb;
        $days = (int) get_option( 'hcd_retention_days', 14 );
        if ( ! in_array( $days, array( 7, 14, 30 ), true ) ) { $days = 14; }
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
        $table = self::table();
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE recorded_at < %s", $cutoff ) );
    }

    public static function purge() {
        global $wpdb;
        $table = self::table();
        $wpdb->query( "DELETE FROM {$table}" );
    }
}
