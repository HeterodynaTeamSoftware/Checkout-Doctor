<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' );
require dirname( __DIR__ ) . '/includes/class-hcd-repair-reports.php';
$legacy = array( 'version'=>'1.3.0','generated_at'=>'2026-11-01T05:00:00Z', 'checks'=>array(
    array('id'=>'shipping_zone_12','status'=>'warning','category'=>'shipping','confidence'=>'signal','title'=>'Strefa niepełna','evidence'=>'brak metody','recommendation'=>'Sprawdź metody'),
) );
$one = HCD_Repair_Reports::enrich( $legacy );
$two = HCD_Repair_Reports::enrich( $one );
if ( $one !== $two || 'shipping' !== $one['repair_plan'][0]['category'] ) { throw new RuntimeException( 'Legacy report not upgraded deterministically.' ); }
if ( $one['repair_plan'][0]['priority'] !== 'P3' || strpos( $one['repair_plan'][0]['verify'], 'stagingu' ) === false ) { throw new RuntimeException( 'Legacy signal mislabeled.' ); }
if ( HCD_Repair_Reports::enrich( null ) !== null ) { throw new RuntimeException( 'Invalid report not handled.' ); }
echo "PASS: previous 1.3.0 scan upgrade, idempotence, shipping guidance\n";
