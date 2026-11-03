<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' );
define( 'HCD_VERSION', '1.16.0' );
define( 'HCD_URL', 'https://example.invalid/wp-content/plugins/heterodyna-checkout-doctor/' );
class HCD_Events { public static function enabled(){ return false; } }
function current_user_can($cap){ return true; }
function get_option($key, $default=null){ return $default; }
function esc_url($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function esc_html($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function admin_url($url){ return '/wp-admin/' . $url; }
function wp_nonce_url($url,$nonce){ return $url . '&_wpnonce=testing'; }
function wp_nonce_field($action){ return; }
function checked($actual){ return ''; }
function selected($actual,$expected){ return ''; }
function wp_die($s){ throw new RuntimeException('unexpected wp_die'); }
require dirname(__DIR__) . '/includes/class-hcd-browser-probe.php';
require dirname(__DIR__) . '/includes/class-hcd-payment-visibility.php';
require dirname(__DIR__) . '/includes/class-hcd-store-api-observer.php';
function disabled($disabled) { return $disabled ? 'disabled' : ''; }
require dirname(__DIR__) . '/includes/class-hcd-integration-tests.php';
require dirname(__DIR__) . '/includes/class-hcd-admin.php';
ob_start(); HCD_Admin::render(); $html = ob_get_clean();
$required = array('data-hcd-tab="repairs"', 'data-hcd-page="repairs"', 'id="hcd-repair-list"', 'id="hcd-correlations"', 'id="hcd-repair-p1"', 'id="hcd-repair-p2"', 'id="hcd-repair-p3"', 'id="hcd-run-repairs"', 'format=csv', 'format=html', 'heterodyna-original.webp', 'data-hcd-tab="browser"', 'id="hcd-run-browser"', 'name="hcd_browser_observer_enabled"', 'data-hcd-tab="store-api"', 'data-hcd-page="store-api"', 'id="hcd-run-store-api"', 'name="hcd_store_api_observer_enabled"', 'data-hcd-tab="integration"', 'data-hcd-page="integration"', 'hcd_save_integration_tests', 'hcd_export_integration_tests');
foreach ($required as $needle) { if (strpos($html, $needle) === false) throw new RuntimeException('Missing UI binding: ' . $needle); }
if (preg_match_all('/id="([^"]+)"/', $html, $matches)) {
    if (count($matches[1]) !== count(array_unique($matches[1]))) throw new RuntimeException('Repeated HTML id.');
}
echo "PASS: admin markup + repair controls + export links + original logo\n";
