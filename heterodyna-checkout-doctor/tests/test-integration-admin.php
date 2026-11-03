<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
$p = dirname(__DIR__);
$a = file_get_contents($p.'/includes/class-hcd-admin.php');
$s = file_get_contents($p.'/includes/class-hcd-scanner.php');
$e = file_get_contents($p.'/heterodyna-checkout-doctor.php');
$j = file_get_contents($p.'/assets/js/admin.js');
foreach (array("'hcd_save_integration_tests'", "'hcd_export_integration_tests'", "self::authorize( 'hcd_save_integration_tests' )", "self::authorize( 'hcd_export_integration_tests' )",'hcd_scenario[','data-hcd-tab="integration"','data-hcd-page="integration"') as $needle) {
  if (strpos($a,$needle)===false) throw new RuntimeException('Admin integration missing: '.$needle);
}
foreach (array('HCD_Integration_Tests::run', 'payments_shipping_unavailable','core_module_unavailable') as $needle) if(strpos($s,$needle)===false) throw new RuntimeException('Scanner link missing');
foreach (array('class-hcd-integration-tests.php',"define( 'HCD_VERSION', '1.16.0' )") as $needle) if(strpos($e,$needle)===false) throw new RuntimeException('Version/loader mismatch');
foreach (array('function updateIntegration()','updateIntegration();', "runScan('integration')", "'integration'") as $needle) if(strpos($j,$needle)===false) throw new RuntimeException('Missing client binding');
echo "PASS: permissions, AJAX containment, integration UI, JS and loader\n";
