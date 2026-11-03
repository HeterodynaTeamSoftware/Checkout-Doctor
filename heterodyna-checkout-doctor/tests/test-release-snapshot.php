<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' );
define( 'HCD_VERSION', '1.16.0' );
require dirname( __DIR__ ) . '/includes/class-hcd-release-snapshot.php';
function check( $ok, $msg ) { if ( ! $ok ) { throw new RuntimeException( $msg ); } }
$secret = 'DO_NOT_EXPORT_private_203.0.113.9_admin@example.org_gateway_secret';
$report = array(
 'generated_at' => '2026-11-01T07:00:00Z',
 'summary' => array( 'critical' => 3, 'warning' => 7, 'pass' => 10, 'info' => 2 ),
 'checks' => array( array( 'evidence' => $secret ) ),
 'meta' => array( 'release_gate' => array( 'state' => 'requires_tests', 'reasons' => array($secret) ), 'customer_data' => $secret ),
 'site_url' => $secret,
);
$manual = array( 'total' => 7, 'summary' => array( 'passed' => 2, 'failed' => 0, 'blocked' => 1, 'untested' => 4 ), 'current_manual_passed' => 2, 'entries' => array($secret), 'verification_summary' => array('expired'=>1) );
$env = array( 'type' => 'staging', 'php' => '8.4.24', 'wordpress' => '6.9.1', 'woocommerce' => '10.3.2', 'woocommerce_active' => true, 'raw_error' => $secret );
$output = HCD_Release_Snapshot::build( $report, $manual, $env, 1793516400 );
$json = json_encode( $output );
check( false === strpos( $json, $secret ), 'No private source strings' );
check( 'requires_tests' === $output['release_gate'] && 3 === $output['findings_count']['critical'], 'Expected counts and gate' );
check( 2 === $output['integration_tests']['current_manual_passed'], 'Manual count preserved' );
check( !isset($output['checks'], $output['site_url'], $output['meta']), 'No raw report' );
$env['wordpress'] = $secret;
$wrong = HCD_Release_Snapshot::build( $report, array(), $env, 1793516400 );
check( 'unknown' === $wrong['runtime']['wordpress_version'], 'Version allowlist drops arbitrary strings' );
check( 'not_evaluated' === HCD_Release_Snapshot::build( array(), array(), $env )['release_gate'], 'Empty report stays unverified' );
echo "PASS: bounded allowlist public support JSON and privacy\n";
