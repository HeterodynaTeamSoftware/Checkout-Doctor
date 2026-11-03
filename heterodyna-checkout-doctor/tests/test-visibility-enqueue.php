<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define('ABSPATH','/tmp/'); define('HCD_VERSION','1.16.0');define('HCD_URL','https://example.invalid/plugin/');
$env='staging';$enabled='yes';$logged=false;$allowed=false;$checkout=true;$received=false;$paypage=false;$scripts=array();$localized=array();
function wp_get_environment_type(){global $env;return $env;}
function get_option($key,$default='no'){global $enabled;return $enabled;}
function is_user_logged_in(){global $logged;return $logged;}
function current_user_can($cap){global $allowed;return $allowed;}
function is_checkout(){global $checkout;return $checkout;}
function is_order_received_page(){global $received;return $received;}
function is_checkout_pay_page(){global $paypage;return $paypage;}
function wp_enqueue_script($id,$url,$deps,$version,$footer){global $scripts;$scripts[]=array($id,$url,$version);}
function wp_localize_script($id,$name,$data){global $localized;$localized=array($name,$data);}
function admin_url($path){return 'https://example.invalid/wp-admin/'.$path;}
function wp_create_nonce($key){return 'abc';}
require dirname(__DIR__).'/includes/class-hcd-payment-visibility.php';
function call_and_check($expected,$note){global $scripts;$scripts=array();HCD_Payment_Visibility::enqueue();if(count($scripts)!==$expected)throw new RuntimeException($note);}
call_and_check(0,'Anonymous');
$logged=true;call_and_check(0,'Unauthorized');
$allowed=true;$env='production';call_and_check(0,'Production');
$env='staging';$received=true;call_and_check(0,'Thank-you page');
$received=false;$paypage=true;call_and_check(0,'Payment existing order');
$paypage=false;$checkout=false;call_and_check(0,'Outside checkout');
$checkout=true;$enabled='no';call_and_check(0,'Opt-out');
$enabled='yes';call_and_check(1,'Expected enqueue');
if($localized[0]!=='HCD_PAYMENT_VISIBILITY'||$localized[1]['cartState']!=='unknown')throw new RuntimeException('Unexpected config');
if(array_key_exists('customer', $localized[1])) throw new RuntimeException('Personal field');
echo "PASS: script enqueues only opted-in staging checkout for authorized account\n";
