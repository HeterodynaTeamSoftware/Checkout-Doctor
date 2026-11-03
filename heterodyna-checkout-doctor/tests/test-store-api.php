<?php
/* Contract check: no event for GET /checkout or POST /cart; categorizes Store API errors only. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 403 ); exit; }
define( 'ABSPATH', '/tmp/' );
class HCD_Events { public static $records = []; public static function enabled(){return true;} public static function record(...$args){self::$records[]=$args;} }
class HCD_Admin {}
class WP_REST_Request {private $method; private $route;public function __construct($m,$r){$this->method=$m;$this->route=$r;} public function get_method(){return $this->method;}public function get_route(){return $this->route;}}
class WP_HTTP_Response {private $status;private $data;public function __construct($status,$data){$this->status=$status;$this->data=$data;}public function get_status(){return $this->status;}public function get_data(){return $this->data;}}
require dirname(__DIR__) . '/includes/class-hcd-plugin.php';
$api = new WP_REST_Request('POST','/wc/store/v1/checkout');
HCD_Plugin::store_api_response(new WP_HTTP_Response(402,['code'=>'payment_gateway_error_joe@private.test']),null,$api);
HCD_Plugin::store_api_response(new WP_HTTP_Response(200,[]),null,$api);
HCD_Plugin::store_api_response(new WP_HTTP_Response(400,['code'=>'payment_failed']),null,new WP_REST_Request('GET','/wc/store/v1/checkout'));
HCD_Plugin::store_api_response(new WP_HTTP_Response(400,['code'=>'payment_failed']),null,new WP_REST_Request('POST','/wc/store/v1/cart'));
if(count(HCD_Events::$records)!==1 || HCD_Events::$records[0] !== ['store_api_error','blocks','payment_http_402']) throw new RuntimeException('Store API event filtering / privacy failure');
echo "PASS: Store API error filtering and privacy\n";
