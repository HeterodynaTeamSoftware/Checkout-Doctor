<?php
/**
 * Plugin Name: Heterodyna Checkout Doctor
 * Plugin URI: https://heterodynateamsoftware.pl/
 * Description: Diagnostyka checkout WooCommerce, bezpieczny monitoring błędów, dowody techniczne i raporty bez wykonywania transakcji.
 * Version: 1.16.0
 * Author: Heterodyna Team Software
 * Author URI: https://heterodynateamsoftware.pl/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: heterodyna-checkout-doctor
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'HCD_VERSION', '1.16.0' );
define( 'HCD_FILE', __FILE__ );
define( 'HCD_DIR', plugin_dir_path( __FILE__ ) );
define( 'HCD_URL', plugin_dir_url( __FILE__ ) );

require_once HCD_DIR . 'includes/class-hcd-events.php';
require_once HCD_DIR . 'includes/class-hcd-checkout-core.php';
require_once HCD_DIR . 'includes/class-hcd-payments-shipping.php';
require_once HCD_DIR . 'includes/class-hcd-conflicts-logs.php';
require_once HCD_DIR . 'includes/class-hcd-repair-reports.php';
require_once HCD_DIR . 'includes/class-hcd-readiness.php';
require_once HCD_DIR . 'includes/class-hcd-browser-probe.php';
require_once HCD_DIR . 'includes/class-hcd-block-compat.php';
require_once HCD_DIR . 'includes/class-hcd-scenarios.php';
require_once HCD_DIR . 'includes/class-hcd-payment-visibility.php';
require_once HCD_DIR . 'includes/class-hcd-store-api-observer.php';
require_once HCD_DIR . 'includes/class-hcd-compatibility.php';
require_once HCD_DIR . 'includes/class-hcd-integration-tests.php';
require_once HCD_DIR . 'includes/class-hcd-evidence-correlation.php';
require_once HCD_DIR . 'includes/class-hcd-release-gate.php';
require_once HCD_DIR . 'includes/class-hcd-release-snapshot.php';
require_once HCD_DIR . 'includes/class-hcd-scan-lock.php';
require_once HCD_DIR . 'includes/class-hcd-scanner.php';
require_once HCD_DIR . 'includes/class-hcd-admin.php';
require_once HCD_DIR . 'includes/class-hcd-plugin.php';

add_action( 'before_woocommerce_init', array( 'HCD_Plugin', 'hpos_compat' ) );
register_activation_hook( __FILE__, array( 'HCD_Events', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'HCD_Events', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'HCD_Plugin', 'init' ) );
