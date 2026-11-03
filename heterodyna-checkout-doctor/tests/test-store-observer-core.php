<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' );
$hcd_env = 'production'; $hcd_options = array(); $hcd_transients = array(); $hcd_logged = true; $hcd_authorized = true;
function wp_get_environment_type() { global $hcd_env; return $hcd_env; }
function get_option( $key, $default = false ) { global $hcd_options; return isset( $hcd_options[$key] ) ? $hcd_options[$key] : $default; }
function get_transient( $key ) { global $hcd_transients; return isset( $hcd_transients[$key] ) ? $hcd_transients[$key] : false; }
function is_user_logged_in() { global $hcd_logged; return $hcd_logged; }
function current_user_can( $cap ) { global $hcd_authorized; return $hcd_authorized; }
require dirname(__DIR__) . '/includes/class-hcd-store-api-observer.php';
function verify($ok,$text){ if (!$ok) throw new RuntimeException($text); }
verify(!HCD_Store_API_Observer::staging(), 'Production accepted');
$hcd_options[HCD_Store_API_Observer::OPTION]='yes';
verify(!HCD_Store_API_Observer::enabled(), 'Production observer enabled');
$hcd_env='staging';
verify(HCD_Store_API_Observer::enabled(),'Staging opt in not enabled');
$hcd_authorized=false;verify(!HCD_Store_API_Observer::can_test(),'User without permission');$hcd_authorized=true;
$hcd_logged=false;verify(!HCD_Store_API_Observer::can_test(),'Anonymous access');$hcd_logged=true;
foreach(array(
 array('area'=>'cart','method'=>'GET','status'=>404),
 array('area'=>'checkout','method'=>'POST','status'=>500),
 array('area'=>'cart','method'=>'PUT','status'=>429)
) as $item) verify(HCD_Store_API_Observer::normalize($item)===$item,'Valid sample denied');
foreach(array(
 array('area'=>'user','method'=>'GET','status'=>404),
 array('area'=>'checkout','method'=>'TRACE','status'=>400),
 array('area'=>'checkout','method'=>'POST','status'=>200),
 array('area'=>'checkout','method'=>'POST','status'=>'500'),
 array('area'=>'cart','method'=>'GET','status'=>600),
 array('area'=>'cart','method'=>'GET','status'=>399)
) as $item) verify(HCD_Store_API_Observer::normalize($item)===null,'Invalid sample accepted');
$hcd_transients[HCD_Store_API_Observer::KEY] = array(
 array('area'=>'checkout','method'=>'POST','status'=>500,'at'=>'2026-11-01T12:34:56+00:00','credit_card'=>'4111111111111111','request_url'=>'secret'),
 array('area'=>'cart','method'=>'GET','status'=>403,'at'=>'2026-11-01T12:34:57+00:00','name'=>'Customer'),
 array('area'=>'other','method'=>'POST','status'=>502,'raw'=>'secret'),
);
$s=HCD_Store_API_Observer::summary();
verify($s['count']===2 && $s['server_errors']===1 && $s['client_errors']===1,'Wrong summary');
verify(strpos(json_encode($s),'4111111111111111')===false && strpos(json_encode($s),'secret')===false && strpos(json_encode($s),'Customer')===false,'Sensitive data leaked');
$meta=array();$checks=HCD_Store_API_Observer::run($meta);
verify(count($checks)===1 && $checks[0]['status']==='warning' && $checks[0]['confidence']==='signal', 'Incorrect cautious classification');
$hcd_env='production';$s=HCD_Store_API_Observer::summary();verify($s['count']===0 && !$s['enabled'],'Old data displayed on production');
$hcd_env='staging';$hcd_options[HCD_Store_API_Observer::OPTION]='no';verify(!HCD_Store_API_Observer::summary()['count'],'Disabled observer leaked data');
$report = array('meta'=>array('store_api_observations'=>$hcd_transients[HCD_Store_API_Observer::KEY]));
// Archive sanitizer only works on the normalized report shape; malformed data is always excluded from summary.
$report = array('meta'=>array('store_api_observations'=>array('count'=>2,'last'=>array('area'=>'cart','status'=>403),'samples'=>array(array('secret'=>'card')))));
$archive = HCD_Store_API_Observer::for_archive($report);
verify($archive['meta']['store_api_observations']['samples']===array() && $archive['meta']['store_api_observations']['last']===null,'Sample persisted in durable report');
echo "PASS: staging/permission gates, strict enums, privacy, classification, production concealment\n";
