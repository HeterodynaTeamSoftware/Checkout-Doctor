<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' );
require dirname( __DIR__ ) . '/includes/class-hcd-repair-reports.php';
$bad = '"<script>alert(1)</script>';
$report = HCD_Repair_Reports::enrich( array( 'generated_at'=>'2026-11-01Z', 'checks'=>array(
    array( 'id'=>'payment_gateways','status'=>'warning','confidence'=>'signal','category'=>'payments', 'title'=>$bad, 'evidence'=>'=HYPERLINK("https://evil")','recommendation'=>'  +cmd' ),
    array( 'id'=>'checkout_page','status'=>'critical','confidence'=>'confirmed','category'=>'core','title'=>'ok', 'evidence'=>'@formula', 'recommendation'=>'-1+2' ),
) ) );
$rows = HCD_Repair_Reports::csv_rows( $report );
if ( count( $rows ) !== 3 || substr( $rows[2][4], 0, 1 ) !== "'" || substr( $rows[2][6], 0, 1 ) !== "'" ) { throw new RuntimeException( 'CSV formula escaping failed.' ); }
if ( HCD_Repair_Reports::csv_cell( "\t=1+2" ) !== "'\t=1+2" ) { throw new RuntimeException( 'Tab escaping failed.' ); }
if ( HCD_Repair_Reports::csv_cell( 'safe' ) !== 'safe' ) { throw new RuntimeException( 'Clean CSV cell altered.' ); }
$html = HCD_Repair_Reports::html_report( $report );
if ( strpos( $html, '<script>' ) !== false || strpos( $html, '&lt;script&gt;' ) === false ) { throw new RuntimeException( 'HTML injection failed.' ); }
if ( strpos( $html, 'P1' ) === false || strpos( $html, 'P3' ) === false || strpos( $html, 'Jak potwierdzić' ) === false ) { throw new RuntimeException( 'Report incomplete.' ); }
if ( strpos( $html, 'src="http' ) !== false ) { throw new RuntimeException( 'External assets found.' ); }
echo "PASS: safe CSV cells, HTML escape, report completeness\n";
