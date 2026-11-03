<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 403 ); exit; }
define('ABSPATH','/tmp/');
class WooCommerce {}
function wc_get_page_id($page) { return $page==='checkout'?11:12; }
function get_post($id) {return (object) array('post_content' => $id===11?'[woocommerce_checkout]':'[woocommerce_cart]','post_status'=>'publish','post_password'=>'');}
function has_shortcode($content,$shortcode) { return strpos($content,'['.$shortcode.']')!==false; }
function has_block($name,$post) { return false; }
function get_option($key,$default=null){return array('woocommerce_enable_guest_checkout'=>'yes','woocommerce_enable_signup_and_login_from_checkout'=>'no','woocommerce_terms_page_id'=>0)[$key]??$default;}
function get_stylesheet_directory(){return '/a-fake-test-theme-which-does-not-exist';}
function get_template_directory(){return '/another-fake-test-theme-which-does-not-exist';}
function trailingslashit($s){return rtrim($s,'/').'/';}
require dirname(__DIR__).'/includes/class-hcd-checkout-core.php';
$meta=[];$r=HCD_Checkout_Core::run($meta);$by=[];foreach($r as $row)$by[$row['id']]=$row;
if ($meta['checkout_mode']!=='classic' || $meta['cart_mode']!=='classic')throw new RuntimeException('Failed classic path detection');
if ($by['core_page_collision']['status']!=='pass'||$by['core_checkout_collision']['status']!=='pass'||$by['classic_mode']['status']!=='pass')throw new RuntimeException('Incorrect classic result');
if(isset($by['blocks_store_api_routes'])) throw new RuntimeException('Store API check should be skipped for classic checkout');
echo "PASS: classic checkout checks + passive theme template inspection\n";
