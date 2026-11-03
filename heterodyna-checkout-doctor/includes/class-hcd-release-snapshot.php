<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * An allowlisted support snapshot. Never embed checks, evidence, metadata dumps,
 * URLs, logs, usernames, order identifiers, paths, plugin inventories, or hashes.
 */
final class HCD_Release_Snapshot {
    private static function version( $value ) {
        return is_string( $value ) && preg_match( '/^[0-9]{1,3}(?:\.[0-9]{1,3}){1,3}(?:-[a-zA-Z0-9.]{1,24})?$/D', $value ) ? $value : 'unknown';
    }

    private static function count( $value ) {
        return is_numeric( $value ) ? max( 0, min( 100000, (int) $value ) ) : 0;
    }

    private static function state( $value ) {
        return in_array( $value, array( 'blocked', 'requires_tests', 'manual_complete_pending_review' ), true ) ? $value : 'not_evaluated';
    }

    public static function build( $report, $manual, $environment, $generated_at = null ) {
        $report = is_array( $report ) ? $report : array();
        $manual = is_array( $manual ) ? $manual : array();
        $environment = is_array( $environment ) ? $environment : array();
        $summary = isset( $report['summary'] ) && is_array( $report['summary'] ) ? $report['summary'] : array();
        $manual_counts = isset( $manual['summary'] ) && is_array( $manual['summary'] ) ? $manual['summary'] : array();
        $verification = isset( $manual['verification_summary'] ) && is_array( $manual['verification_summary'] ) ? $manual['verification_summary'] : array();
        $gate = isset( $report['meta']['release_gate'] ) && is_array( $report['meta']['release_gate'] ) ? $report['meta']['release_gate'] : array();
        $env_type = isset( $environment['type'] ) && in_array( $environment['type'], array( 'production', 'staging', 'development', 'local' ), true ) ? $environment['type'] : 'unknown';
        return array(
            'schema' => 'hcd-public-support-v1',
            'generated_utc' => gmdate( 'Y-m-d\TH:i:s\Z', null === $generated_at ? time() : (int) $generated_at ),
            'plugin' => array( 'name' => 'Heterodyna Checkout Doctor', 'version' => HCD_VERSION ),
            'runtime' => array(
                'environment' => $env_type,
                'php_version' => self::version( isset( $environment['php'] ) ? $environment['php'] : '' ),
                'wordpress_version' => self::version( isset( $environment['wordpress'] ) ? $environment['wordpress'] : '' ),
                'woocommerce_version' => self::version( isset( $environment['woocommerce'] ) ? $environment['woocommerce'] : '' ),
                'woocommerce_active' => ! empty( $environment['woocommerce_active'] ),
            ),
            'last_scan_available' => ! empty( $report['generated_at'] ),
            'findings_count' => array(
                'critical' => self::count( isset( $summary['critical'] ) ? $summary['critical'] : 0 ),
                'warning' => self::count( isset( $summary['warning'] ) ? $summary['warning'] : 0 ),
                'pass' => self::count( isset( $summary['pass'] ) ? $summary['pass'] : 0 ),
                'info' => self::count( isset( $summary['info'] ) ? $summary['info'] : 0 ),
            ),
            'integration_tests' => array(
                'total' => self::count( isset( $manual['total'] ) ? $manual['total'] : 0 ),
                'passed' => self::count( isset( $manual_counts['passed'] ) ? $manual_counts['passed'] : 0 ),
                'failed' => self::count( isset( $manual_counts['failed'] ) ? $manual_counts['failed'] : 0 ),
                'blocked' => self::count( isset( $manual_counts['blocked'] ) ? $manual_counts['blocked'] : 0 ),
                'untested' => self::count( isset( $manual_counts['untested'] ) ? $manual_counts['untested'] : 0 ),
                'current_manual_passed' => self::count( isset( $manual['current_manual_passed'] ) ? $manual['current_manual_passed'] : 0 ),
                'environment_changed' => self::count( isset( $verification['environment_changed'] ) ? $verification['environment_changed'] : 0 ),
                'expired' => self::count( isset( $verification['expired'] ) ? $verification['expired'] : 0 ),
            ),
            'release_gate' => self::state( isset( $gate['state'] ) ? $gate['state'] : '' ),
            'note' => 'Counts and manual declarations only; not proof of payment functionality or production compatibility.',
        );
    }
}
