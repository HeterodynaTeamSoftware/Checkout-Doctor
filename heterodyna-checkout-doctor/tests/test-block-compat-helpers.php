<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' );
require dirname( __DIR__ ) . '/includes/class-hcd-block-compat.php';
$c = HCD_Block_Compat::compare_gateways( array( 'stripe', 'my_gateway' ), array( 'stripe', 'cheque' ) );
if ( $c['enabled'] !== 2 || $c['registered'] !== 2 || $c['name_matches'] !== 1 || $c['unmatched_ids'] !== array( 'my_gateway' ) ) { throw new RuntimeException( 'Gateway inventory failed' ); }
$fields = HCD_Block_Compat::summarize_fields( array(
    'contact' => array( 'a/contact' => array( 'type' => 'text', 'required' => true, 'label' => 'SECRET LABEL' ) ),
    'address' => array( 'b/address' => array( 'type' => 'select', 'required' => array('type'=>'boolean') ) ),
    'order'   => array( 'c/order' => array( 'type' => 'date', 'required' => false, 'value' => 'SENSITIVE_VALUE' ) ),
) );
if ( $fields['total'] !== 3 || $fields['required'] !== 1 || $fields['conditional_required'] !== 1 || count( $fields['samples'] ) !== 3 ) { throw new RuntimeException( 'Field inventory failed' ); }
if ( strpos( json_encode( $fields ), 'SECRET' ) !== false || strpos( json_encode( $fields ), 'SENSITIVE' ) !== false ) { throw new RuntimeException( 'Field data leaked' ); }
if ( HCD_Block_Compat::safe_id( 'hello%PAYLOAD?<SECRET>' ) !== 'hellopayloadsecret' ) { throw new RuntimeException( 'Unsafe identifier' ); }
$meta = array( 'checkout_mode' => 'classic' );
$checks = HCD_Block_Compat::run( $meta );
if ( count( $checks ) !== 1 || $meta['block_compat']['payment_status'] !== 'not_checked' ) { throw new RuntimeException( 'Classic mode not skipped' ); }
echo "PASS: gateway ID hints, fields inventory/privacy and classic-mode bypass\n";
