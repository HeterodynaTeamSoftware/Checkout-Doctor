<?php
if ( PHP_SAPI !== 'cli' ) { exit(1); }
define('ABSPATH', '/tmp/');
$OPTIONS = array('woocommerce_enable_coupons'=>'no','woocommerce_ship_to_countries'=>'specific','woocommerce_specific_ship_to_countries'=>array());
function get_option($key, $default=null) {global $OPTIONS; return isset($OPTIONS[$key]) ? $OPTIONS[$key] : $default;}
function sanitize_key($key) {return preg_replace('/[^a-z0-9_-]/','',strtolower($key));}
class GatewayItem {public $id; public $enabled; public function __construct($id,$enabled){$this->id=$id;$this->enabled=$enabled;} public function get_option($key,$default=null){return $key==='enable_for_methods'?array('flat_rate:1'):$default;}}
class GatewayManager {public function payment_gateways(){return array(new GatewayItem('cod','yes'), new GatewayItem('secret_merchant_key_not_exposed','no'));}}
class TestWoo {public function payment_gateways(){return new GatewayManager();}}
function WC(){return new TestWoo();}
function wc_shipping_enabled(){return true;}
class TestMethod {public $method_id; public $enabled;public function __construct($id,$enabled){$this->method_id=$id;$this->enabled=$enabled;}public function get_option($key,$default=null){if($key==='requires')return 'coupon';return $default;}}
class TestZone {private $id;public function __construct($id){$this->id=$id;}public function get_shipping_methods($enabled_only=false){return $this->id===7?array(new TestMethod('free_shipping','yes'),new TestMethod('flat_rate','no')):array();}}
class WC_Shipping_Zones {public static function get_zones(){return array(7=>array('zone_id'=>7));}public static function get_zone($id){return new TestZone($id);}}
require dirname(__DIR__).'/includes/class-hcd-payments-shipping.php';
$meta=array();$checks=HCD_Payments_Shipping::run($meta);$by=array();foreach($checks as $row)$by[$row['id']]=$row;
if($meta['payment_gateways_total']!==2 || $meta['gateways_enabled']!==1 || $meta['shipping_methods_enabled']!==1 || $meta['shipping_zones_inspected']!==2)throw new RuntimeException('Wrong stats');
if($by['shipping_free_coupon_zone_7']['status']!=='warning'||$by['shipping_countries']['status']!=='warning'||$by['shipping_zone_7']['status']!=='pass'||$by['shipping_zone_0']['status']!=='info')throw new RuntimeException('Configuration conflicts not detected');
if($by['payments_cod_shipping_rules']['status']!=='info')throw new RuntimeException('COD restrictions missing');
if(strpos(json_encode($checks),'secret_merchant_key_not_exposed')!==false)throw new RuntimeException('Disabled gateway leaked');
echo "PASS: gateway state, COD rules, shipping zones, free-shipping/coupon mismatch, no disabled IDs\n";
