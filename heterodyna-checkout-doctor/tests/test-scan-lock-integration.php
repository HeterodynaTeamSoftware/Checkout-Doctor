<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
$root = dirname( __DIR__ );
$admin = file_get_contents( $root . '/includes/class-hcd-admin.php' );
$js = file_get_contents( $root . '/assets/js/admin.js' );
foreach ( array( 'HCD_Scan_Lock::acquire( $user_id )', 'HCD_Scan_Lock::release( $user_id, $token )', 'get_current_user_id()', 'wp_send_json_error', 'check_ajax_referer' ) as $part ) {
    if ( false === strpos( $admin, $part ) ) { throw new RuntimeException( 'Missing scan guard: ' . $part ); }
}
if ( substr_count( $admin, 'HCD_Scan_Lock::release( $user_id, $token )' ) !== 2 ) { throw new RuntimeException( 'Lock must clear on both success and error paths' ); }
if ( false === strpos( $js, 'if (scanInProgress) return;' ) ) { throw new RuntimeException( 'Missing client-side guard' ); }
echo "PASS: scan guard present in AJAX and browser paths\n";
