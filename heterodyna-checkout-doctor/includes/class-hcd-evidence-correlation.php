<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ZIP 15: Bounded, read-only synthesis of existing aggregated diagnostics.
 * No new HTTP calls, log reads, customer data, gateway callbacks or transactions.
 * Temporal overlap and common cause are NOT established by these counters.
 */
final class HCD_Evidence_Correlation {
    private static function count( $value ) {
        return is_numeric( $value ) ? min( 1000000, max( 0, (int) $value ) ) : 0;
    }
    private static function entry( $id, $status, $title, $evidence, $meaning, $recommendation ) {
        return array( 'id' => $id, 'category' => 'correlation', 'status' => $status,
            'confidence' => 'signal', 'title' => $title, 'evidence' => $evidence,
            'meaning' => $meaning, 'recommendation' => $recommendation );
    }

    /** Pure summary; only counters and fixed enums are accepted and emitted. */
    public static function summarize( $meta ) {
        $meta = is_array( $meta ) ? $meta : array();
        $raw_store = isset( $meta['store_api_observations'] ) && is_array( $meta['store_api_observations'] ) ? $meta['store_api_observations'] : array();
        $raw_logs = isset( $meta['technical_logs'] ) && is_array( $meta['technical_logs'] ) ? $meta['technical_logs'] : array();
        $raw_manual = isset( $meta['integration_tests'] ) && is_array( $meta['integration_tests'] ) ? $meta['integration_tests'] : array();
        $raw_summary = isset( $raw_manual['summary'] ) && is_array( $raw_manual['summary'] ) ? $raw_manual['summary'] : array();
        $raw_verification = isset( $raw_manual['verification_summary'] ) && is_array( $raw_manual['verification_summary'] ) ? $raw_manual['verification_summary'] : array();

        $store_enabled = isset( $raw_store['enabled'] ) && true === $raw_store['enabled'];
        $samples = $store_enabled && isset( $raw_store['count'] ) ? self::count( $raw_store['count'] ) : 0;
        $http4 = $store_enabled && isset( $raw_store['client_errors'] ) ? self::count( $raw_store['client_errors'] ) : 0;
        $http5 = $store_enabled && isset( $raw_store['server_errors'] ) ? self::count( $raw_store['server_errors'] ) : 0;
        // A legacy/stale aggregate alone must not synthesize observations.
        $http4 = min( $http4, $samples );
        $http5 = min( $http5, max( 0, $samples - $http4 ) );
        $files = isset( $raw_logs['files_inspected'] ) ? self::count( $raw_logs['files_inspected'] ) : 0;
        $log_critical = $files && isset( $raw_logs['critical'] ) ? self::count( $raw_logs['critical'] ) : 0;
        $log_errors = $files && isset( $raw_logs['error'] ) ? self::count( $raw_logs['error'] ) : 0;
        $tested = array_key_exists( 'total', $raw_manual ) ? self::count( $raw_manual['total'] ) : 0;
        $manual_failed = $tested && isset( $raw_summary['failed'] ) ? self::count( $raw_summary['failed'] ) : 0;
        $manual_current = $tested && isset( $raw_manual['current_manual_passed'] ) ? self::count( $raw_manual['current_manual_passed'] ) : 0;
        $reverify = 0;
        foreach ( array( 'expired', 'environment_changed', 'legacy_unverified' ) as $status ) {
            $reverify += isset( $raw_verification[ $status ] ) ? self::count( $raw_verification[ $status ] ) : 0;
        }
        $signal_sources = ( $samples ? 1 : 0 ) + ( ( $log_critical + $log_errors ) ? 1 : 0 ) + ( $manual_failed ? 1 : 0 );
        $level = $signal_sources >= 2 ? 'multiple_signal_sources' : ( 1 === $signal_sources ? 'single_signal_source' : 'not_enough_evidence' );
        return array(
            'scope' => 'independent_aggregates_not_time_correlated_or_causal',
            'level' => $level,
            'store_observer_enabled' => $store_enabled,
            'store_samples' => $samples,
            'store_http_4xx' => $http4,
            'store_http_5xx' => $http5,
            'local_log_files_read' => $files,
            'local_log_critical_lines' => $log_critical,
            'local_log_error_lines' => $log_errors,
            'manual_total' => $tested,
            'manual_failed' => $manual_failed,
            'manual_current_passed' => $manual_current,
            'manual_reverification_needed' => $reverify,
            'signal_source_count' => $signal_sources,
            'manual_steps' => array(
                'Powtórz konkretny objaw na izolowanym stagingu; testuj tylko bramki sandbox.',
                'Sprawdź godzinę UTC i kod HTTP w DevTools → Network (prywatnie, bez przesyłania treści żądań).',
                'Porównaj tę godzinę z oryginalnymi logami WooCommerce i PHP, filtrując konkretne źródło.',
                'Wykonaj i potwierdź ponownie właściwy scenariusz w Testach integracyjnych.',
            ),
        );
    }

