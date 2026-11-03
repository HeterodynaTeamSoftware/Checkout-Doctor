<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' ); define( 'DAY_IN_SECONDS', 86400 );
function get_option( $k, $d = false ) { return $d; }
require dirname(__DIR__) . '/includes/class-hcd-integration-tests.php';
$envA = HCD_Integration_Tests::environment_fingerprint(array('wordpress'=>'6.8','woocommerce'=>'9.5','theme'=>'a'));
$envB = HCD_Integration_Tests::environment_fingerprint(array('theme'=>'a','woocommerce'=>'9.5','wordpress'=>'6.8'));
$envC = HCD_Integration_Tests::environment_fingerprint(array('wordpress'=>'6.8','woocommerce'=>'9.6','theme'=>'a'));
if ($envA !== $envB || $envA === $envC) throw new RuntimeException('fingerprint nondeterministic');
$time = '2026-11-01T06:00:00+00:00'; $now = strtotime($time);
$stored = HCD_Integration_Tests::store(array('classic'=>'passed'),array(),$time,array(),$envA);
$current = HCD_Integration_Tests::snapshot($stored,$envA,$now);
if ($current['current_manual_passed'] !== 1 || $current['entries']['classic']['verification'] !== 'current_manual') throw new RuntimeException('Current attestation not recognized');
$changed = HCD_Integration_Tests::snapshot($stored,$envC,$now);
if ($changed['entries']['classic']['verification'] !== 'environment_changed' || $changed['current_manual_passed'] !== 0) throw new RuntimeException('Environment changes not flagged');
$expired = HCD_Integration_Tests::snapshot($stored,$envA,$now + 31*86400);
if ($expired['entries']['classic']['verification'] !== 'expired' || $expired['current_manual_passed'] !== 0) throw new RuntimeException('Expiration missing');
$legacy = HCD_Integration_Tests::snapshot(array('classic'=>array('status'=>'passed','updated_utc'=>$time)),$envA,$now);
if ($legacy['entries']['classic']['verification'] !== 'legacy_unverified') throw new RuntimeException('Legacy upgrade not handled');
if (strpos(json_encode($current), 'wordpress') !== false || strpos(json_encode($current), 'woocommerce') !== false) throw new RuntimeException('Environment details exposed');
echo "PASS: deterministic fingerprints, current/expired/changed/legacy attestations\n";
