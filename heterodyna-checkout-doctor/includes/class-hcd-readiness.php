<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Local checkout readiness checks and comparison of two successive read-only scans.
 * Only stable check identifiers and statuses are compared; no customer/order details.
 */
final class HCD_Readiness {
    private static function finding( &$checks, $id, $status, $title, $evidence, $meaning, $recommendation, $confidence = 'confirmed' ) {
        $checks[] = array(
            'id' => $id, 'category' => 'readiness', 'status' => $status,
            'title' => $title, 'evidence' => $evidence, 'meaning' => $meaning,
            'recommendation' => $recommendation, 'confidence' => $confidence,
        );
    }

    /** Does not make HTTP calls or write WooCommerce data. */
    public static function run( &$meta ) {
        $checks = array();
        $db = get_option( 'woocommerce_db_version', '' );
        $code = defined( 'WC_VERSION' ) ? (string) WC_VERSION : '';
        $meta['readiness'] = array('woocommerce_db_version' => is_scalar( $db ) ? (string) $db : '');
        if ( is_string( $db ) && '' !== $db && '' !== $code && preg_match( '/^\d+(?:\.\d+){1,3}$/', $db ) && preg_match( '/^\d+(?:\.\d+){1,3}$/', $code ) ) {
            $relation = version_compare( $db, $code );
            if ( $relation < 0 ) {
                self::finding( $checks, 'readiness_db_version', 'warning', 'Wersja bazy WooCommerce jest starsza od kodu',
                    'Baza: ' . $db . ' · kod: ' . $code . '.',
                    'Rozbieżność wersji może oznaczać oczekującą aktualizację struktury danych, ale sama nie dowodzi awarii checkoutu.',
                    'Sprawdź WooCommerce → Status → Narzędzia oraz zakończenie aktualizacji bazy. Wykonaj kopię zapasową przed zmianą.', 'signal' );
            } elseif ( $relation > 0 ) {
                self::finding( $checks, 'readiness_db_version', 'warning', 'Wersja bazy nowsza niż kod WooCommerce',
                    'Baza: ' . $db . ' · kod: ' . $code . '.',
                    'Może wskazywać na przywrócenie starszej wtyczki bez przywrócenia zgodnej bazy.',
                    'Zweryfikuj historię aktualizacji i wersje w środowisku testowym. Nie wykonuj ręcznego downgrade bazy.', 'signal' );
            } else {
                self::finding( $checks, 'readiness_db_version', 'pass', 'Wersja bazy i kodu WooCommerce zgodna',
                    'Baza i kod: ' . $code . '.', 'Porównywane numery wersji są zgodne. Nie jest to test integralności bazy.', 'Brak działania.' );
            }
        } else {
            self::finding( $checks, 'readiness_db_version', 'info', 'Brak porównywalnych wersji bazy WooCommerce',
                'Nie udało się porównać numerów wersji bazy i kodu.',
                'Nie można ocenić stanu migracji jedynie na podstawie metadanych.',
                'Sprawdź WooCommerce → Status. Nie modyfikuj bazy na podstawie samego skanu.', 'signal' );
        }

        // WooCommerce default endpoint values are used only when the option is absent.
        $pay = get_option( 'woocommerce_checkout_pay_endpoint', 'order-pay' );
        $received = get_option( 'woocommerce_checkout_order_received_endpoint', 'order-received' );
        $pay = is_string( $pay ) ? trim( $pay ) : '';
        $received = is_string( $received ) ? trim( $received ) : '';
        $meta['readiness']['checkout_endpoints'] = array( 'order_pay' => $pay, 'order_received' => $received );
        if ( '' !== $pay && '' !== $received && strtolower( $pay ) === strtolower( $received ) ) {
            self::finding( $checks, 'readiness_endpoint_collision', 'warning', 'Endpointy płatności i potwierdzenia mają tę samą nazwę',
                'Obie opcje WooCommerce mają wartość: ' . $pay . '.',
                'Kolizja nazw endpointów może powodować niejednoznaczny routing po zamówieniu.',
                'Ustaw odrębne nazwy w WooCommerce → Ustawienia → Zaawansowane → Endpointy i przetestuj odnośniki na stagingu.' );
        } elseif ( '' === $pay || '' === $received ) {
            self::finding( $checks, 'readiness_endpoint_collision', 'info', 'Jeden z endpointów checkoutu jest pusty',
                'Order pay: ' . ( '' === $pay ? 'pusty' : 'ustawiony' ) . '; order received: ' . ( '' === $received ? 'pusty' : 'ustawiony' ) . '.',
                'Puste opcje mogą wynikać z nietypowej konfiguracji, nie są dowodem awarii.',
                'Zweryfikuj odnośniki „zapłać” i „zamówienie otrzymane” na stagingu.', 'signal' );
        } else {
            self::finding( $checks, 'readiness_endpoint_collision', 'pass', 'Endpointy zamówienia mają odrębne nazwy',
                'Order pay: ' . $pay . ' · order received: ' . $received . '.',
                'Nie znaleziono kolizji między tymi dwiema opcjami. Nie sprawdzano serwera HTTP ani reguł przepisywania adresów.',
                'Brak działania.' );
        }

        $disabled = defined( 'DISABLE_WP_CRON' ) && (bool) DISABLE_WP_CRON;
        $as_present = function_exists( 'as_next_scheduled_action' ) || class_exists( 'ActionScheduler' );
        $meta['readiness']['wp_cron_disabled'] = $disabled;
        $meta['readiness']['action_scheduler_detected'] = $as_present;
        if ( $disabled ) {
            self::finding( $checks, 'readiness_cron', 'info', 'WP-Cron jest wyłączony w konfiguracji',
                'DISABLE_WP_CRON = true; Action Scheduler: ' . ( $as_present ? 'wykryty' : 'niewykryty' ) . '.',
                'Może to być prawidłowa konfiguracja zewnętrznego crona. Skan nie ma możliwości potwierdzenia harmonogramu na serwerze.',
                'Sprawdź zadania systemowego crona, WooCommerce → Status → Zaplanowane działania i zaległe zadania.', 'signal' );
        } else {
            self::finding( $checks, 'readiness_cron', 'info', 'WP-Cron nie jest jawnie wyłączony',
                'Nie wykryto stałej DISABLE_WP_CRON = true.',
                'To nie potwierdza, że zaplanowane zadania uruchamiają się poprawnie.',
                'W WooCommerce → Status sprawdź zaległe zaplanowane działania.', 'signal' );
        }

        $debug_visible = defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY;
        $meta['readiness']['debug_display_enabled'] = (bool) $debug_visible;
        if ( $debug_visible ) {
            self::finding( $checks, 'readiness_debug_display', 'warning', 'Debug PHP może wyświetlać informacje klientom',
                'WP_DEBUG i WP_DEBUG_DISPLAY są włączone.',
                'Komunikaty diagnostyczne mogą pojawiać się na stronach checkoutu. To ocena konfiguracji, bez testu wyświetlania na froncie.',
                'W produkcji ustaw WP_DEBUG_DISPLAY na false; diagnostykę zapisuj w bezpiecznym dzienniku.', 'signal' );
        } else {
            self::finding( $checks, 'readiness_debug_display', 'pass', 'Nie wykryto jednoczesnego włączenia debugowania i wyświetlania',
                'Warunek WP_DEBUG && WP_DEBUG_DISPLAY nie jest spełniony.',
                'Nie oceniono zachowania serwera, motywu i zewnętrznych modułów.', 'Brak działania.' );
        }
        return $checks;
    }

