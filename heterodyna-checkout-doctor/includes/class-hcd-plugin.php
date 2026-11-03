<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class HCD_Plugin {
    public static function init() {
        add_action( 'hcd_cleanup_events', array( 'HCD_Events', 'cleanup' ) );
        HCD_Admin::init();
        HCD_Browser_Probe::init();
        HCD_Payment_Visibility::init();
        HCD_Store_API_Observer::init();
        if ( ! class_exists( 'WooCommerce' ) ) { return; }
        add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'classic_checkout_validation' ), 20, 2 );
        add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'classic_order' ), 20, 1 );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'block_order' ), 20, 1 );
        add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'failed_order' ), 20, 1 );
        add_action( 'woocommerce_payment_complete', array( __CLASS__, 'payment_complete' ), 20, 1 );
        add_filter( 'rest_post_dispatch', array( __CLASS__, 'store_api_response' ), 999, 3 );
    }

    public static function hpos_compat() {
        if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', HCD_FILE, true );
        }
    }

    public static function classic_checkout_validation( $data, $errors ) {
        if ( is_wp_error( $errors ) && $errors->has_errors() ) {
            // Field-level validation errors can be ordinary customer mistakes, not software defects.
            $codes = $errors->get_error_codes();
            HCD_Events::record( 'classic_validation', 'classic', self::error_group( isset( $codes[0] ) ? $codes[0] : '' ) );
        }
    }

    public static function classic_order( $order_id ) {
        HCD_Events::record( 'order_created', 'classic' );
    }

    public static function block_order( $order ) {
        HCD_Events::record( 'order_created', 'blocks' );
    }

    public static function failed_order( $order_id ) {
        HCD_Events::record( 'order_failed', 'order' );
    }

    public static function payment_complete( $order_id ) {
        HCD_Events::record( 'payment_complete', 'order' );
    }

    /** Convert arbitrary third-party error codes to fixed categories; never persist raw values. */
    private static function error_group( $code ) {
        $code = strtolower( (string) $code );
        if ( preg_match( '/payment|gateway|card|transaction/', $code ) ) { return 'payment'; }
        if ( preg_match( '/stock|inventory/', $code ) ) { return 'stock'; }
        if ( preg_match( '/ship|delivery|rate/', $code ) ) { return 'shipping'; }
        if ( preg_match( '/nonce|authoriz|permission|access|auth/', $code ) ) { return 'authorization'; }
        if ( preg_match( '/cart|coupon/', $code ) ) { return 'cart'; }
        if ( preg_match( '/field|billing|email|address|validation|required|invalid/', $code ) ) { return 'validation'; }
        return 'other';
    }

    public static function store_api_response( $response, $server, $request ) {
        if ( ! HCD_Events::enabled() || ! $request instanceof WP_REST_Request || ! $response instanceof WP_HTTP_Response ) {
            return $response;
        }
        // Record only final POST /checkout errors, not cart reads or payment payload.
        if ( 'POST' !== $request->get_method() || ! preg_match( '#^/wc/store/v[0-9]+/checkout/?$#', $request->get_route() ) ) {
            return $response;
        }
        if ( $response->get_status() >= 400 ) {
            $data = $response->get_data();
            $code = is_array( $data ) && isset( $data['code'] ) && is_string( $data['code'] ) ? $data['code'] : '';
            HCD_Events::record( 'store_api_error', 'blocks', self::error_group( $code ) . '_http_' . (int) $response->get_status() );
        }
        return $response;
    }
}
