<?php
if ( PHP_SAPI !== 'cli' ) { exit(1); }
define('ABSPATH','/tmp/'); define('MINUTE_IN_SECONDS',60);
$env='production';$options=array('hcd_store_api_observer_enabled'=>'yes');$storage=array();$auth=true;$logged=true;$nonce_valid=true;
function wp_get_environment_type(){global $env;return $env;}
function get_option($key,$default=false){global $options;return array_key_exists($key,$options)?$options[$key]:$default;}
function get_transient($key){global $storage;return isset($storage[$key])?$storage[$key]:false;}
function set_transient($key,$value,$ttl){global $storage;$storage[$key]=$value;return true;}
function is_user_logged_in(){global $logged;return $logged;}
function current_user_can($cap){global $auth;return $auth;}
function get_current_user_id(){return 42;}
function absint($number){return abs((int)$number);}
function wp_unslash($value){return stripslashes($value);}
function check_ajax_referer($action,$field){global $nonce_valid;if(!$nonce_valid)throw new RuntimeException('rejected:nonce');}
function wp_send_json_error($value,$code=400){throw new RuntimeException('error:'.$code);}
function wp_send_json_success($value){throw new RuntimeException('success:'.(int)$value['accepted']);}
require dirname(__DIR__).'/includes/class-hcd-store-api-observer.php';
function expect_response($code){try{HCD_Store_API_Observer::receive();}catch(RuntimeException $e){if($e->getMessage()!==$code)throw $e;return;}throw new RuntimeException('No response');}
$_POST=array('observations'=>'[{"area":"checkout","method":"POST","status":500}]','nonce'=>'test');
expect_response('error:403');
$env='staging';$auth=false;expect_response('error:403');$auth=true;
$nonce_valid=false;expect_response('rejected:nonce');$nonce_valid=true;
$_POST['observations']='[{"area":"checkout","method":"POST","status":200}]';expect_response('success:0');
$_POST['observations']='[{"area":"checkout","method":"POST","status":500,"token":"TOP_SECRET","url":"/private"}]';expect_response('success:1');
$s=$storage[HCD_Store_API_Observer::KEY];
if(count($s)!==1||isset($s[0]['token'])||isset($s[0]['url'])||$s[0]['status']!==500)throw new RuntimeException('Private payload persisted');
$storage['hcd_store_api_rate_42']=12;expect_response('error:429');
echo "PASS: AJAX staging gate, permissions, nonce, rate limit, allowlist, no secrets\n";
