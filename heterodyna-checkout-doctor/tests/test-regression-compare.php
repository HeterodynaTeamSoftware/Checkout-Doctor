<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' );
require dirname(__DIR__).'/includes/class-hcd-readiness.php';
function issue($id,$status,$category='core',$confidence='confirmed') { return array('id'=>$id, 'status'=>$status, 'category'=>$category,'confidence'=>$confidence,'title'=>'Sygnał '.$id); }
$old = array('generated_at'=>'2026-10-31T08:00:00Z','version'=>'1.5.0','checks'=>array(issue('a','critical'),issue('b','pass'),issue('c','warning'),issue('d','warning'),issue('e','warning')));
$new = array('generated_at'=>'2026-11-01T08:00:00Z','version'=>'1.5.0','checks'=>array(issue('a','pass'),issue('b','critical'),issue('c','critical'),issue('d','warning'),issue('f','warning')));
$r=HCD_Readiness::compare($new,$old);
if (! $r['available'] || $r['counts']['cleared'] !== 1 || $r['counts']['escalated'] !== 2 || $r['counts']['new'] !== 1 || $r['counts']['unavailable'] !== 1 || count($r['changes']) !== 5) { throw new RuntimeException('Regression status comparison failure: '.json_encode($r['counts'])); }
if (HCD_Readiness::compare($new,array())['available']) { throw new RuntimeException('Missing baseline should not be available.'); }
$old['version']='1.4.0';
$upgrade=HCD_Readiness::compare($new,$old);
if($upgrade['counts']['new_check'] !== 1 || $upgrade['counts']['new'] !== 0) { throw new RuntimeException('New check after upgrade must not be classified as new defect.'); }
$old['checks'][]=issue('duplicate','warning'); $old['checks'][]=issue('duplicate','critical');
$duplicates=HCD_Readiness::compare($new,$old);
if($duplicates['counts']['unavailable'] !== 1) { throw new RuntimeException('Duplicate keys not safely ignored.'); }
echo "PASS: regression, clear, escalation, new-version check, absent, duplicate\n";