    private static function map( $report ) {
        $out = array();
        if ( ! is_array( $report ) || empty( $report['checks'] ) || ! is_array( $report['checks'] ) ) { return $out; }
        foreach ( $report['checks'] as $item ) {
            if ( ! is_array( $item ) || ! isset( $item['id'], $item['status'] ) ) { continue; }
            $id = (string) $item['id'];
            $category = isset( $item['category'] ) && is_string( $item['category'] ) ? $item['category'] : 'core';
            $status = (string) $item['status'];
            if ( ! preg_match( '/^[a-z0-9_-]{1,100}$/i', $id ) || ! preg_match( '/^[a-z0-9_-]{1,50}$/i', $category ) || ! in_array( $status, array('critical','warning','pass','info'), true ) ) { continue; }
            $key = $category . ':' . $id;
            // Duplicate keys are ambiguous; mark as absent rather than arbitrarily choosing one.
            if ( array_key_exists( $key, $out ) ) { $out[$key] = null; continue; }
            $out[$key] = array(
                'id' => $id, 'category' => $category, 'status' => $status,
                'title' => isset( $item['title'] ) ? (string) $item['title'] : $id,
                'confidence' => isset( $item['confidence'] ) && 'confirmed' === $item['confidence'] ? 'confirmed' : 'signal',
            );
        }
        return array_filter( $out, 'is_array' );
    }

