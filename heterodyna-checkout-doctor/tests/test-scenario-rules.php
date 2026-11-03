<?php
if ( PHP_SAPI !== 'cli' ) { exit(1); }
define( 'ABSPATH', '/tmp/' );
require dirname( __DIR__ ) . '/includes/class-hcd-scenarios.php';
function verify( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
$rows = HCD_Scenarios::cod_scenarios( 'no', array('local_pickup') );
verify( count($rows) === 3, 'Three fixed scenarios expected' );
verify( $rows[0]['result'] === 'config_restriction', 'Virtual COD disable not found' );
verify( $rows[1]['result'] === 'config_restriction', 'Standard rate restriction not found' );
verify( $rows[2]['result'] === 'config_condition', 'Pickup allowance not found' );
$rows = HCD_Scenarios::cod_scenarios( 'yes', array('flat_rate:4') );
verify( $rows[0]['result'] === 'dynamic', 'Virtual setting should be a hint, not guarantee' );
verify( $rows[1]['result'] === 'config_condition', 'Instanced shipping not recognized' );
verify( HCD_Scenarios::safe_id('VISA <script>alert(1)') === 'visascriptalert1', 'Unsafe identifiers' );
verify( HCD_Scenarios::normalize_methods(array('local_pickup','local_pickup', array('secret'=>'PRIVATE'), 'flat_rate')) === array('local_pickup','flat_rate'), 'Normalization' );
$a = HCD_Scenarios::free_shipping_rule('both','100',false);
verify( $a['coupon_conflict'] === true && $a['has_minimum'], 'Required coupon contradiction' );
$b = HCD_Scenarios::free_shipping_rule('either','100',false);
verify( $b['coupon_conflict'] === false, 'Either logic cannot be unconditional coupon conflict' );
$c = HCD_Scenarios::free_shipping_rule('strange','',false);
verify( $c['status'] === 'dynamic' && $c['condition'] === 'unknown', 'Custom rule should be unknown' );
echo "PASS: static COD and free shipping conditions, bounds and cautious interpretation\n";
