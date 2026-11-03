<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' );
require dirname( __DIR__ ) . '/includes/class-hcd-evidence-correlation.php';
$meta = array(
 'store_api_observations' => array('enabled'=>true,'count'=>1,'server_errors'=>1,
    'samples'=>array(array('card'=>'4111111111111111','email'=>'john@private.test','at'=>'2026-11-01T10:00:00Z'))),
 'technical_logs' => array('files_inspected'=>1,'critical'=>1,'error'=>0,'raw'=>'Authorization: Bearer secret_token'),
 'integration_tests' => array('total'=>7,'summary'=>array('failed'=>0), 'entries'=>array('payments'=>array('private'=>'sensitive'))),
);
$checks = HCD_Evidence_Correlation::run($meta);
$output = json_encode(array($checks,$meta['evidence_correlation']));
foreach(array('4111111111111111','john@private.test','secret_token','sensitive','2026-11-01T10:00:00Z') as $secret) if(strpos($output,$secret)!==false) throw new RuntimeException('Leak: '.$secret);
if($meta['evidence_correlation']['store_samples']!==1 || $meta['evidence_correlation']['store_http_5xx']!==1)throw new RuntimeException('Sample count');
// Malicious/invalid metadata is constrained to non-negative bounded integers, fixed shapes.
$bad = HCD_Evidence_Correlation::summarize(array('store_api_observations'=>array('enabled'=>true,'count'=>1,'client_errors'=>80000000,'server_errors'=>20000000), 'technical_logs'=>array('files_inspected'=>0,'critical'=>10,'error'=>10)));
if($bad['store_http_4xx']!==1 || $bad['store_http_5xx']!==0 || $bad['local_log_critical_lines']!==0)throw new RuntimeException('Unbounded or invalid counters');
echo "PASS: privacy, normalization, sample capping, no raw events or logs\n";
