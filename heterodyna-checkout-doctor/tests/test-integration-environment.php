<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' );
define( 'WC_VERSION', '10.0.0' );
$GLOBALS['wp_version'] = '6.8.0';
$GLOBALS['active'] = array('woocommerce/woocommerce.php', 'another/plugin.php');
$GLOBALS['versions'] = array('woocommerce/woocommerce.php'=>array('Version'=>'10.0.0'), 'another/plugin.php'=>array('Version'=>'1.0.0'));
function get_option($id, $default = false) { return $id === 'active_plugins' ? $GLOBALS['active'] : $default; }
function get_site_option($id, $default = false) { return $default; }
function get_plugins() { return $GLOBALS['versions']; }
function get_stylesheet() { return 'heterodyna-theme'; }
class HCD_Test_Theme { public function get($key) { return $key === 'Version' ? '1.0.0' : ''; } }
function wp_get_theme() { return new HCD_Test_Theme(); }
require dirname(__DIR__) . '/includes/class-hcd-integration-tests.php';
$a = HCD_Integration_Tests::environment_fingerprint();
$GLOBALS['active'] = array_reverse($GLOBALS['active']);
if ($a !== HCD_Integration_Tests::environment_fingerprint()) throw new RuntimeException('Order changed fingerprint');
$GLOBALS['versions']['another/plugin.php']['Version'] = '1.0.1';
$b = HCD_Integration_Tests::environment_fingerprint();
if ($a === $b) throw new RuntimeException('Plugin update not detected');
$GLOBALS['active'][] = 'third/plugin.php';
$c = HCD_Integration_Tests::environment_fingerprint();
if ($b === $c) throw new RuntimeException('Plugin activation not detected');
$GLOBALS['wp_version'] = '6.8.1';
if ($c === HCD_Integration_Tests::environment_fingerprint()) throw new RuntimeException('Core update not detected');
echo "PASS: active plugins, version changes, stable ordering and WP upgrades\n";
