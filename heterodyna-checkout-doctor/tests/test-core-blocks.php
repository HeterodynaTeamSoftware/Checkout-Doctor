<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 403 ); exit; }
define('ABSPATH','/tmp/');
class WooCommerce {}
class WP_Block_Type_Registry {public static function get_instance(){return new self();}public function get_registered($name){return (object)['name'=>$name];}}
function wc_get_page_id($page) { return $page==='checkout'?21:22; }
function get_post($id) {return (object) array('post_content' => $id===21?'<!-- wp:woocommerce/checkout /-->':'<!-- wp:woocommerce/cart /-->','post_status'=>'publish','post_password'=>'');}
function has_shortcode($content,$shortcode) {return false;}
function parse_blocks($content){return array(array('blockName' => strpos($content,'checkout')!==false?'core/group':'woocommerce/cart','innerBlocks'=> strpos($content,'checkout')!==false?array(array('blockName'=>'woocommerce/checkout','innerBlocks'=>array())):array()));}
function has_block($name,$post) { return false; }
function get_option($key,$default=null){return $default;}
class FakeRest { public function get_routes(){return array('/wc/store/v1/checkout'=>array(), '/wc/store/v1/cart'=>array());}}
function rest_get_server(){return new FakeRest();}
require dirname(__DIR__).'/includes/class-hcd-checkout-core.php';
$meta=[];$r=HCD_Checkout_Core::run($meta);$by=[];foreach($r as $row)$by[$row['id']]=$row;
if ($meta['checkout_mode']!=='blocks'||$meta['cart_mode']!=='blocks'||$meta['checkout_block_count']!==1) throw new RuntimeException('Nested block mode detection failed');
if ($by['blocks_registration']['status']!=='pass'||$by['blocks_store_api_routes']['status']!=='pass')throw new RuntimeException('Registered routes/blocks not detected');
if (strpos(json_encode($r),'HTTP')!==false && strpos(json_encode($r),'GET registry')===false)throw new RuntimeException('Unexpected active check');
echo "PASS: nested Checkout Block and passive Store API route registry checks\n";
