<?php
if(PHP_SAPI!=='cli')exit(1);
define('ABSPATH','/tmp/');define('MINUTE_IN_SECONDS',60);
$auth=false;$options=['hcd_browser_observer_enabled'=>'yes'];$transients=[];$last=null;
function get_option($k,$d=false){global $options;return isset($options[$k])?$options[$k]:$d;}
function is_user_logged_in(){global $auth;return $auth;}
function current_user_can($cap){global $auth;return $auth;}
function get_current_user_id(){return 72;}
function absint($value){return abs((int)$value);}
function get_transient($key){global $transients;return $transients[$key]??false;}
function set_transient($key,$v,$ttl){global $transients;$transients[$key]=$v;}
function delete_transient($key){global $transients;unset($transients[$key]);}
function check_ajax_referer($what,$field){if($what!=='hcd_browser_probe'||$field!=='nonce')throw new RuntimeException('Invalid nonce validation');}
function wp_unslash($str){return $str;}
function wp_send_json_error($payload,$code){throw new RuntimeException('HTTP_'.$code);}
function wp_send_json_success($payload){global $last;$last=$payload;throw new RuntimeException('HTTP_200');}
require dirname(__DIR__).'/includes/class-hcd-browser-probe.php';
$_POST['events']=json_encode([['kind'=>'js_error','context'=>'other','bucket'=>'unknown','message'=>'SECRET_TOKEN'],['kind'=>'render','context'=>'block','bucket'=>'none']]);
try{HCD_Browser_Probe::ajax_receive();throw new RuntimeException('Unexpected success');}catch(RuntimeException $e){if($e->getMessage()!=='HTTP_403')throw $e;}
$auth=true;
try{HCD_Browser_Probe::ajax_receive();throw new RuntimeException('Unexpected return');}catch(RuntimeException $e){if($e->getMessage()!=='HTTP_200')throw $e;}
if($last['accepted']!==2||count($transients[HCD_Browser_Probe::KEY])!==2)throw new RuntimeException('Invalid accepted events');
if(strpos(json_encode($transients[HCD_Browser_Probe::KEY]),'SECRET_TOKEN')!==false)throw new RuntimeException('Untrusted field stored');
$_POST['events']=str_repeat('x',2049);
try{HCD_Browser_Probe::ajax_receive();throw new RuntimeException('Oversize accepted');}catch(RuntimeException $e){if($e->getMessage()!=='HTTP_400')throw $e;}
$transients['hcd_browser_rate_72']=12;
$_POST['events']='[]';
try{HCD_Browser_Probe::ajax_receive();throw new RuntimeException('Rate limit bypassed');}catch(RuntimeException $e){if($e->getMessage()!=='HTTP_429')throw $e;}
echo "PASS: AJAX privilege, enums, data minimization, payload bound and rate limit\n";
