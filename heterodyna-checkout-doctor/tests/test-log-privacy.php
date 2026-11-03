<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
$test_root = sys_get_temp_dir() . '/hcd-check-logs-' . getmypid();
@mkdir( $test_root, 0777, true );
$secret = 'private-user@example.com-secret-token-123';
$log_name = 'gateway-2026-11-01-hiddenhash.log';
file_put_contents( $test_root . '/' . $log_name, "[time] PHP Fatal error: token=" . $secret . "\n[time] WARNING checkout message " . $secret . "\n[time] PHP Deprecated: " . $secret . "\n" );
// Never traverse symlinks in the log store.
@symlink( $test_root . '/' . $log_name, $test_root . '/symlink-abc.log' );
define( 'ABSPATH', '/tmp/' );
class HCD_Fake_LoggingUtil { public static $directory; public static function get_log_directory() { return self::$directory; } }
// Use same fully-qualified class WooCommerce uses.
class_alias( 'HCD_Fake_LoggingUtil', 'Automattic\\WooCommerce\\Utilities\\LoggingUtil' );
HCD_Fake_LoggingUtil::$directory = $test_root;
require dirname( __DIR__ ) . '/includes/class-hcd-conflicts-logs.php';
$meta = array(); $rows = HCD_Conflicts_Logs::run( $meta );
$stats = $meta['technical_logs'];
if ( 1 !== $stats['files_inspected'] || 1 !== $stats['critical'] || 1 !== $stats['error'] || 1 !== $stats['notice'] ) { throw new RuntimeException( 'Log classification or symlink exclusion failed: ' . json_encode( $stats ) ); }
if ( false !== strpos( json_encode( array( $meta, $rows ) ), $secret ) || false !== strpos( json_encode( array( $meta, $rows ) ), 'hiddenhash' ) ) { throw new RuntimeException( 'Log secret or source filename leaked' ); }
if ( ! preg_match( '/Sygnały/', json_encode( $rows, JSON_UNESCAPED_UNICODE ) ) ) { throw new RuntimeException( 'No diagnostic finding for log errors' ); }
@unlink( $test_root . '/symlink-abc.log' ); @unlink( $test_root . '/' . $log_name ); @rmdir( $test_root );
echo "PASS: bounded Woo log classification, symlink exclusion and secret redaction\n";
