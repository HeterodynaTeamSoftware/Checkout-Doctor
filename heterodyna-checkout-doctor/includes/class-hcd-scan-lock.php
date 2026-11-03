<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * MySQL/MariaDB option table provides a unique option_name key.
 * INSERT IGNORE (not add_option) never overwrites an existing lock in a race.
 * All operations are conditional on exact key AND owner token when deleting.
 * No cached WP options are involved; the lock is not an autoloaded option.
 */
final class HCD_Scan_Lock {
    const TTL = 120;

    public static function option_name( $user_id ) {
        return 'hcd_active_scan_' . max( 0, (int) $user_id );
    }

    private static function insert_owner( $key, $owner ) {
        global $wpdb;
        // WordPress' MySQL/MariaDB options table guarantees unique option_name.
        $result = $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO `{$wpdb->options}` (`option_name`,`option_value`,`autoload`) VALUES (%s,%s,'no')",
            $key, $owner
        ) );
        return 1 === (int) $result;
    }

    /** Returns opaque expiration-and-token on success or false if currently locked. */
    public static function acquire( $user_id, $now = null ) {
        global $wpdb;
        $now = null === $now ? time() : (int) $now;
        $key = self::option_name( $user_id );
        try {
            $owner = ( $now + self::TTL ) . ':' . bin2hex( random_bytes( 16 ) );
        } catch ( Throwable $e ) {
            return false; // Never run without a strong ownership token.
        }
        if ( self::insert_owner( $key, $owner ) ) { return $owner; }

        // A dead PHP worker leaves a stale record; remove it only when unchanged.
        $old = $wpdb->get_var( $wpdb->prepare(
            "SELECT `option_value` FROM `{$wpdb->options}` WHERE `option_name` = %s LIMIT 1", $key
        ) );
        if ( ! is_string( $old ) ) { return false; }
        $parts = explode( ':', $old, 2 );
        $expires = 2 === count( $parts ) && ctype_digit( $parts[0] ) ? (int) $parts[0] : 0;
        if ( $expires > $now ) { return false; }
        // Conditional delete protects a newer owner from an old/stale requester.
        $deleted = $wpdb->query( $wpdb->prepare(
            "DELETE FROM `{$wpdb->options}` WHERE `option_name` = %s AND `option_value` = %s", $key, $old
        ) );
        return 1 === (int) $deleted && self::insert_owner( $key, $owner ) ? $owner : false;
    }

    public static function release( $user_id, $owner ) {
        global $wpdb;
        if ( ! is_string( $owner ) || ! preg_match( '/^[0-9]{1,12}:[a-f0-9]{32}$/D', $owner ) ) { return false; }
        $key = self::option_name( $user_id );
        $deleted = $wpdb->query( $wpdb->prepare(
            "DELETE FROM `{$wpdb->options}` WHERE `option_name` = %s AND `option_value` = %s", $key, $owner
        ) );
        return 1 === (int) $deleted;
    }
}
