<?php
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);
$admin=file_get_contents($root.'/includes/class-hcd-admin.php');
$js=file_get_contents($root.'/assets/js/admin.js');
$frontend=file_get_contents($root.'/assets/js/checkout-observer.js');
$scanner=file_get_contents($root.'/includes/class-hcd-scanner.php');
$plugin=file_get_contents($root.'/includes/class-hcd-plugin.php');
$entry=file_get_contents($root.'/heterodyna-checkout-doctor.php');
foreach(array('data-hcd-tab="browser"','data-hcd-page="browser"','hcd-run-browser','hcd-browser-samples','hcd_browser_observer_enabled','hcd_clear_browser','hcd-browser-findings') as $needle){
  if(strpos($admin,$needle)===false)throw new RuntimeException('Admin UI missing '.$needle);
}
foreach(array('function updateBrowser()',"runScan('browser')",'updateBrowser();',"browser:'Przeglądarka JS'") as $needle){
  if(strpos($js,$needle)===false)throw new RuntimeException('Admin JS missing '.$needle);
}
foreach(array("push('render'", "push('js_error'", "push('promise_rejection'", 'same-origin', 'new PerformanceObserver', 'JSON.stringify(batch)') as $needle){
  if(strpos($frontend,$needle)===false)throw new RuntimeException('Frontend JS missing '.$needle);
}
if(strpos($frontend,'event.message')!==false||strpos($frontend,'event.reason')!==false||strpos($frontend,'event.stack')!==false)throw new RuntimeException('Raw JS exception captured');
if(strpos($scanner,'HCD_Browser_Probe::run')===false||strpos($plugin,'HCD_Browser_Probe::init')===false||strpos($entry,'class-hcd-browser-probe.php')===false||strpos($entry,'Version: 1.16.0')===false)throw new RuntimeException('Incomplete loader');
echo "PASS: browser tab/scan/JS and loader wiring; no raw error message access\n";
