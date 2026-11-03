<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' ); define( 'DAY_IN_SECONDS', 86400 );
function get_option( $k, $d = false ) { return $d; }
require dirname(__DIR__) . '/includes/class-hcd-integration-tests.php';
$A = str_repeat('a',64); $B = str_repeat('b',64); $old = '2026-11-01T06:00:00+00:00'; $new = '2026-11-02T06:00:00+00:00';
$old_record = HCD_Integration_Tests::store(array('classic'=>'passed'), array(), $old, array(), $A);
$unchanged = HCD_Integration_Tests::store(array('classic'=>'passed'),$old_record,$new,array(),$B);
if ($unchanged['classic']['updated_utc'] !== $old || $unchanged['classic']['environment_hash'] !== $A) throw new RuntimeException('Silent re-attestation');
$reconfirmed = HCD_Integration_Tests::store(array('classic'=>'passed'),$old_record,$new,array('classic'=>'1'),$B);
if ($reconfirmed['classic']['updated_utc'] !== $new || $reconfirmed['classic']['environment_hash'] !== $B) throw new RuntimeException('Explicit re-attestation failed');
$invalid = HCD_Integration_Tests::store(array('classic'=>'passed'),$old_record,$new,array('classic'=>'yes'),$B);
if ($invalid['classic']['updated_utc'] !== $old) throw new RuntimeException('Untrusted confirmation accepted');
$reset = HCD_Integration_Tests::store(array('classic'=>'untested'),$reconfirmed,$new,array(),$B);
if ($reset['classic']['environment_hash'] !== '' || $reset['classic']['updated_utc'] !== '') throw new RuntimeException('Reset did not erase proof');
$injected = HCD_Integration_Tests::normalize(array('classic'=>array('status'=>'passed','updated_utc'=>$old,'environment_hash'=>str_repeat('z',64),'user_email'=>'person@example.test')));
if ($injected['classic']['environment_hash'] !== '' || isset($injected['classic']['user_email'])) throw new RuntimeException('Unsafe fields retained');
echo "PASS: no silent renewal, explicit re-confirmation, reset and private allowlist\n";
