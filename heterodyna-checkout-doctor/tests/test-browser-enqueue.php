<?php
if(PHP_SAPI!=='cli')exit(1);
define('ABSPATH','/tmp/');define('HCD_URL','https://example.invalid/hts/');define('HCD_VERSION','1.7.0');
$logged=false;$allowed=false;$checkout=true;$received=false;$options=array('hcd_browser_observer_enabled'=>'yes');$scripts=array();$data=array();
function get_option($k,$d=false){global $options;return isset($options[$k])?$options[$k]:$d;}
function is_user_logged_in(){global $logged;return $logged;}
function current_user_can($role){global $allowed;return $allowed;}
function is_checkout(){global $checkout;return $checkout;}
function is_order_received_page(){global $received;return $received;}
function wp_enqueue_script($name,$url,$deps,$ver,$foot){global $scripts;$scripts[]=array($name,$url,$ver,$foot);}
function wp_localize_script($name,$label,$cfg){global $data;$data=array($name,$label,$cfg);}
function wp_create_nonce($name){return 'nonce_test';}
function admin_url($path){return 'https://example.invalid/wp-admin/'.$path;}
require dirname(__DIR__).'/includes/class-hcd-browser-probe.php';
HCD_Browser_Probe::enqueue();if(count($scripts))throw new RuntimeException('Enqueued for anonymous customer');
$logged=true;HCD_Browser_Probe::enqueue();if(count($scripts))throw new RuntimeException('Enqueued without role');
$allowed=true;$received=true;HCD_Browser_Probe::enqueue();if(count($scripts))throw new RuntimeException('Enqueued on order received');
$received=false;$checkout=false;HCD_Browser_Probe::enqueue();if(count($scripts))throw new RuntimeException('Enqueued on other page');
$checkout=true;$options['hcd_browser_observer_enabled']='no';HCD_Browser_Probe::enqueue();if(count($scripts))throw new RuntimeException('Enqueued when disabled');
$options['hcd_browser_observer_enabled']='yes';HCD_Browser_Probe::enqueue();if(count($scripts)!==1 || $scripts[0][0]!=='hcd-browser-observer' || $data[1]!=='HCD_BROWSER_PROBE')throw new RuntimeException('Did not enqueue for permitted tester');
if(count($data[2])!==2 || array_key_exists('customer_data',$data[2]))throw new RuntimeException('Unsafe browser settings');
echo "PASS: no customer monitoring, permission + opt-in + checkout-only probe\n";
