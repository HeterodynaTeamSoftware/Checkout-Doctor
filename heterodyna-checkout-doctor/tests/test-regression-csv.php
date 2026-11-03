<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' );
require dirname(__DIR__).'/includes/class-hcd-repair-reports.php';
require dirname(__DIR__).'/includes/class-hcd-readiness.php';
$rows = HCD_Readiness::csv_rows(array('changes'=>array(array('type'=>'new','category'=>'payments','id'=>'x','title'=>'\t=cmd','from'=>'info','to'=>'warning','confidence'=>'signal'),array('type'=>'changed','category'=>'core','id'=>'y','title'=>'  +cmd','from'=>'warning','to'=>'info','confidence'=>'signal'))));
if (count($rows)!==3 || $rows[2][3][0] !== "'") { throw new RuntimeException('CSV leading whitespace formula protection failed.'); }
// Explicit real tab (not a literal backslash-t).
$rows = HCD_Readiness::csv_rows(array('changes'=>array(array('type'=>'new','category'=>'core','id'=>'x','title'=>"\t=cmd",'from'=>'info','to'=>'warning','confidence'=>'signal'))));
if (substr($rows[1][3],0,1) !== "'") { throw new RuntimeException('Tab formula protection failed.'); }
echo "PASS: comparison CSV formula escaping\n";
