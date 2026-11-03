<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' );
require dirname( __DIR__ ) . '/includes/class-hcd-evidence-correlation.php';
function hcd_assert( $ok, $reason ) { if ( ! $ok ) { throw new RuntimeException( $reason ); } }
$meta = array(
    'store_api_observations' => array( 'enabled' => true, 'count' => 3, 'client_errors' => 2, 'server_errors' => 1 ),
    'technical_logs' => array( 'files_inspected' => 2, 'critical' => 1, 'error' => 4 ),
    'integration_tests' => array( 'total' => 7,
        'summary' => array( 'failed' => 2 ),
        'current_manual_passed' => 3,
        'verification_summary' => array( 'expired' => 1, 'environment_changed' => 2, 'legacy_unverified' => 1 ) ),
);
$checks = HCD_Evidence_Correlation::run( $meta );
$s = $meta['evidence_correlation'];
hcd_assert( $s['level'] === 'multiple_signal_sources' && $s['signal_source_count'] === 3, 'Source count' );
hcd_assert( $s['store_http_4xx'] === 2 && $s['store_http_5xx'] === 1, 'Status counts' );
hcd_assert( $s['manual_reverification_needed'] === 4 && $s['manual_failed'] === 2, 'Manual integration' );
hcd_assert( count( $checks ) === 3 && $checks[1]['status'] === 'warning', 'Expected careful correlation signal' );
foreach ( $checks as $check ) {
    hcd_assert( $check['confidence'] === 'signal' && $check['category'] === 'correlation' && $check['status'] !== 'critical', 'Never causal proof' );
}
$blank = array(); $checks2 = HCD_Evidence_Correlation::run( $blank );
hcd_assert( $blank['evidence_correlation']['signal_source_count'] === 0, 'Empty input' );
hcd_assert( count( $checks2 ) === 2 && $checks2[1]['id'] === 'correlation_coverage', 'Incomplete coverage' );
echo "PASS: counts, cautious correlations, empty inputs, manual revalidation\n";
