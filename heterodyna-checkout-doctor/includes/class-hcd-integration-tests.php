<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ZIP 12 — manual integration-test ledger.
 * Only fixed scenario IDs, fixed statuses and UTC timestamps are persisted.
 * No notes, payment data, personal details or checkout input is collected.
 * Entries are explicit human attestations, NOT automated integration results.
 */
final class HCD_Integration_Tests {
    const OPTION = 'hcd_integration_tests';

    public static function scenarios() {
        return array(
            'classic' => array( 'title' => 'Checkout klasyczny', 'instruction' => 'Na stagingu przejdź koszyk i checkout z testowym produktem. Sprawdź pola i komunikaty bez finalizacji rzeczywistej płatności.' ),
            'blocks' => array( 'title' => 'Checkout Block', 'instruction' => 'Sprawdź renderowanie bloku, wymagane pola i odpowiedzi Store API na stagingu.' ),
            'gateway' => array( 'title' => 'Bramka w sandboxie', 'instruction' => 'Przy użyciu konta testowego operatora zweryfikuj przejście testowej płatności i jej status. Nie używaj prawdziwych danych karty.' ),
            'shipping' => array( 'title' => 'Dostawa i odbiór', 'instruction' => 'Sprawdź testowe adresy, strefy, stawki i dostępne metody dostawy bez zamawiania na produkcji.' ),
            'hpos' => array( 'title' => 'HPOS / status zamówienia', 'instruction' => 'Na stagingu sprawdź zapis i odczyt zamówienia testowego oraz zgodność z HPOS, jeżeli jest włączony.' ),
            'theme' => array( 'title' => 'Motyw i rozszerzenia', 'instruction' => 'Porównaj checkout w aktywnym motywie i na czystej kopii testowej; kontroluj błędy JS.' ),
            'upgrade' => array( 'title' => 'Aktualizacja i regresja', 'instruction' => 'Wykonaj kopię, zaktualizuj wtyczkę na stagingu i porównaj wyniki dwóch skanów.' ),
        );
    }

    const VALID_DAYS = 30;

    public static function allowed_statuses() { return array( 'untested', 'passed', 'failed', 'blocked' ); }

