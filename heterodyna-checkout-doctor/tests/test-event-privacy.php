<?php
if ( PHP_SAPI !== 'cli' ) { http_response_code( 403 ); exit; }
define('ABSPATH','/tmp/');
class HCD_Events {public static $last=[]; public static function enabled(){return true;}public static function record(...$args){self::$last=$args;} }
class HCD_Admin {}
function add_action(){} function add_filter(){} function is_wp_error($x){return $x instanceof TestWPError;}
class TestWPError{public function has_errors(){return true;} public function get_error_codes(){return ['billing_john@example.com'];}}
require dirname(__DIR__).'/includes/class-hcd-plugin.php';
HCD_Plugin::classic_checkout_validation([],new TestWPError());
if(HCD_Events::$last !== ['classic_validation','classic','validation']) throw new RuntimeException('PII in validation event');
$method=(new ReflectionClass('HCD_Plugin'))->getMethod('error_group');
foreach(['myname@example.com','random_sensitive_token'] as $x) if($method->invoke(null,$x)!=='other')throw new RuntimeException('Raw code might leak');
echo "PASS: arbitrary error codes map to controlled categories\n";
