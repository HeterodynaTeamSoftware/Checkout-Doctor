<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' );
require dirname( __DIR__ ) . '/includes/class-hcd-compatibility.php';
require dirname( __DIR__ ) . '/includes/class-hcd-release-gate.php';
function check( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
$complete = array(
 'total' => 7,
 'summary' => array( 'passed' => 7, 'failed' => 0, 'blocked' => 0, 'untested' => 0 ),
 'verification_summary' => array( 'environment_changed' => 0, 'expired' => 0, 'legacy_unverified' => 0 ),
 'current_manual_passed' => 7,
);
$env = array( 'woo_active' => true, 'wordpress' => '6.9.0', 'php' => '8.4.0' );
$gate = HCD_Release_Gate::evaluate( $complete, $env );
check( 'manual_complete_pending_review' === $gate['state'] && false === $gate['public_release_approved_automatically'], 'Manual checklist must not auto-certify release' );
$with_failure = $complete; $with_failure['summary']['failed'] = 1; $with_failure['current_manual_passed'] = 6;
check( 'blocked' === HCD_Release_Gate::evaluate( $with_failure, $env )['state'], 'Failed manual scenario must block' );
$old = $env; $old['wordpress'] = '5.9';
check( 'blocked' === HCD_Release_Gate::evaluate( $complete, $old )['state'], 'Old WP must block' );
$old = $env; $old['php'] = '7.3';
check( 'blocked' === HCD_Release_Gate::evaluate( $complete, $old )['state'], 'Old PHP must block' );
$no_wc = $env; $no_wc['woo_active'] = false;
check( 'blocked' === HCD_Release_Gate::evaluate( $complete, $no_wc )['state'], 'Missing WooCommerce must block' );
$stale = $complete; $stale['verification_summary']['expired'] = 1; $stale['current_manual_passed'] = 6;
check( 'requires_tests' === HCD_Release_Gate::evaluate( $stale, $env )['state'], 'Expired verification cannot count as current' );
$none = HCD_Release_Gate::evaluate( array(), $env );
check( 'requires_tests' === $none['state'] && 7 === $none['missing_or_unverified'], 'Missing checklist must require tests' );
$odd = $env; $odd['wordpress'] = 'N/A';
check( 'requires_tests' === HCD_Release_Gate::evaluate( $complete, $odd )['state'], 'Unknown WP cannot approve' );
check( ! isset( $gate['environment_hash'], $gate['plugin_names'], $gate['customer_data'] ), 'No identifiable inventory in gate' );
echo "PASS: release gate states, manual scope and privacy\n";
