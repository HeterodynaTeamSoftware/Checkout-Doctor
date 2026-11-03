<?php
if ( PHP_SAPI !== 'cli' ) { exit(1); }
define('ABSPATH','/tmp/');
define('HCD_VERSION','1.16.0');
class WooCommerce {}
class HCD_Payments_Shipping { public static function run(&$meta){ throw new RuntimeException('secret-payment-provider-key'); } }
class HCD_Checkout_Core { public static function run(&$meta){ throw new RuntimeException('secret-checkout-path'); } }
class HCD_Browser_Probe { public static function run(&$meta){ return array(); } }
class HCD_Events {
 public static function enabled(){ return false; }
 public static function summary($days){ return array('store_api_error'=>0,'classic_validation'=>0,'order_failed'=>0); }
}
function get_bloginfo($k){return '6.7.0';}
function wc_get_page_id($k){return -1;}
function get_post($id){return null;}
function wc_get_checkout_url(){return 'https://shop.invalid/checkout/';}
function wp_parse_url($url,$part){return parse_url($url,$part);}
require dirname(__DIR__).'/includes/class-hcd-scanner.php';
$report=HCD_Scanner::run();
$checks=array(); foreach ($report['checks'] as $check) $checks[$check['id']]=$check;
if (!isset($checks['payments_shipping_unavailable'],$checks['core_module_unavailable'],$checks['https'])) throw new RuntimeException('Fault isolation failed');
if ($checks['payments_shipping_unavailable']['confidence'] !== 'signal' || $checks['core_module_unavailable']['status'] !== 'info') throw new RuntimeException('Fault classified as confirmed');
if (strpos(json_encode($report),'secret-payment')!==false || strpos(json_encode($report),'secret-checkout')!==false) throw new RuntimeException('Exception secrets leaked');
echo "PASS: faults in payment/checkout do not interrupt scan or leak exception details\n";
