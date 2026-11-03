<?php
namespace Automattic\WooCommerce\Blocks {
    class Package { public static function container() { return new \FakeContainer(); } }
}
namespace Automattic\WooCommerce\Blocks\Payments { class PaymentMethodRegistry {} }
namespace Automattic\WooCommerce\Blocks\Domain\Services { class CheckoutFields {} }
namespace {
    if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
    define( 'ABSPATH', '/tmp/' );
    class FakePaymentRegistry { public function get_all_registered() { return array( 'stripe' => new \stdClass(), 'cheque' => new \stdClass() ); } }
    class FakeCheckoutFields {
        public function get_fields_for_location( $location ) {
            if ( 'order' === $location ) { return array( 'test/custom' => array('type'=>'text','required'=>true,'label'=>'DO_NOT_EXPORT','value'=>'PRIVATE') ); }
            return array();
        }
    }
    class FakeContainer {
        public function get( $name ) {
            if ( $name === '\\Automattic\\WooCommerce\\Blocks\\Payments\\PaymentMethodRegistry' ) { return new FakePaymentRegistry(); }
            if ( $name === '\\Automattic\\WooCommerce\\Blocks\\Domain\\Services\\CheckoutFields' ) { return new FakeCheckoutFields(); }
            throw new \RuntimeException('Unrecognized service');
        }
    }
    class FakeGatewayManager { public function payment_gateways() { return array( (object) array('id'=>'stripe','enabled'=>'yes'), (object) array('id'=>'cod','enabled'=>'yes'),(object)array('id'=>'disabled','enabled'=>'no') ); } }
    class FakeWC { public function payment_gateways() { return new FakeGatewayManager(); } }
    function WC() { return new FakeWC(); }
    require dirname( __DIR__ ) . '/includes/class-hcd-block-compat.php';
    $meta = array( 'checkout_mode' => 'blocks' );
    $checks = \HCD_Block_Compat::run( $meta );
    if ( count( $checks ) !== 4 ) { throw new \RuntimeException( 'Wrong number of findings: ' . count($checks) ); }
    if ( $meta['block_compat']['payment_status'] !== 'registry_read' || $meta['block_compat']['field_status'] !== 'registry_read' ) { throw new \RuntimeException( 'Registry not read' ); }
    if ( $meta['block_compat']['gateways']['unmatched_ids'] !== array( 'cod' ) ) { throw new \RuntimeException( 'Gateway mismatch' ); }
    if ( $meta['block_compat']['fields']['total'] !== 1 ) { throw new \RuntimeException( 'Fields not read' ); }
    if ( strpos( json_encode($meta), 'PRIVATE' ) !== false || strpos( json_encode($meta), 'DO_NOT_EXPORT' ) !== false ) { throw new \RuntimeException( 'Sensitive field data exported' ); }
    $entry = file_get_contents(dirname(__DIR__).'/heterodyna-checkout-doctor.php');
    $admin = file_get_contents(dirname(__DIR__).'/includes/class-hcd-admin.php');
    $js = file_get_contents(dirname(__DIR__).'/assets/js/admin.js');
    $scanner = file_get_contents(dirname(__DIR__).'/includes/class-hcd-scanner.php');
    foreach ( array('Version: 1.16.0', 'class-hcd-block-compat.php') as $word ) { if (strpos($entry,$word)===false) throw new \RuntimeException('Main loader missing'); }
    foreach (array('data-hcd-tab="block-compat"','data-hcd-page="block-compat"','hcd-run-block-compat') as $word ) { if(strpos($admin,$word)===false)throw new \RuntimeException('Missing UI'); }
    foreach (array('updateBlockCompat()','runScan(\'block-compat\')') as $word ) { if(strpos($js,$word)===false)throw new \RuntimeException('Missing JS'); }
    if (strpos($scanner,'HCD_Block_Compat::run')===false)throw new \RuntimeException('Scanner not integrated');
    echo "PASS: block-mode integration + privacy + admin/scanner/JS wiring\n";
}
