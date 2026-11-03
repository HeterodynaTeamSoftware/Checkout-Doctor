<?php
if ( PHP_SAPI !== 'cli' ) { exit(1); }
define('ABSPATH', '/tmp/');
function get_bloginfo($key) { return '6.1.9'; }
function wp_get_environment_type() { return 'unknown-new-type'; }
require dirname(__DIR__) . '/includes/class-hcd-compatibility.php';
$cases = array(
    array('6.1.9','6.2','below'), array('6.2','6.2','supported'), array('6.2.1','6.2','supported'),
    array('6.x','6.2','unknown'), array('7.0beta','6.2','unknown'), array('', '6.2', 'unknown'),
);
foreach ($cases as $row) if (HCD_Compatibility::classify_version($row[0],$row[1]) !== $row[2]) throw new RuntimeException('Version case ' . $row[0]);
$meta=array();$checks=HCD_Compatibility::run($meta);$map=array();foreach($checks as $item){$map[$item['id']]=$item;}
if ('warning'!==$map['compat_wp']['status'] || 'info'!==$map['compat_wc']['status'] || count($checks)!==3) throw new RuntimeException('No Woo state');
if ($meta['compatibility']['environment'] !== 'unknown') throw new RuntimeException('Untrusted environment string');
echo "PASS: minimum versions, missing WooCommerce, unexpected environment\n";