    public static function run( &$meta ) {
        $data = self::summarize( $meta );
        $meta['evidence_correlation'] = $data;
        $checks = array();
        $checks[] = self::entry( 'correlation_evidence', 'info', 'Przekrój dowodów diagnostycznych',
            'Źródła sygnałów: ' . $data['signal_source_count'] . '/3; Store API: ' . $data['store_samples'] .
            '; linie błędów w próbie logów: ' . ( $data['local_log_critical_lines'] + $data['local_log_error_lines'] ) .
            '; ręcznie zgłoszone problemy: ' . $data['manual_failed'] . '.',
            'Źródła mają różne zakresy i okresy pomiarowe. Współwystępowanie nie dowodzi jednej przyczyny ani zgodności czasowej.',
            'Na stagingu porównaj konkretną operację, jej godzinę UTC oraz odpowiednie oryginalne logi. Nie publikuj logów klientów.' );
        if ( $data['store_samples'] && $data['local_log_files_read'] && ( $data['local_log_critical_lines'] || $data['local_log_error_lines'] ) ) {
            $checks[] = self::entry( 'correlation_store_logs', $data['store_http_5xx'] ? 'warning' : 'info',
                'Sygnały Store API i logów wymagają korelacji czasowej',
                'Store API: 4xx ' . $data['store_http_4xx'] . ', 5xx ' . $data['store_http_5xx'] .
                '; w próbie plików logów: krytyczne ' . $data['local_log_critical_lines'] .
                ', pozostałe błędy ' . $data['local_log_error_lines'] . '.',
                'Oba źródła zawierają sygnały, ale logi mogą dotyczyć innych dni lub procesów. Nie jest to potwierdzona awaria checkoutu ani bramki.',
                'Sprawdź oryginalne wpisy z tego samego momentu i źródła na stagingu; przejrzyj Network → Response bez eksportowania danych osobowych.' );
        } elseif ( ! $data['store_observer_enabled'] || ! $data['local_log_files_read'] ) {
            $checks[] = self::entry( 'correlation_coverage', 'info', 'Niepełne źródła obserwacji',
                'Obserwator Store API: ' . ( $data['store_observer_enabled'] ? 'włączony' : 'wyłączony/niedostępny' ) .
                '; odczytane pliki logów WooCommerce: ' . $data['local_log_files_read'] . '.',
                'Niedostępna telemetria lub logi w bazie nie oznaczają braku błędów.',
                'Na stagingu włącz świadomie obserwator i skontroluj WooCommerce → Status → Dzienniki.' );
        }
        if ( $data['manual_total'] ) {
            $checks[] = self::entry( 'correlation_manual_validation', $data['manual_failed'] ? 'warning' : 'info',
                'Wyniki ręcznych testów wymagają potwierdzenia',
                'Ręcznie zgłoszone problemy: ' . $data['manual_failed'] . '; aktualne pozytywne potwierdzenia: ' .
                $data['manual_current_passed'] . '; do rewalidacji: ' . $data['manual_reverification_needed'] . '.',
                'Wyniki checklisty pochodzą od testera. Nawet aktualne potwierdzenie nie weryfikuje automatycznie bramki, a wynik przestarzały nie potwierdza naprawy.',
                'Powtórz niezaliczone lub przeterminowane scenariusze w kontrolowanym koszyku na stagingu.' );
        }
        return $checks;
    }
}
