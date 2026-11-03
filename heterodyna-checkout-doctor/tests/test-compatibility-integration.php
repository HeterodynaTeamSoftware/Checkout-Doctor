<?php
if ( PHP_SAPI !== 'cli' ) { exit(1); }
$base = dirname(__DIR__);
$entry = file_get_contents($base . '/heterodyna-checkout-doctor.php');
$admin = file_get_contents($base . '/includes/class-hcd-admin.php');
$scanner = file_get_contents($base . '/includes/class-hcd-scanner.php');
$js = file_get_contents($base . '/assets/js/admin.js');
foreach (array('Version: 1.16.0', "define( 'HCD_VERSION', '1.16.0' )", 'class-hcd-compatibility.php') as $term) if (strpos($entry, $term)===false) throw new RuntimeException('Version / loader: '.$term);
foreach (array('data-hcd-tab="compatibility"','data-hcd-page="compatibility"','id="hcd-run-compatibility"','id="hcd-compat-findings"', 'Lista testów przed publikacją') as $term) if(strpos($admin,$term)===false) throw new RuntimeException('UI missing: '.$term);
foreach (array('function updateCompatibility()',"runScan('compatibility')",'updateCompatibility();') as $term) if(strpos($js,$term)===false) throw new RuntimeException('JS missing: '.$term);
if(strpos($scanner, 'HCD_Compatibility::run')===false) throw new RuntimeException('Scanner not linked');
if(strpos($admin,'heterodyna-original.webp')===false) throw new RuntimeException('Branding regressed');
echo "PASS: loader, scan, tab, client rendering, logo\n";
