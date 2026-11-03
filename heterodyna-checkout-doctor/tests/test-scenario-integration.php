<?php
if(PHP_SAPI !== 'cli')exit(1);
define('ABSPATH','/tmp/');
function get_option($name,$default=null) { if($name==='woocommerce_enable_coupons')return 'no'; return $default; }
class FakeScenarioGateway {
    public $id = 'cod'; public $enabled = 'yes';
    public function get_option($key,$default=null) {
        if ($key==='enable_for_virtual') return 'no';
        if ($key==='enable_for_methods') return array('local_pickup');
        return $default;
    }
}
class FakeScenarioManager { public function payment_gateways() { return array(new FakeScenarioGateway(),(object)array('id'=>'other_provider','enabled'=>'yes')); } }
class FakeScenarioWC { public function payment_gateways() { return new FakeScenarioManager(); } }
function WC() { return new FakeScenarioWC(); }
class WC_Shipping_Zones { public static function get_zones() { return array(array('zone_id'=>2)); } }
class FakeShippingMethod {
    public $id='free_shipping'; public $instance_id=7;
    public function get_option($key,$default='') { if($key==='requires')return 'both'; if($key==='min_amount')return '100'; return $default; }
}
class WC_Shipping_Zone {
    private $id;
    public function __construct($id){$this->id=$id;}
    public function get_shipping_methods($enabled=true){ return $this->id===2 ? array(new FakeShippingMethod()) : array(); }
}
require dirname(__DIR__).'/includes/class-hcd-scenarios.php';
$meta=array('checkout_mode'=>'blocks');
$checks=HCD_Scenarios::run($meta);
if (count($meta['scenario_audit']['scenarios'])!==3 || count($meta['scenario_audit']['gateways'])!==2 || count($meta['scenario_audit']['shipping_rules'])!==1)throw new RuntimeException('Inventories wrong');
if (count(array_filter($checks,function($r){return $r['status']==='warning';}))!==1)throw new RuntimeException('Expected coupon warning');
$json=json_encode(array($checks,$meta));
if (strpos($json,'PRIVATE')!==false || strpos($json,'customer_id')!==false || strpos($json,'billing_address')!==false)throw new RuntimeException('Leak');
$entry=file_get_contents(dirname(__DIR__).'/heterodyna-checkout-doctor.php');
$admin=file_get_contents(dirname(__DIR__).'/includes/class-hcd-admin.php');
$js=file_get_contents(dirname(__DIR__).'/assets/js/admin.js');
$scanner=file_get_contents(dirname(__DIR__).'/includes/class-hcd-scanner.php');
foreach(array('Version: 1.16.0','class-hcd-scenarios.php') as $key)if(strpos($entry,$key)===false)throw new RuntimeException('Loader: '.$key);
foreach(array('data-hcd-tab="scenarios"','data-hcd-page="scenarios"','hcd-run-scenarios','hcd-scenario-rows') as $key)if(strpos($admin,$key)===false)throw new RuntimeException('Admin UI: '.$key);
foreach(array('function updateScenarios()',"runScan('scenarios')",'updateScenarios();') as $key)if(strpos($js,$key)===false)throw new RuntimeException('JS UI: '.$key);
if(strpos($scanner,'HCD_Scenarios::run')===false)throw new RuntimeException('Scanner missing');
echo "PASS: scenario audit integration, UI, PHP runtime and no personal data export\n";
