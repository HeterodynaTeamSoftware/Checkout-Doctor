<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
$test_root = sys_get_temp_dir() . '/hcd-check-templates-' . getmypid();
@mkdir( $test_root . '/theme/woocommerce/checkout', 0777, true );
@mkdir( $test_root . '/woo/templates/checkout', 0777, true );
file_put_contents( $test_root . '/theme/woocommerce/checkout/form-checkout.php', "<?php /** Woo override @version 3.0.0 */" );
file_put_contents( $test_root . '/woo/templates/checkout/form-checkout.php', "<?php /** WC original @version 10.2.0 */" );
define( 'ABSPATH', '/tmp/' );
function get_stylesheet_directory() { global $test_root; return $test_root . '/theme'; }
function get_template_directory() { global $test_root; return $test_root . '/theme'; }
class HCD_Woo_For_Test { public function plugin_path() { global $test_root; return $test_root . '/woo'; } }
function WC() { return new HCD_Woo_For_Test(); }
require dirname( __DIR__ ) . '/includes/class-hcd-conflicts-logs.php';
$meta = array();
$rows = HCD_Conflicts_Logs::run( $meta );
$by = array(); foreach ( $rows as $r ) { $by[$r['id']] = $r; }
if ( 1 !== $meta['theme_override_count'] || 1 !== $meta['theme_override_older_count'] ) { throw new RuntimeException( 'Template comparison failed' ); }
if ( $by['conflicts_template_versions']['status'] !== 'warning' ) { throw new RuntimeException( 'Outdated template not flagged' ); }
if ( false !== strpos( json_encode( array( $meta, $rows ) ), $test_root ) ) { throw new RuntimeException( 'Absolute path leaked' ); }
@unlink( $test_root . '/theme/woocommerce/checkout/form-checkout.php' );
@unlink( $test_root . '/woo/templates/checkout/form-checkout.php' );
@rmdir( $test_root . '/theme/woocommerce/checkout' ); @rmdir( $test_root . '/theme/woocommerce' ); @rmdir( $test_root . '/theme' );
@rmdir( $test_root . '/woo/templates/checkout' ); @rmdir( $test_root . '/woo/templates' ); @rmdir( $test_root . '/woo' ); @rmdir( $test_root );
echo "PASS: version-aware WC theme override comparison and path privacy\n";
