<?php
if ( PHP_SAPI !== 'cli' ) { exit(1); }
define( 'ABSPATH', '/tmp/' ); define( 'WC_VERSION', '10.0.0' );
class WooCommerce {}
function get_bloginfo($key) { return '6.6.2'; }
function wp_get_environment_type() { return 'staging'; }
function wc_get_orders() { return array(); }
function wc_get_page_id($page) { return 12; }
function rest_get_server() { return null; }
require dirname(__DIR__) . '/includes/class-hcd-compatibility.php';
$meta = array(); $items = HCD_Compatibility::run($meta);
$map = array(); foreach($items as $item) { $map[$item['id']] = $item; }
foreach(array('compat_wp','compat_php','compat_wc','compat_core_apis','compat_block_api','compat_scheduler') as $key) {
    if (!isset($map[$key]) || 'compatibility' !== $map[$key]['category']) throw new RuntimeException('Missing '.$key);
}
if ('pass' !== $map['compat_wp']['status'] || 'pass' !== $map['compat_php']['status'] || 'pass' !== $map['compat_core_apis']['status']) throw new RuntimeException('Version/API status');
if ('info' !== $map['compat_wc']['status'] || 'signal' !== $map['compat_block_api']['confidence']) throw new RuntimeException('Unsupported certainty');
if ($meta['compatibility']['environment'] !== 'staging' || $meta['compatibility']['woocommerce'] !== '10.0.0') throw new RuntimeException('Inventory metadata');
if (count($meta['compatibility']['features']) !== 6) throw new RuntimeException('Feature inventory not bounded');
foreach ($items as $entry) {
    $line = implode(' ', array($entry['evidence'], $entry['meaning'], $entry['recommendation']));
    if (strpos($line, 'https://example') !== false || strpos($line, 'secret') !== false) throw new RuntimeException('Unexpected private content');
}
echo "PASS: compatibility API inventory and cautious findings\n";
