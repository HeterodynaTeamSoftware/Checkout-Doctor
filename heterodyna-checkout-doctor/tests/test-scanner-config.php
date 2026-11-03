<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 403 ); exit; }
/* WooCommerce stubs to test read-only rules and evidence; not a real WP integration test. */
define( 'ABSPATH', '/tmp/' ); define('HCD_VERSION','1.0.0'); define('DAY_IN_SECONDS',86400);define('WC_VERSION','10.fake');
class WooCommerce {}
class HCD_Events { public static function enabled() { return true; } public static function summary($days) { return ['store_api_error'=>2,'classic_validation'=>1,'order_failed'=>1]; } }
function get_bloginfo($key) { return '6.x'; }
function wc_get_page_id($page) { return $page === 'checkout' ? 100 : 101; }
function get_post($id) { return (object)['post_status'=>'publish','post_content'=>'<!-- wp:woocommerce/checkout /-->']; }
function has_block($name,$post) { return true; }
function has_shortcode($content,$shortcode) { return false; }
function wc_get_checkout_url() { return 'https://shop.example/checkout/'; }
function wp_parse_url($url,$constant) { return parse_url($url,$constant); }
class WC_Gateway_Fake { public $enabled='yes';public $id='bacs'; }
class WC_Gateways_Fake { public function payment_gateways(){return [new WC_Gateway_Fake()];} }
class WC_Fake { public function payment_gateways(){return new WC_Gateways_Fake();} }
function WC() {return new WC_Fake();}
function wc_shipping_enabled(){return true;}
class WC_Shipping_Zones {public static function get_zones(){return [1=>[]];}}
function wc_get_orders($args) { if(!isset($args['paginate'])||!isset($args['return'])||$args['return']!=='ids')throw new RuntimeException('Not using paginated WC API'); return (object)['total'=>4]; }
function sanitize_key($key){return preg_replace('/[^a-z0-9_\-]/','',strtolower($key));}
require dirname(__DIR__) . '/includes/class-hcd-checkout-core.php';
require dirname(__DIR__) . '/includes/class-hcd-payments-shipping.php';
require dirname(__DIR__) . '/includes/class-hcd-scanner.php';
$result = HCD_Scanner::run();
$byId=[];foreach($result['checks'] as $c){$byId[$c['id']]=$c;}
foreach(['checkout_page','checkout_render','cart_page','https','payment_gateways','shipping','failed_orders','monitoring','core_page_collision','core_checkout_collision','blocks_registration','orders_hpos'] as $id) if(!isset($byId[$id]))throw new RuntimeException('Missing check: '.$id);
if($byId['https']['status']!=='pass'||$byId['checkout_render']['status']!=='pass')throw new RuntimeException('False positive with correct configuration');
if($byId['failed_orders']['status']!=='warning'||$byId['failed_orders']['confidence']!=='signal')throw new RuntimeException('Failed orders interpreted incorrectly');
if($byId['monitoring']['status']!=='warning')throw new RuntimeException('Monitoring signal missing');
echo "PASS: configuration and evidence rules (".count($result['checks'])." checks)\n";
