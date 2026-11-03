<?php
if ( PHP_SAPI !== 'cli' ) { exit( 1 ); }
define( 'ABSPATH', '/tmp/' );
/** Strict in-memory simulation of MySQL's INSERT IGNORE and conditional DELETE. */
class Test_Lock_DB {
    public $options = 'wp_options';
    public $stored = array();
    public function prepare( $sql, ...$args ) {
        foreach ( $args as $arg ) {
            $sql = preg_replace( '/%s/', "'" . str_replace( "'", "''", $arg ) . "'", $sql, 1 );
        }
        return $sql;
    }
    private function key( $sql ) {
        if ( ! preg_match( "/option_name`\s*(?:=\s*)?'([^']+)'/", $sql, $m ) ) { return null; }
        return $m[1];
    }
    public function query( $sql ) {
        if ( false !== strpos( $sql, 'INSERT IGNORE' ) ) {
            if ( ! preg_match( "/VALUES \('([^']+)','([^']+)','no'\)/", $sql, $m ) ) { throw new RuntimeException('Insert query invalid: ' . $sql); }
            if ( isset( $this->stored[$m[1]] ) ) { return 0; }
            $this->stored[$m[1]] = $m[2]; return 1;
        }
        if ( false !== strpos( $sql, 'DELETE FROM' ) ) {
            if ( ! preg_match( "/option_name` = '([^']+)' AND `option_value` = '([^']+)'/", $sql, $m ) ) { throw new RuntimeException('Delete query invalid'); }
            if ( !isset( $this->stored[$m[1]] ) || $this->stored[$m[1]] !== $m[2] ) { return 0; }
            unset( $this->stored[$m[1]] ); return 1;
        }
        throw new RuntimeException('Unknown query');
    }
    public function get_var( $sql ) {
        if ( ! preg_match( "/option_name` = '([^']+)'/", $sql, $m ) ) { throw new RuntimeException('Select query invalid'); }
        return isset( $this->stored[$m[1]] ) ? $this->stored[$m[1]] : null;
    }
}
$wpdb = new Test_Lock_DB();
require dirname( __DIR__ ) . '/includes/class-hcd-scan-lock.php';
function check( $ok, $msg ) { if ( ! $ok ) { throw new RuntimeException( $msg ); } }
$a = HCD_Scan_Lock::acquire( 7, 1000 );
check( is_string($a) && preg_match('/^[0-9]+:[a-f0-9]{32}$/D', $a), 'First lock token' );
check( false === HCD_Scan_Lock::acquire( 7, 1001 ), 'Same user cannot overlap' );
$b = HCD_Scan_Lock::acquire( 8, 1001 );
check( is_string($b), 'Separate user may scan' );
check( false === HCD_Scan_Lock::release( 7, $b ), 'Other token cannot release' );
check( true === HCD_Scan_Lock::release( 7, $a ), 'Lock owner releases' );
check( false === HCD_Scan_Lock::release( 7, $a ), 'Old token cannot release twice' );
$c = HCD_Scan_Lock::acquire( 7, 1002 );
check( is_string($c) && $c !== $a, 'Fresh acquisition' );
$d = HCD_Scan_Lock::acquire( 7, 1002 + HCD_Scan_Lock::TTL + 1 );
check( is_string($d) && $d !== $c, 'Expired lock recovered' );
check( false === HCD_Scan_Lock::release( 7, $c ), 'Old worker cannot unlock replacement' );
check( true === HCD_Scan_Lock::release( 7, $d ), 'New worker releases replacement' );
check( false === HCD_Scan_Lock::release( 7, 'oops' ), 'Invalid token rejected' );
$wpdb->stored[HCD_Scan_Lock::option_name(9)] = 'bad-legacy-record';
$recovered = HCD_Scan_Lock::acquire(9, 2000);
check( is_string($recovered), 'Malformed stale record recovered safely');
echo "PASS: SQL insert-ignore mutex, owner token, stale conditional delete and per-user isolation\n";