    /** Stable, private fingerprint of the configuration relevant to manual staging tests.
     * No site addresses, order data, secret options or customers are read/stored.
     * Version information for active plugins is included when WordPress can provide it.
     */
    public static function environment_fingerprint( $override = null ) {
        if ( is_array( $override ) ) {
            $parts = $override;
        } else {
            global $wp_version;
            $active = function_exists( 'get_option' ) ? get_option( 'active_plugins', array() ) : array();
            $active = is_array( $active ) ? array_values( array_filter( $active, 'is_string' ) ) : array();
            if ( function_exists( 'get_site_option' ) ) {
                $network = get_site_option( 'active_sitewide_plugins', array() );
                if ( is_array( $network ) ) { $active = array_merge( $active, array_keys( $network ) ); }
            }
            $active = array_values( array_unique( array_slice( $active, 0, 500 ) ) );
            sort( $active, SORT_STRING );
            // The plugin inventory is used only locally and hashed, never exported as a list.
            if ( ! function_exists( 'get_plugins' ) && defined( 'ABSPATH' ) ) {
                $plugin_file = ABSPATH . 'wp-admin/includes/plugin.php';
                if ( is_readable( $plugin_file ) ) { require_once $plugin_file; }
            }
            $available = function_exists( 'get_plugins' ) ? get_plugins() : array();
            $plugins = array();
            foreach ( $active as $id ) {
                $plugins[] = array( $id, isset( $available[ $id ]['Version'] ) ? (string) $available[ $id ]['Version'] : 'unknown' );
            }
            $theme = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
            $parts = array(
                'wordpress' => isset( $wp_version ) ? (string) $wp_version : 'unknown',
                'woocommerce' => defined( 'WC_VERSION' ) ? (string) WC_VERSION : 'unknown',
                'php' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
                'theme' => function_exists( 'get_stylesheet' ) ? (string) get_stylesheet() : 'unknown',
                'theme_version' => is_object( $theme ) && method_exists( $theme, 'get' ) ? (string) $theme->get( 'Version' ) : 'unknown',
                'active_plugin_versions' => $plugins,
            );
        }
        // Sort root keys, so the injected test environment is also deterministic.
        ksort( $parts, SORT_STRING );
        return hash( 'sha256', json_encode( $parts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
    }

    public static function normalize( $raw ) {
        if ( ! is_array( $raw ) ) { $raw = array(); }
        $result = array();
        foreach ( self::scenarios() as $id => $definition ) {
            $entry = isset( $raw[ $id ] ) && is_array( $raw[ $id ] ) ? $raw[ $id ] : array();
            $status = isset( $entry['status'] ) && is_string( $entry['status'] ) ? $entry['status'] : 'untested';
            if ( ! in_array( $status, self::allowed_statuses(), true ) ) { $status = 'untested'; }
            $time = isset( $entry['updated_utc'] ) && is_string( $entry['updated_utc'] )
                && preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/D', $entry['updated_utc'] ) ? $entry['updated_utc'] : '';
            $fingerprint = isset( $entry['environment_hash'] ) && is_string( $entry['environment_hash'] )
                && preg_match( '/^[a-f0-9]{64}$/D', $entry['environment_hash'] ) ? $entry['environment_hash'] : '';
            if ( 'untested' === $status ) { $time = ''; $fingerprint = ''; }
            $result[ $id ] = array( 'status' => $status, 'updated_utc' => $time, 'environment_hash' => $fingerprint );
        }
        return $result;
    }

    /** Saving the form does NOT renew an unchanged claim.
     * Only a changed status or an explicit, checked reconfirmation stamps evidence.
     */
    public static function store( $submitted, $existing = null, $now = null, $reconfirm = array(), $environment_hash = null ) {
        $current = self::normalize( null === $existing ? get_option( self::OPTION, array() ) : $existing );
        $next = $current;
        $submitted = is_array( $submitted ) ? $submitted : array();
        $reconfirm = is_array( $reconfirm ) ? $reconfirm : array();
        $at = is_string( $now ) && preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/D', $now ) ? $now : gmdate( 'c' );
        $fingerprint = is_string( $environment_hash ) && preg_match( '/^[a-f0-9]{64}$/D', $environment_hash )
            ? $environment_hash : self::environment_fingerprint();
        foreach ( self::scenarios() as $id => $definition ) {
            $status = isset( $submitted[ $id ] ) && is_string( $submitted[ $id ] ) ? $submitted[ $id ] : $current[ $id ]['status'];
            if ( ! in_array( $status, self::allowed_statuses(), true ) ) { $status = 'untested'; }
            $changed = $status !== $current[ $id ]['status'];
            $confirmed = isset( $reconfirm[ $id ] ) && '1' === (string) $reconfirm[ $id ];
            if ( $changed || ( $confirmed && 'untested' !== $status ) ) {
                $next[ $id ] = array(
                    'status' => $status,
                    'updated_utc' => 'untested' === $status ? '' : $at,
                    'environment_hash' => 'untested' === $status ? '' : $fingerprint,
                );
            }
        }
        return $next;
    }

    public static function verification( $entry, $hash, $now_ts ) {
        if ( 'untested' === $entry['status'] ) { return 'not_tested'; }
        if ( empty( $entry['environment_hash'] ) || empty( $entry['updated_utc'] ) ) { return 'legacy_unverified'; }
        if ( ! hash_equals( $hash, $entry['environment_hash'] ) ) { return 'environment_changed'; }
        $when = strtotime( $entry['updated_utc'] );
        if ( false === $when || $when > $now_ts + 3600 || ( $now_ts - $when ) > self::VALID_DAYS * 86400 ) {
            return 'expired';
        }
        return 'current_manual';
    }

    public static function snapshot( $data = null, $environment_hash = null, $now = null ) {
        $entries = self::normalize( null === $data ? get_option( self::OPTION, array() ) : $data );
        $hash = is_string( $environment_hash ) && preg_match( '/^[a-f0-9]{64}$/D', $environment_hash )
            ? $environment_hash : self::environment_fingerprint();
        $now_ts = is_numeric( $now ) ? (int) $now : time();
        $summary = array( 'untested' => 0, 'passed' => 0, 'failed' => 0, 'blocked' => 0 );
        $verification = array( 'current_manual' => 0, 'environment_changed' => 0, 'expired' => 0, 'legacy_unverified' => 0, 'not_tested' => 0 );
        $current_passed = 0;
        foreach ( $entries as $id => $entry ) {
            $summary[ $entry['status'] ]++;
            $state = self::verification( $entry, $hash, $now_ts );
            $entries[ $id ]['verification'] = $state;
            $verification[ $state ]++;
            if ( 'passed' === $entry['status'] && 'current_manual' === $state ) { $current_passed++; }
        }
        return array(
            'scope' => 'manual_staging_attestation_not_automated_validation',
            'total' => count( $entries ),
            'summary' => $summary,
            'verification_summary' => $verification,
            'current_manual_passed' => $current_passed,
            'manual_confirmation_valid_days' => self::VALID_DAYS,
            'entries' => $entries,
        );
    }

    public static function run( &$meta ) {
        $snapshot = self::snapshot();
        $meta['integration_tests'] = $snapshot;
        $s = $snapshot['summary'];
        $v = $snapshot['verification_summary'];
        $pending = $s['untested'] + $s['blocked'];
        $recheck = $v['environment_changed'] + $v['expired'] + $v['legacy_unverified'];
        $status = $s['failed'] ? 'warning' : 'info';
        $text = 'Ręczne: zaliczone ' . $s['passed'] . '/' . $snapshot['total']
            . ' (aktualne potwierdzenia: ' . $snapshot['current_manual_passed'] . ')'
            . ', błędy ' . $s['failed'] . ', zablokowane ' . $s['blocked']
            . ', niewykonane ' . $s['untested'] . ', do ponownej weryfikacji ' . $recheck . '.';
        return array( array(
            'id' => 'integration_manual_coverage',
            'category' => 'integration',
            'status' => $status,
            'confidence' => 'signal',
            'title' => 'Ręczna kontrola integracyjna — aktualność wyników testera',
            'evidence' => $text,
            'meaning' => 'Wyniki deklaruje tester. Nie są automatycznym testem sklepu. Potwierdzenia wygasają po 30 dniach albo zmianie rozpoznanego środowiska.',
            'recommendation' => $s['failed'] ? 'Odtwórz zgłoszone problemy na stagingu, przeanalizuj logi i ponów test.'
                : ( $recheck ? 'Ponów nieaktualne testy, następnie zaznacz osobno Potwierdź ponownie.'
                    : ( $pending ? 'Dokończ brakujące scenariusze na stagingu przed publikacją.'
                        : 'Zachowaj dowody testów stagingowych przed publikacją.' ) ),
        ) );
    }
}
