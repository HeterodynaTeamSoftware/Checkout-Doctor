<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ZIP 16 — evidence-aware release gate. Read only; NO payments, requests, or orders.
 * A complete manual checklist is never presented as automated proof of compatibility.
 */
final class HCD_Release_Gate {
    public static function evaluate( $manual, $environment ) {
        $manual = is_array( $manual ) ? $manual : array();
        $environment = is_array( $environment ) ? $environment : array();
        $counts = isset( $manual['summary'] ) && is_array( $manual['summary'] ) ? $manual['summary'] : array();
        $verifications = isset( $manual['verification_summary'] ) && is_array( $manual['verification_summary'] ) ? $manual['verification_summary'] : array();
        $total = isset( $manual['total'] ) ? max( 0, min( 100, (int) $manual['total'] ) ) : 0;
        $passed = isset( $counts['passed'] ) ? max( 0, min( $total, (int) $counts['passed'] ) ) : 0;
        $failed = isset( $counts['failed'] ) ? max( 0, min( $total, (int) $counts['failed'] ) ) : 0;
        $blocked = isset( $counts['blocked'] ) ? max( 0, min( $total, (int) $counts['blocked'] ) ) : 0;
        $untested = isset( $counts['untested'] ) ? max( 0, min( $total, (int) $counts['untested'] ) ) : 0;
        $current = isset( $manual['current_manual_passed'] ) ? max( 0, min( $passed, (int) $manual['current_manual_passed'] ) ) : 0;
        $stale = 0;
        foreach ( array( 'environment_changed', 'expired', 'legacy_unverified' ) as $status ) {
            $stale += isset( $verifications[ $status ] ) ? max( 0, min( $total, (int) $verifications[ $status ] ) ) : 0;
        }
        $stale = min( $total, $stale );
        $woo = ! empty( $environment['woo_active'] );
        $wp = isset( $environment['wordpress'] ) && is_string( $environment['wordpress'] ) ? $environment['wordpress'] : '';
        $php = isset( $environment['php'] ) && is_string( $environment['php'] ) ? $environment['php'] : '';
        $min_wp = class_exists( 'HCD_Compatibility' ) ? HCD_Compatibility::MIN_WP : '6.2';
        $min_php = class_exists( 'HCD_Compatibility' ) ? HCD_Compatibility::MIN_PHP : '7.4';
        $wp_state = class_exists( 'HCD_Compatibility' ) ? HCD_Compatibility::classify_version( $wp, $min_wp ) : 'unknown';
        $php_state = class_exists( 'HCD_Compatibility' ) ? HCD_Compatibility::classify_version( $php, $min_php ) : 'unknown';
        $reasons = array();
        if ( ! $woo ) { $reasons[] = 'WooCommerce jest nieaktywne'; }
        if ( 'below' === $wp_state ) { $reasons[] = 'WordPress poniżej minimalnej wersji'; }
        if ( 'below' === $php_state ) { $reasons[] = 'PHP poniżej minimalnej wersji'; }
        if ( $failed ) { $reasons[] = 'Testy ręczne oznaczone jako niezaliczone: ' . $failed; }
        $hard_block = count( $reasons ) > 0;
        if ( 'unknown' === $wp_state || 'unknown' === $php_state ) { $reasons[] = 'Niepotwierdzona wersja środowiska'; }
        if ( 7 !== $total ) { $reasons[] = 'Brak pełnych 7 scenariuszy w protokole testów'; }
        if ( $untested || $blocked ) { $reasons[] = 'Scenariusze niewykonane lub zablokowane: ' . ( $untested + $blocked ); }
        if ( $stale ) { $reasons[] = 'Potwierdzenia wymagające rewalidacji: ' . $stale; }
        if ( $current < 7 ) { $reasons[] = 'Aktualnie potwierdzone scenariusze: ' . $current . '/7'; }
        $state = $hard_block ? 'blocked' : ( 7 === $total && 7 === $current && 0 === $stale && 'supported' === $wp_state && 'supported' === $php_state ? 'manual_complete_pending_review' : 'requires_tests' );
        return array(
            'state' => $state,
            'scope' => 'manual_checklist_and_local_versions_not_certification',
            'manual_total' => $total,
            'manual_current_passed' => $current,
            'manual_failed' => $failed,
            'manual_blocked' => $blocked,
            'manual_untested' => $untested,
            'manual_stale' => $stale,
            'missing_or_unverified' => max( 0, 7 - $current ),
            'reasons' => array_slice( array_values( array_unique( $reasons ) ), 0, 8 ),
            'public_release_approved_automatically' => false,
        );
    }

    public static function run( &$meta ) {
        $manual = isset( $meta['integration_tests'] ) && is_array( $meta['integration_tests'] )
            ? $meta['integration_tests'] : ( class_exists( 'HCD_Integration_Tests' ) ? HCD_Integration_Tests::snapshot() : array() );
        $environment = array(
            'woo_active' => class_exists( 'WooCommerce' ),
            'wordpress' => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : '',
            'php' => PHP_VERSION,
        );
        $gate = self::evaluate( $manual, $environment );
        $meta['release_gate'] = $gate;
        $labels = array(
            'blocked' => 'Publikacja wstrzymana — wykryto przeszkody',
            'requires_tests' => 'Publikacja wymaga dalszych testów',
            'manual_complete_pending_review' => 'Checklista ręczna kompletna — wymagana kontrola końcowa',
        );
        return array( array(
            'id' => 'release_gate_review',
            'category' => 'compatibility',
            'status' => 'blocked' === $gate['state'] ? 'warning' : 'info',
            'confidence' => 'signal',
            'title' => $labels[ $gate['state'] ],
            'evidence' => 'Aktualne potwierdzenia: ' . $gate['manual_current_passed'] . '/7; niezaliczone: ' . $gate['manual_failed'] .
                '; do rewalidacji: ' . $gate['manual_stale'] . '.',
            'meaning' => 'To kontrola wersji i deklaracji testera. Nigdy nie stanowi automatycznego certyfikatu sprawności sklepu.',
            'recommendation' => 'Wykonaj scenariusze na stagingu, przetestuj płatności w sandboxie, sprawdź raport i przeprowadź przegląd przed publikacją.',
        ) );
    }
}
