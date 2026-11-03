<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
$root=dirname(__DIR__);
$admin=file_get_contents($root.'/includes/class-hcd-admin.php');
$js=file_get_contents($root.'/assets/js/admin.js');
$entry=file_get_contents($root.'/heterodyna-checkout-doctor.php');
foreach (array('data-hcd-tab="readiness"', 'data-hcd-page="readiness"', 'hcd-run-readiness', 'hcd-reg-changes','hcd_export_comparison','hcd-readiness-findings') as $str) { if(strpos($admin,$str)===false) throw new RuntimeException('UI missing '.$str); }
foreach(array("runScan('readiness')",'function updateReadiness()', "updateReadiness();", "new_check:'NOWY TEST'") as $str) { if(strpos($js,$str)===false) throw new RuntimeException('JS missing '.$str); }
if (strpos($entry,'Version: 1.16.0') === false || strpos($entry,"class-hcd-readiness.php") === false) throw new RuntimeException('Main version/loader missing');
echo "PASS: readiness navigation, AJAX action, HTML and JS wiring\n";
