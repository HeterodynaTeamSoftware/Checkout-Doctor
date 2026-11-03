<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' );
$env = 'production'; $options = array(); $storage = array();
function wp_get_environment_type() { global $env; return $env; }
function get_option($key,$default='no') {global $options; return array_key_exists($key,$options) ? $options[$key] : $default;}
function get_transient($key){ global $storage; return isset($storage[$key]) ? $storage[$key] : false; }
require dirname(__DIR__) . '/includes/class-hcd-payment-visibility.php';
function verify($condition,$msg){ if (!$condition) throw new RuntimeException($msg); }
verify(!HCD_Payment_Visibility::staging(), 'Production must be blocked');
$options['hcd_payment_visibility_enabled']='yes';
verify(!HCD_Payment_Visibility::enabled(), 'Production must remain blocked even when option enabled');
$meta=array(); $checks=HCD_Payment_Visibility::run($meta);
verify($checks[0]['id']==='visibility_staging_only' && $checks[0]['status']==='info','Production state report');
$env='staging';verify(HCD_Payment_Visibility::enabled(),'Staging must be allowed with opt-in');
foreach(array(
  array('mode'=>'block','cart'=>'nonempty','visible'=>0),
  array('mode'=>'classic','cart'=>'empty','visible'=>2),
  array('mode'=>'mixed','cart'=>'unknown','visible'=>20)
) as $valid) verify(HCD_Payment_Visibility::normalize($valid)===$valid,'Valid enum rejected');
foreach(array(
  array('mode'=>'block','cart'=>'nonempty','visible'=>'1'),
  array('mode'=>'other','cart'=>'nonempty','visible'=>2),
  array('mode'=>'block','cart'=>'customer_email','visible'=>2),
  array('mode'=>'block','cart'=>'nonempty','visible'=>21),
  array('mode'=>'block','cart'=>'nonempty','visible'=>-1),
  array('mode'=>'block','cart'=>'nonempty','visible'=>1,'email'=>'secret@example.com')
) as $invalid) {
  // Extra fields must be DROPPED, never persisted.
  $norm=HCD_Payment_Visibility::normalize($invalid);
  if(isset($invalid['email'])) verify($norm && !isset($norm['email']),'Extra fields persisted');
  else verify($norm===null,'Malformed data accepted');
}
$storage[HCD_Payment_Visibility::KEY]=array(array('mode'=>'block','cart'=>'nonempty','visible'=>0,'at'=>'2026-11-01T12:34:56+00:00','card'=>'SECRET'));
$meta=array();$checks=HCD_Payment_Visibility::run($meta);
verify(count($checks)===2 && $checks[1]['id']==='visibility_no_methods' && $checks[1]['confidence']==='signal','Expected cautious warning');
verify(strpos(json_encode($meta),'SECRET')===false,'Secret leaked into report');
$storage[HCD_Payment_Visibility::KEY]=array(array('mode'=>'block','cart'=>'empty','visible'=>0,'at'=>'2026-11-01T12:34:56+00:00'));
$checks=HCD_Payment_Visibility::run($meta);
verify(count($checks)===1,'Empty cart should not get warning');
echo "PASS: staging gate, default disable, strict enum, privacy and cautious findings\n";
