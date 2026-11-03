<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
$test_root = sys_get_temp_dir() . '/hcd-check-hooks-' . getmypid();
@mkdir( $test_root . '/plugin_alpha', 0777, true );
@mkdir( $test_root . '/plugin_beta', 0777, true );
file_put_contents( $test_root . '/plugin_alpha/hooks.php', '<?php function hcd_test_alpha() {}' );
file_put_contents( $test_root . '/plugin_beta/hooks.php', '<?php function hcd_test_beta() {}' );
define( 'ABSPATH', '/tmp/' );
define( 'WP_PLUGIN_DIR', $test_root );
require $test_root . '/plugin_alpha/hooks.php'; require $test_root . '/plugin_beta/hooks.php';
class HCD_WP_Hook_Stub { public $callbacks; public function __construct( $callbacks ) { $this->callbacks = $callbacks; } }
$wp_filter = array( 'woocommerce_checkout_fields' => new HCD_WP_Hook_Stub( array(
    10 => array( 'a' => array( 'function' => 'hcd_test_alpha' ), 'b' => array( 'function' => 'hcd_test_beta' ) )
) ) );
require dirname( __DIR__ ) . '/includes/class-hcd-conflicts-logs.php';
$meta = array(); $rows = HCD_Conflicts_Logs::run( $meta );
$entry = null; foreach ( $rows as $r ) { if ( $r['id'] === 'conflicts_shared_hooks' ) { $entry = $r; } }
if ( 1 !== $meta['checkout_hooks_with_multiple_plugin_owners'] ) { throw new RuntimeException( 'Multiple hook owners not found' ); }
if ( 'info' !== $entry['status'] || 'signal' !== $entry['confidence'] ) { throw new RuntimeException( 'Hook overlap incorrectly classified as confirmed failure' ); }
if ( false !== strpos( json_encode( $rows ), $test_root ) ) { throw new RuntimeException( 'Absolute hook path leaked' ); }
if ( HCD_Conflicts_Logs::owner_for_file( '/other/path/plugin_alpha/file.php', $test_root ) !== '' ) { throw new RuntimeException( 'Prefix traversal mapping failed' ); }
@unlink( $test_root . '/plugin_alpha/hooks.php' ); @unlink( $test_root . '/plugin_beta/hooks.php' );
@rmdir( $test_root . '/plugin_alpha' ); @rmdir( $test_root . '/plugin_beta' ); @rmdir( $test_root );
echo "PASS: reflection-based hook inventory, false-positive avoidance and path privacy\n";
