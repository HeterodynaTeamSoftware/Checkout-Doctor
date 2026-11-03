<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Opt-in, STAGING ONLY, administrator-owned observations of *existing* Store API fetch responses.
 * No requests to WooCommerce Store API are issued by this class or its JS observer.
 * Never process request/response bodies, customer fields, URLs, cookies, IDs, tokens or headers.
 */
final class HCD_Store_API_Observer {
    const KEY = 'hcd_store_api_observations';
    const OPTION = 'hcd_store_api_observer_enabled';
    const TTL = 604800; // 7 days

    public static function staging() {
        return function_exists( 'wp_get_environment_type' ) && in_array( wp_get_environment_type(), array( 'local', 'development', 'staging' ), true );
    }
    public static function can_test() {
        return function_exists( 'is_user_logged_in' ) && is_user_logged_in()
            && ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) );
    }
    public static function enabled() {
        return self::staging() && 'yes' === get_option( self::OPTION, 'no' );
    }
    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 101 );
        add_action( 'wp_ajax_hcd_store_api_observation', array( __CLASS__, 'receive' ) );
        add_action( 'admin_post_hcd_clear_store_api_observations', array( __CLASS__, 'clear' ) );
    }
    public static function enqueue() {
        if ( ! self::enabled() || ! self::can_test() || ! function_exists( 'is_checkout' ) || ! is_checkout() ) { return; }
        if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) { return; }
        wp_enqueue_script( 'hcd-store-api-observer', HCD_URL . 'assets/js/store-api-observer.js', array(), HCD_VERSION, false );
        wp_localize_script( 'hcd-store-api-observer', 'HCD_STORE_OBSERVER', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'hcd_store_api_observation' ),
        ) );
    }
    /** Strict enum and scalar types. The stored shape is built from scratch; extra input never persists. */
    public static function normalize( $value ) {
        if ( ! is_array( $value ) || ! isset( $value['area'], $value['method'], $value['status'] ) ) { return null; }
        if ( ! is_string( $value['area'] ) || ! in_array( $value['area'], array( 'cart', 'checkout' ), true ) ) { return null; }
        if ( ! is_string( $value['method'] ) || ! in_array( $value['method'], array( 'GET', 'POST', 'PUT', 'DELETE' ), true ) ) { return null; }
        if ( ! is_int( $value['status'] ) || $value['status'] < 400 || $value['status'] > 599 ) { return null; }
        return array( 'area' => $value['area'], 'method' => $value['method'], 'status' => $value['status'] );
    }
    public static function receive() {
        if ( ! self::enabled() || ! self::can_test() ) { wp_send_json_error( array( 'message' => 'Test niedostępny.' ), 403 ); return; }
        check_ajax_referer( 'hcd_store_api_observation', 'nonce' );
        $rate_key = 'hcd_store_api_rate_' . absint( get_current_user_id() );
        $rate = (int) get_transient( $rate_key );
        if ( $rate >= 12 ) { wp_send_json_error( array( 'message' => 'Limit próbek.' ), 429 ); return; }
        set_transient( $rate_key, $rate + 1, MINUTE_IN_SECONDS );
        $body = isset( $_POST['observations'] ) && is_string( $_POST['observations'] ) ? wp_unslash( $_POST['observations'] ) : '';
        if ( strlen( $body ) > 1024 ) { wp_send_json_error( array( 'message' => 'Przekroczono limit danych.' ), 400 ); return; }
        $decoded = json_decode( $body, true );
        if ( ! is_array( $decoded ) || count( $decoded ) > 8 ) { wp_send_json_error( array( 'message' => 'Nieprawidłowe próbki.' ), 400 ); return; }
        $saved = get_transient( self::KEY );
        $saved = is_array( $saved ) ? array_slice( $saved, -24 ) : array();
        $accepted = 0;
        foreach ( $decoded as $item ) {
            $normalized = self::normalize( $item );
            if ( ! $normalized ) { continue; }
            $normalized['at'] = gmdate( 'c' );
            $saved[] = $normalized;
            ++$accepted;
        }
        if ( $accepted ) { set_transient( self::KEY, array_slice( $saved, -24 ), self::TTL ); }
        wp_send_json_success( array( 'accepted' => $accepted ) );
    }
    public static function clear() {
        if ( ! self::can_test() ) { wp_die( 'Brak uprawnień.', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'hcd_clear_store_api_observations' );
        delete_transient( self::KEY );
        self::clear_archive();
        wp_safe_redirect( admin_url( 'admin.php?page=hcd-checkout-doctor&tab=store-api&cleared_store_api=1' ) );
        exit;
    }
    public static function summary() {
        // Never report old observations after switching to a production environment or disabling opt-in.
        if ( ! self::enabled() ) {
            return array( 'enabled' => false, 'count' => 0, 'last' => null, 'samples' => array(), 'client_errors' => 0, 'server_errors' => 0 );
        }
        $raw = get_transient( self::KEY );
        $raw = is_array( $raw ) ? array_slice( $raw, -24 ) : array();
        $samples = array();
        $clients = 0; $servers = 0;
        foreach ( $raw as $entry ) {
            $safe = self::normalize( $entry );
            if ( ! $safe ) { continue; }
            $safe['at'] = isset( $entry['at'] ) && is_string( $entry['at'] )
                && preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/D', $entry['at'] ) ? $entry['at'] : '';
            $samples[] = $safe;
            if ( $safe['status'] < 500 ) { ++$clients; } else { ++$servers; }
        }
        return array( 'enabled' => true, 'count' => count( $samples ), 'last' => $samples ? end( $samples ) : null,
            'samples' => $samples, 'client_errors' => $clients, 'server_errors' => $servers );
    }
    /** The durable last-scan option must never keep individual browser events beyond transient TTL. */
    public static function for_archive( $report ) {
        if ( is_array( $report ) && isset( $report['meta']['store_api_observations'] ) ) {
            $report['meta']['store_api_observations']['samples'] = array();
            $report['meta']['store_api_observations']['last'] = null;
            $report['meta']['store_api_observations']['detail_retained'] = false;
        }
        return $report;
    }
    /** Purge archived aggregates when observations are deliberately cleared. */
    public static function clear_archive() {
        $report = get_option( 'hcd_last_scan', array() );
        if ( ! is_array( $report ) || ! isset( $report['meta']['store_api_observations'] ) ) { return; }
        unset( $report['meta']['store_api_observations'], $report['meta']['evidence_correlation'] );
        if ( isset( $report['checks'] ) && is_array( $report['checks'] ) ) {
            $report['checks'] = array_values( array_filter( $report['checks'], function( $item ) {
                return ! is_array( $item ) || ! isset( $item['category'] ) || ! in_array( $item['category'], array( 'store_api', 'correlation' ), true );
            } ) );
            $counts = array( 'critical' => 0, 'warning' => 0, 'pass' => 0, 'info' => 0 );
            foreach ( $report['checks'] as $item ) {
                if ( is_array( $item ) && isset( $counts[ $item['status'] ] ) ) { ++$counts[ $item['status'] ]; }
            }
            $report['summary'] = $counts;
            if ( class_exists( 'HCD_Repair_Reports' ) ) { $report = HCD_Repair_Reports::enrich( $report ); }
        }
        update_option( 'hcd_last_scan', $report, false );
    }
    public static function run( &$meta ) {
        $details = self::summary();
        $meta['store_api_observations'] = $details;
        $checks = array();
        $make = function( $id, $status, $title, $evidence, $meaning, $recommendation ) use ( &$checks ) {
            $checks[] = compact( 'id', 'status', 'title', 'evidence', 'meaning', 'recommendation' )
                + array( 'category' => 'store_api', 'confidence' => 'signal' );
        };
        if ( ! $details['enabled'] ) {
            $make( 'store_observer_disabled', 'info', 'Obserwator Store API jest wyłączony',
                'Brak aktywnej obserwacji (wymagany staging i zgoda administratora).',
                'Nie zebrano danych o rzeczywistych odpowiedziach HTTP checkoutu.',
                'Włącz na kopii staging w ustawieniach, a następnie testuj wyłącznie własny koszyk.' );
        } elseif ( 0 === $details['count'] ) {
            $make( 'store_observer_empty', 'info', 'Brak błędnych odpowiedzi w obserwacji',
                'Zebrane błędne odpowiedzi: 0.',
                'Brak próbek nie potwierdza sprawności Store API ani bramek płatniczych.',
                'Wykonaj ręczny scenariusz na stagingu i zweryfikuj DevTools oraz logi WooCommerce.' );
        } else {
            $make( 'store_observer_samples', $details['server_errors'] ? 'warning' : 'info',
                'Zaobserwowano odpowiedzi HTTP Store API',
                '4xx: ' . $details['client_errors'] . '; 5xx: ' . $details['server_errors'] . '; łącznie ' . $details['count'] . '.',
                'Kody 4xx mogą wynikać z walidacji lub uprawnień; 5xx wymagają analizy. To sygnały, nie dowód awarii bramki.',
                'Porównaj czas obserwacji z logami WooCommerce/PHP na stagingu; nie wykonuj płatności produkcyjnych.' );
        }
        return $checks;
    }
}
