<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' );
require dirname( __DIR__ ) . '/includes/class-hcd-repair-reports.php';
$checks = array(
    array( 'id'=>'checkout_page','status'=>'critical','category'=>'core','confidence'=>'confirmed','title'=>'Checkout zablokowany','evidence'=>'status: private','recommendation'=>'Opublikuj stronę.' ),
    array( 'id'=>'https','status'=>'critical','category'=>'core','confidence'=>'confirmed','title'=>'Brak TLS','evidence'=>'http','recommendation'=>'Włącz HTTPS.' ),
    array( 'id'=>'payment_gateways','status'=>'warning','category'=>'payments','confidence'=>'confirmed','title'=>'Bramka','evidence'=>'brak konfiguracji','recommendation'=>'Konfiguruj bramkę.' ),
    array( 'id'=>'failed_orders','status'=>'warning','category'=>'orders','confidence'=>'signal','title'=>'Status Failed','evidence'=>'4','recommendation'=>'Przejrzyj statusy.' ),
    array( 'id'=>'logs_indicators','status'=>'warning','category'=>'logs','confidence'=>'signal','title'=>'Błędy','evidence'=>'2 wpisy','recommendation'=>'Porównaj czas.' ),
    array( 'id'=>'cart_page','status'=>'pass','category'=>'core','confidence'=>'confirmed','title'=>'Koszyk','evidence'=>'ok','recommendation'=>'-' ),
);
$base = array( 'checks'=>$checks, 'generated_at'=>'2026-11-01T10:00:00Z', 'meta'=>array( 'secret_field'=>'not-to-be-exported-as-action' ) );
$result = HCD_Repair_Reports::enrich( $base );
if ( 5 !== count( $result['repair_plan'] ) || 2 !== $result['repair_summary']['p1'] || 1 !== $result['repair_summary']['p2'] || 2 !== $result['repair_summary']['p3'] ) { throw new RuntimeException( 'Priority or filter mismatch.' ); }
if ( 'P1' !== $result['repair_plan'][0]['priority'] || 'P2' !== $result['repair_plan'][2]['priority'] || 'P3' !== $result['repair_plan'][3]['priority'] ) { throw new RuntimeException( 'Repair order incorrect.' ); }
if ( 1 !== count( $result['correlations'] ) || 'core' !== $result['correlations'][0]['category'] ) { throw new RuntimeException( 'Correlation counting incorrect.' ); }
if ( ! $result['repair_plan'][3]['validation_required'] || false !== $result['repair_plan'][0]['validation_required'] ) { throw new RuntimeException( 'Signals not distinguished.' ); }
if ( ! isset( $result['checks'][5] ) || $result['checks'][5]['status'] !== 'pass' ) { throw new RuntimeException( 'Original evidence corrupted.' ); }
if ( HCD_Repair_Reports::enrich( array( 'checks'=>array() ) )['repair_summary']['actions'] !== 0 ) { throw new RuntimeException( 'Empty report failed.' ); }
echo "PASS: plan priority, corroboration boundaries, pass preservation, empty report\n";
