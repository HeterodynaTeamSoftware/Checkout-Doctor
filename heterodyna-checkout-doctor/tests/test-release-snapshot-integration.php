<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
$base = dirname( __DIR__ );
$main = file_get_contents( $base . '/heterodyna-checkout-doctor.php' );
$admin = file_get_contents( $base . '/includes/class-hcd-admin.php' );
foreach ( array("Version: 1.16.0", 'class-hcd-release-snapshot.php', 'class-hcd-scan-lock.php') as $v ) if ( false === strpos( $main, $v ) ) throw new RuntimeException( 'Missing: '. $v );
foreach ( array('hcd_export_release_snapshot', 'HCD_Release_Snapshot::build', 'HCD_Scan_Lock::acquire', 'HCD_Scan_Lock::release', 'Pobierz anonimowy pakiet wsparcia') as $v ) if ( false === strpos($admin,$v) ) throw new RuntimeException('Not integrated: '. $v);
echo "PASS: version, lock and support export wiring\n";
