<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' );
$options = array();
function get_option( $name, $default = false ) { global $options; return isset( $options[$name] ) ? $options[$name] : $default; }
require dirname(__DIR__) . '/includes/class-hcd-integration-tests.php';
$scenarios = HCD_Integration_Tests::scenarios();
if ( count($scenarios) !== 7 || ! isset($scenarios['gateway']) ) throw new RuntimeException('Wrong test scenarios');
$blank = HCD_Integration_Tests::snapshot();
if ($blank['summary']['untested'] !== 7 || $blank['summary']['passed'] !== 0) throw new RuntimeException('Default not untested');
// Reject arbitrary fields, invalid states, and data from customer/payment fields.
$input = array( 'classic'=>'passed', 'gateway'=>'failed', 'shipping'=>'HACKED',
    'unknown'=>'passed', 'customer_email'=>'sensitive@example.test' );
$saved = HCD_Integration_Tests::store( $input, array(), '2026-11-01T06:00:00+00:00' );
if ($saved['classic']['status'] !== 'passed' || $saved['gateway']['status'] !== 'failed'
    || $saved['shipping']['status'] !== 'untested' || isset($saved['unknown'])) throw new RuntimeException('Allowlist not enforced');
if ($saved['classic']['updated_utc'] !== '2026-11-01T06:00:00+00:00') throw new RuntimeException('Date missing');
$snap = HCD_Integration_Tests::snapshot( $saved );
if ($snap['summary']['passed'] !== 1 || $snap['summary']['failed'] !== 1 || $snap['summary']['untested'] !== 5) throw new RuntimeException('Counters incorrect');
if (strpos(json_encode($snap),'sensitive') !== false || strpos(json_encode($snap),'HACKED') !== false) throw new RuntimeException('Sensitive fields leaked');
$unchanged = HCD_Integration_Tests::store( $input, $saved, '2026-11-02T06:00:00+00:00' );
if ($unchanged['classic']['updated_utc'] !== '2026-11-01T06:00:00+00:00') throw new RuntimeException('Unchanged date modified');
$meta = array(); $options['hcd_integration_tests'] = $saved;
$checks = HCD_Integration_Tests::run( $meta );
if (count($checks) !== 1 || $checks[0]['status'] !== 'warning' || $checks[0]['confidence'] !== 'signal'
    || $meta['integration_tests']['summary']['failed'] !== 1) throw new RuntimeException('Manual results classification');
$options['hcd_integration_tests'] = HCD_Integration_Tests::store( array_fill_keys(array_keys($scenarios),'passed'), $saved );
$all = HCD_Integration_Tests::run($meta);
if ($all[0]['status'] !== 'info') throw new RuntimeException('Do not assert automated pass for manual results');
echo "PASS: seven scenarios, status allowlist, privacy, timestamps and manual scope\n";
