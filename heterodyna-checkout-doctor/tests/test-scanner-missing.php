<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 403 ); exit; }
/* Minimal contract smoke test outside WordPress; no server, WooCommerce or network required. */
define( 'ABSPATH', '/tmp/' ); define('HCD_VERSION','1.0.0');
class HCD_Events { public static function enabled() { return false; } }
function get_bloginfo($key) { return '6.x'; }
require dirname(__DIR__) . '/includes/class-hcd-payments-shipping.php';
require dirname(__DIR__) . '/includes/class-hcd-scanner.php';
$result = HCD_Scanner::run();
if (!isset($result['summary']['critical']) || $result['summary']['critical'] !== 1 || $result['checks'][0]['id'] !== 'woo_missing') { throw new RuntimeException('Missing WooCommerce state regression'); }
if (strtolower($result['meta']['woocommerce_version']) !== 'nieaktywne') { throw new RuntimeException('Metadata error'); }
echo "PASS: graceful missing WooCommerce mode\n";
