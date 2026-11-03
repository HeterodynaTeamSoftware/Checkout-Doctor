<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' );
define( 'WC_VERSION', '9.5.0' );
$options = array( 'woocommerce_db_version'=>'9.4.0', 'woocommerce_checkout_pay_endpoint'=>'zaplat', 'woocommerce_checkout_order_received_endpoint'=>'zaplat' );
function get_option( $key, $default = false ) { global $options; return array_key_exists($key, $options) ? $options[$key] : $default; }
require dirname( __DIR__ ) . '/includes/class-hcd-readiness.php';
$meta = array();
$checks = HCD_Readiness::run( $meta );
$items = array(); foreach ($checks as $item) { $items[$item['id']] = $item; }
if ( count($items) !== 4 || 'warning' !== $items['readiness_db_version']['status'] || 'signal' !== $items['readiness_db_version']['confidence'] ) { throw new RuntimeException( 'DB version mismatch classification.' ); }
if ( 'warning' !== $items['readiness_endpoint_collision']['status'] ) { throw new RuntimeException( 'Endpoint collision missing.' ); }
if ( 'info' !== $items['readiness_cron']['status'] || 'pass' !== $items['readiness_debug_display']['status'] ) { throw new RuntimeException( 'Cron / debug confidence mismatch.' ); }
if ( $meta['readiness']['checkout_endpoints']['order_received'] !== 'zaplat' ) { throw new RuntimeException( 'Endpoint metadata mismatch.' ); }
$options['woocommerce_db_version'] = '9.5.0';
$options['woocommerce_checkout_order_received_endpoint'] = 'koniec';
$ok = HCD_Readiness::run($meta); $items = array(); foreach ($ok as $item) { $items[$item['id']] = $item; }
if ( 'pass' !== $items['readiness_db_version']['status'] || 'pass' !== $items['readiness_endpoint_collision']['status'] ) { throw new RuntimeException('Valid config not recognized.'); }
echo "PASS: DB version, endpoint collision, cron, and config safety\n";
