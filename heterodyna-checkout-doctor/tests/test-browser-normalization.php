<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define('ABSPATH','/tmp/');
$options=array('hcd_browser_observer_enabled'=>'yes');
$transients=array();
function get_option($k,$d=false){global $options;return isset($options[$k])?$options[$k]:$d;}
function get_transient($k){global $transients; return isset($transients[$k])?$transients[$k]:false;}
require dirname(__DIR__).'/includes/class-hcd-browser-probe.php';
$unsafe=array('kind'=>'js_error','context'=>'other','bucket'=>'unknown','message'=>'SECRET_CARD_123456','stack'=>'https://private.invalid/secret');
$safe=HCD_Browser_Probe::normalize_event($unsafe);
if($safe!==array('kind'=>'js_error','context'=>'other','bucket'=>'unknown'))throw new RuntimeException('Unsafe data passed normalization');
foreach(array(
    array('kind'=>'js_error','context'=>'other','bucket'=>'unknown','message'=>'private'),
    array('kind'=>'render','context'=>'checkout','bucket'=>'none'),
    array('kind'=>'store_resource','context'=>'https://invalid.test/private','bucket'=>'slow'),
    array('kind'=>'evil','context'=>'other','bucket'=>'unknown'),
    array('kind'=>array('evil'),'context'=>'other','bucket'=>'unknown')
) as $item){
   $result=HCD_Browser_Probe::normalize_event($item);
   if($item['kind']==='js_error' && $result===null)throw new RuntimeException('Known error category rejected');
   if($item['kind']!=='js_error' && $result!==null)throw new RuntimeException('Unsafe event accepted');
}
$transients[HCD_Browser_Probe::KEY]=array(
    array('kind'=>'js_error','context'=>'other','bucket'=>'unknown','at'=>'2026-11-01T00:00:00+00:00','message'=>'VERY_SECRET'),
    array('kind'=>'store_resource','context'=>'checkout','bucket'=>'slow','at'=>'2026-11-01T00:00:00+00:00'),
    array('kind'=>'render','context'=>'missing','bucket'=>'none','at'=>'2026-11-01T00:00:00+00:00')
);
$m=array();$checks=HCD_Browser_Probe::run($m);$by=array();foreach($checks as $c){$by[$c['id']]=$c;}
foreach(array('browser_coverage','browser_js_signals','browser_slow_store','browser_missing_form') as $id){
 if(!isset($by[$id])||$by[$id]['category']!=='browser')throw new RuntimeException('Missing finding '.$id);
}
if($by['browser_js_signals']['status']!=='warning'||$by['browser_js_signals']['confidence']!=='signal')throw new RuntimeException('Misclassified signal');
if($m['browser_probe']['sample_count']!==3 || strpos(json_encode($m),'VERY_SECRET')!==false || strpos(json_encode($checks),'VERY_SECRET')!==false)throw new RuntimeException('Private value leaked');
$options['hcd_browser_observer_enabled']='no';$m=array();$checks=HCD_Browser_Probe::run($m);
if(count($checks)!==1 || $checks[0]['id']!=='browser_disabled')throw new RuntimeException('Disabled state not handled');
echo "PASS: strict fixed enums, JS categories, safe aggregated signals and disabled mode\n";
