<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' );
$base = dirname( __DIR__ );
$main = file_get_contents( $base . '/heterodyna-checkout-doctor.php' );
$scanner = file_get_contents( $base . '/includes/class-hcd-scanner.php' );
$admin = file_get_contents( $base . '/includes/class-hcd-admin.php' );
$js = file_get_contents( $base . '/assets/js/admin.js' );
$must = array(
 array($main, "Version: 1.16.0"),
 array($main, "define( 'HCD_VERSION', '1.16.0' )"),
 array($main, 'class-hcd-release-gate.php'),
 array($scanner, 'HCD_Release_Gate::run'),
 array($admin, 'id="hcd-release-gate"'),
 array($js, 'manual_complete_pending_review'),
 array($js, 'if (scanInProgress) return;'),
 array($js, 'scanInProgress = false;')
);
foreach ( $must as $pair ) { if ( false === strpos( $pair[0], $pair[1] ) ) { throw new RuntimeException( 'Missing wiring: ' . $pair[1] ); } }
echo "PASS: release gate wired to plugin, scan, admin and JS\n";