    public static function compare( $current, $previous ) {
        $result = array(
            'available' => false,
            'previous_at' => null,
            'current_at' => isset( $current['generated_at'] ) ? (string) $current['generated_at'] : null,
            'counts' => array( 'new' => 0, 'new_check' => 0, 'cleared' => 0, 'escalated' => 0, 'changed' => 0, 'unavailable' => 0 ),
            'changes' => array(),
            'note' => 'Porównanie dotyczy wyników skanera, nie jest testem prawdziwej płatności ani dowodem usunięcia awarii.',
        );
        if ( ! is_array( $previous ) || empty( $previous['generated_at'] ) || ! isset( $previous['checks'] ) || ! is_array( $previous['checks'] ) ) { return $result; }
        $result['available'] = true;
        $result['previous_at'] = (string) $previous['generated_at'];
        $old = self::map( $previous );
        $new = self::map( $current );
        $all = array_unique( array_merge( array_keys( $old ), array_keys( $new ) ) );
        sort( $all, SORT_STRING );
        $unresolved = array( 'critical' => true, 'warning' => true );
        $ranking = array( 'pass' => 0, 'info' => 1, 'warning' => 2, 'critical' => 3 );
        foreach ( $all as $key ) {
            $before = isset( $old[$key] ) ? $old[$key] : null;
            $after = isset( $new[$key] ) ? $new[$key] : null;
            if ( $before && $after && $before['status'] === $after['status'] && $before['confidence'] === $after['confidence'] ) { continue; }
            if ( ! $before || ! $after ) {
                $type = 'unavailable';
                // New check on upgraded scanner is not necessarily a newly introduced store defect.
                if ( ! $before && $after ) {
                    $same_version = isset( $previous['version'], $current['version'] ) && $previous['version'] === $current['version'];
                    $type = ! $same_version ? 'new_check' : ( isset($unresolved[$after['status']]) ? 'new' : 'unavailable' );
                }
            } elseif ( isset($unresolved[$before['status']]) && !isset($unresolved[$after['status']]) ) {
                $type = 'cleared';
            } elseif ( isset($unresolved[$after['status']]) && ( !isset($unresolved[$before['status']]) || $ranking[$after['status']] > $ranking[$before['status']] ) ) {
                $type = 'escalated';
            } else {
                $type = 'changed';
            }
            $result['counts'][$type]++;
            if ( count( $result['changes'] ) >= 100 ) { continue; }
            $result['changes'][] = array(
                'id' => $after ? $after['id'] : $before['id'],
                'category' => $after ? $after['category'] : $before['category'],
                'title' => $after ? $after['title'] : $before['title'],
                'type' => $type,
                'from' => $before ? $before['status'] : 'not_tested',
                'to' => $after ? $after['status'] : 'not_tested',
                'confidence' => $after ? $after['confidence'] : $before['confidence'],
            );
        }
        return $result;
    }

    public static function csv_rows( $comparison ) {
        $rows = array( array( 'change_type', 'module', 'check_id', 'title', 'previous', 'current', 'confidence' ) );
        foreach ( isset($comparison['changes']) && is_array($comparison['changes']) ? $comparison['changes'] : array() as $change ) {
            $cells = array($change['type'], $change['category'], $change['id'], $change['title'], $change['from'], $change['to'], $change['confidence']);
            $rows[] = array_map( array('HCD_Repair_Reports','csv_cell'), $cells );
        }
        return $rows;
    }
}
