<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' );
$GLOBALS['mock_options'] = array( 'hcd_last_scan' => array(
    'meta' => array( 'store_api_observations' => array( 'count' => 2 ), 'evidence_correlation' => array( 'store_samples' => 2 ), 'woocommerce_version' => '10' ),
    'checks' => array(
        array('id'=>'store_observer_samples','category'=>'store_api','status'=>'warning','confidence'=>'signal'),
        array('id'=>'correlation_store_logs','category'=>'correlation','status'=>'warning','confidence'=>'signal'),
        array('id'=>'https','category'=>'core','status'=>'pass','confidence'=>'confirmed') ),
    'summary' => array( 'warning' => 2, 'pass' => 1, 'critical' => 0, 'info' => 0 )
) );
function get_option( $id, $default = false ) { return isset( $GLOBALS['mock_options'][$id] ) ? $GLOBALS['mock_options'][$id] : $default; }
function update_option( $id, $value, $autoload = null ) { $GLOBALS['mock_options'][$id] = $value; return true; }
require dirname( __DIR__ ) . '/includes/class-hcd-store-api-observer.php';
require dirname( __DIR__ ) . '/includes/class-hcd-repair-reports.php';
HCD_Store_API_Observer::clear_archive();
$s = get_option('hcd_last_scan');
if ( isset( $s['meta']['store_api_observations'], $s['meta']['evidence_correlation'] ) || isset( $s['meta']['evidence_correlation'] ) || isset( $s['meta']['store_api_observations'] ) ) {
    throw new RuntimeException( 'Stale archive metadata retained' );
}
if ( count($s['checks'])!==1 || $s['checks'][0]['id']!=='https' || $s['summary']['warning']!==0 || $s['summary']['pass']!==1 ) {
    throw new RuntimeException( 'Stale correlated checks retained' );
}
echo "PASS: deleting Store API observation archive clears correlated signals too\n";
