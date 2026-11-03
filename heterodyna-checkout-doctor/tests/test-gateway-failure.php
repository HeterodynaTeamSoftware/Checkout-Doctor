<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 403 ); exit; }
define( 'ABSPATH', '/tmp/' ); define( 'HCD_VERSION', '1.0.0' ); define( 'DAY_IN_SECONDS', 86400 );
class WooCommerce {}
class HCD_Events { public static function enabled() { return false; } public static function summary( $days ) { return array(); } }
function get_bloginfo( $key ) { return '6.x'; }
function wc_get_page_id( $page ) { return $page === 'checkout' ? 100 : 101; }
function get_post( $id ) { return (object) array( 'post_status' => 'publish', 'post_content' => '[woocommerce_checkout]' ); }
function has_block( $name, $post ) { return false; }
function has_shortcode( $content, $shortcode ) { return true; }
function wc_get_checkout_url() { return 'https://shop.example/checkout'; }
function wp_parse_url( $url, $component ) { return parse_url( $url, $component ); }
function WC() { return new class { public function payment_gateways() { throw new RuntimeException( 'redacted secret' ); } }; }
function wc_shipping_enabled() { throw new RuntimeException( 'other redacted secret' ); }
require dirname( __DIR__ ) . '/includes/class-hcd-checkout-core.php';
require dirname( __DIR__ ) . '/includes/class-hcd-payments-shipping.php';
require dirname( __DIR__ ) . '/includes/class-hcd-scanner.php';
$r = HCD_Scanner::run(); $checks = array();
foreach( $r['checks'] as $item ) { $checks[$item['id']] = $item; }
if ( $checks['payment_gateways']['status'] !== 'warning' || $checks['shipping']['status'] !== 'warning' ) { throw new RuntimeException( 'Scanner crashed or did not report component failure' ); }
if ( strpos( json_encode($r), 'redacted secret' ) !== false ) { throw new RuntimeException( 'Sensitive exception details disclosed' ); }
echo "PASS: graceful component errors and redacted exception data\n";
