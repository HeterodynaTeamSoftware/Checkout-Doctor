<?php
if ( PHP_SAPI !== 'cli' ) { exit(1); }
define('ABSPATH','/tmp/');
function WC(){return new class {public function payment_gateways(){return new class{public function payment_gateways(){throw new RuntimeException('SECRET-PAYMENT-CREDENTIAL');}};}};}
function wc_shipping_enabled(){return true;}
class WC_Shipping_Zones {public static function get_zones(){throw new RuntimeException('SECRET-SHIPPING-ADDRESS');}}
require dirname(__DIR__).'/includes/class-hcd-payments-shipping.php';
$meta=[];$checks=HCD_Payments_Shipping::run($meta);$by=[];foreach($checks as $check){$by[$check['id']]=$check;}
if($by['payment_gateways']['status']!=='warning'||$by['shipping']['status']!=='warning')throw new RuntimeException('Exceptions not isolated');
if(strpos(json_encode($checks),'SECRET')!==false)throw new RuntimeException('Sensitive exception leaked');
echo "PASS: isolated payment/shipping failures and secret-safe reports\n";
