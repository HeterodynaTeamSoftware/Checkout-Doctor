<?php
if ( PHP_SAPI !== 'cli' ) { exit(1); }
$root=dirname(__DIR__);
$entry=file_get_contents($root.'/heterodyna-checkout-doctor.php');
$plugin=file_get_contents($root.'/includes/class-hcd-plugin.php');
$scanner=file_get_contents($root.'/includes/class-hcd-scanner.php');
$admin=file_get_contents($root.'/includes/class-hcd-admin.php');
$ui=file_get_contents($root.'/assets/js/admin.js');
foreach(array("Version: 1.16.0","define( 'HCD_VERSION', '1.16.0' )","class-hcd-store-api-observer.php") as $s) if(strpos($entry,$s)===false)throw new RuntimeException($s);
foreach(array('HCD_Store_API_Observer::init', 'HCD_Store_API_Observer::run','hcd_store_api_observer_enabled','data-hcd-page="store-api"','hcd-run-store-api') as $s) {
 $haystack=$plugin.$scanner.$admin.$ui;
 if(strpos($haystack,$s)===false)throw new RuntimeException('Not connected: '.$s);
}
$script=file_get_contents($root.'/assets/js/store-api-observer.js');
foreach(array('response.status','window.fetch =','observations:JSON.stringify','nativeFetch.call(window, cfg.ajaxUrl') as $s) if(strpos($script,$s)===false)throw new RuntimeException('Missing browser behavior: '.$s);
if(strpos($script,'response.json(')!==false || strpos($script,'response.text(')!==false) throw new RuntimeException('Response body read');
echo "PASS: plugin loader, scanner, admin, JS and opt-in lifecycle\n";
