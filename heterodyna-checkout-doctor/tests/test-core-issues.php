<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 403 ); exit; }
define('ABSPATH','/tmp/');
class WooCommerce {}
function wc_get_page_id($page){return 9;}
function get_post($id){return (object)array('post_content'=>'[woocommerce_checkout]<!-- wp:woocommerce/checkout /-->','post_status'=>$id===13?'draft':'publish','post_password'=>'secret-not-for-export');}
function has_shortcode($content,$shortcode){return strpos($content,'['.$shortcode.']')!==false;}
function has_block($name,$post){return $name==='woocommerce/checkout';}
function get_option($key,$default=null){if($key==='woocommerce_terms_page_id')return 13;return $default;}
require dirname(__DIR__).'/includes/class-hcd-checkout-core.php';
$meta=[];$r=HCD_Checkout_Core::run($meta);$by=[];foreach($r as $row)$by[$row['id']]=$row;
foreach(['core_page_collision','core_checkout_password'] as $k)if($by[$k]['status']!=='critical')throw new RuntimeException('Expected confirmed critical '.$k);
if($by['core_checkout_collision']['status']!=='warning')throw new RuntimeException('Duplicate renderer not detected');
if(strpos(json_encode($r),'secret-not-for-export')!==false)throw new RuntimeException('Password exposed in report');
if($by['core_terms']['status']!=='warning')throw new RuntimeException('Unpublished terms not flagged');
echo "PASS: collision, page protection, mixed renderer, terms and private data\n";
