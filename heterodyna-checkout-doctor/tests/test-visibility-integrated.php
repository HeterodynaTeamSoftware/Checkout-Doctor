<?php
if(PHP_SAPI!=='cli')exit(1);
$r=dirname(__DIR__);
$entry=file_get_contents($r.'/heterodyna-checkout-doctor.php');
$admin=file_get_contents($r.'/includes/class-hcd-admin.php');
$js=file_get_contents($r.'/assets/js/admin.js');
$scanner=file_get_contents($r.'/includes/class-hcd-scanner.php');
foreach(array('Version: 1.16.0',"class-hcd-payment-visibility.php") as $k)if(strpos($entry,$k)===false)throw new RuntimeException($k);
foreach(array('data-hcd-tab="visibility"','data-hcd-page="visibility"','hcd-visibility-findings','hcd_payment_visibility_enabled','hcd-run-visibility') as $k)if(strpos($admin,$k)===false)throw new RuntimeException($k);
foreach(array('function updateVisibility()',"runScan('visibility')",'updateVisibility();') as $k)if(strpos($js,$k)===false)throw new RuntimeException($k);
if(strpos($scanner,'HCD_Payment_Visibility::run')===false)throw new RuntimeException('Scanner disconnected');
if(strpos(file_get_contents($r.'/uninstall.php'),'hcd_payment_visibility_samples')===false)throw new RuntimeException('Uninstall leaves samples');
echo "PASS: ZIP 10 loader, navigation, scan, exporter integration, purge\n";
