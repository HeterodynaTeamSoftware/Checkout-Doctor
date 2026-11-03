<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
$root = dirname( __DIR__ );
$main = file_get_contents( $root . '/heterodyna-checkout-doctor.php' );
$scanner = file_get_contents( $root . '/includes/class-hcd-scanner.php' );
$admin = file_get_contents( $root . '/includes/class-hcd-admin.php' );
$js = file_get_contents( $root . '/assets/js/admin.js' );
foreach ( array( 'Version: 1.16.0', "define( 'HCD_VERSION', '1.16.0' )", 'class-hcd-evidence-correlation.php' ) as $s ) if ( strpos( $main, $s ) === false ) throw new RuntimeException( 'Loader: ' . $s );
foreach ( array( 'HCD_Evidence_Correlation::run', 'correlation_unavailable' ) as $s ) if( strpos( $scanner, $s ) === false ) throw new RuntimeException( 'Scanner: ' . $s );
foreach ( array( 'data-hcd-tab="correlation"', 'data-hcd-page="correlation"', 'id="hcd-run-correlation"', 'id="hcd-correlation-findings"' ) as $s ) if ( strpos( $admin, $s ) === false ) throw new RuntimeException( 'UI: ' . $s );
foreach ( array( 'updateCorrelation', "runScan('correlation')", "'#hcd-corr-sources'", 'evidence_correlation' ) as $s ) if ( strpos( $js, $s ) === false ) throw new RuntimeException( 'JS: ' . $s );
$observer = file_get_contents( $root . '/includes/class-hcd-store-api-observer.php' );
if ( strpos( $observer, 'evidence_correlation' ) === false || strpos( $observer, "'correlation'" ) === false ) throw new RuntimeException( 'Clearing observations must invalidate correlations' );
echo "PASS: loader, scanner, new panel, JS, purge integration\n";
