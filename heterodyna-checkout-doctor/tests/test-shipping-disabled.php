<?php
if ( PHP_SAPI !== 'cli' ) { exit(1); }
define('ABSPATH','/tmp/');
function wc_shipping_enabled(){return false;}
require dirname(__DIR__).'/includes/class-hcd-payments-shipping.php';
$meta=[];$checks=HCD_Payments_Shipping::run($meta);$by=[];foreach($checks as $row)$by[$row['id']]=$row;
if($meta['shipping_enabled']!==false || $by['shipping']['status']!=='info')throw new RuntimeException('Digital shop incorrectly considered failing');
echo "PASS: intentionally disabled shipping is informational only\n";
