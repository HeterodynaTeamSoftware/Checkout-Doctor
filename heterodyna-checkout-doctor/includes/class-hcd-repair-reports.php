<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Produces a read-only remediation plan from existing checks. Never executes the proposed actions. */
final class HCD_Repair_Reports {
    private static function validate_step( $id, $category ) {
        $steps = array(
            'woo_missing' => 'Potwierdź, że WooCommerce jest aktywne; uruchom diagnostykę ponownie.',
            'checkout_page' => 'Otwórz stronę zamówienia w trybie prywatnym i powtórz skan konfiguracji.',
            'checkout_render' => 'Otwórz checkout na stagingu; sprawdź wyświetlanie formularza i podstawowe pola.',
            'cart_page' => 'Dodaj produkt testowy do koszyka na stagingu i potwierdź przejście do checkoutu.',
            'https' => 'Otwórz adres zamówienia przez HTTPS i zweryfikuj certyfikat w przeglądarce.',
            'core_page_collision' => 'Sprawdź, czy koszyk i checkout mają odrębne adresy oraz poprawnie się wyświetlają.',
            'core_checkout_password' => 'Zweryfikuj publiczny dostęp do checkoutu bez sesji administratora.',
            'core_checkout_collision' => 'Sprawdź, czy w edytorze strony pozostał jeden właściwy formularz zamówienia.',
            'blocks_store_api_routes' => 'Potwierdź rejestrację tras Store API; testuj działanie na stagingu bez płatności produkcyjnych.',
            'payment_gateways' => 'Na stagingu sprawdź dostępność bramek i wykonaj test w sandboxie operatora.',
            'failed_orders' => 'Porównaj próbkę zamówień Failed z logami operatora w panelu sklepu, bez eksportowania danych klientów.',
            'conflicts_template_versions' => 'Zaktualizuj lub porównaj szablon na stagingu, a następnie przetestuj checkout.',
            'logs_indicators' => 'Sprawdź daty i kontekst wpisów bezpośrednio w logach WooCommerce; potwierdź związek z checkoutem.',
            'monitoring' => 'Porównaj typy i okresy zdarzeń z monitorowaniem operatora i testami na stagingu.',
            'readiness_db_version' => 'W WooCommerce → Status sprawdź aktualizację bazy; po bezpiecznym zakończeniu uruchom ponownie skan.',
            'readiness_endpoint_collision' => 'Na stagingu przetestuj odnośniki płatności oraz strony potwierdzenia zamówienia.',
            'browser_js_signals' => 'Otwórz konsolę DevTools w testowej sesji checkout i potwierdź występowanie błędów bez kopiowania danych klientów.',
            'browser_missing_form' => 'Na stagingu dodaj produkt do koszyka i sprawdź pojawienie się formularza klasycznego lub Checkout Block.',
            'browser_slow_store' => 'Porównaj czasy zasobów Store API w narzędziach sieciowych przeglądarki.',
            'readiness_debug_display' => 'Na stagingu zweryfikuj, że błędy PHP nie są wyświetlane klientowi checkoutu.',
        );
        if ( isset( $steps[ $id ] ) ) { return $steps[ $id ]; }
        $by_area = array(
            'shipping' => 'Na stagingu przetestuj dostępność dostawy do obsługiwanych krajów i różne typy produktów.',
            'payments' => 'Zweryfikuj konfigurację i przeprowadź płatność testową wyłącznie w sandboxie operatora.',
            'conflicts' => 'Porównaj zachowanie checkoutu na stagingu przed i po kontrolowanej zmianie.',
            'logs' => 'Zweryfikuj czas występowania sygnałów i ich związek z checkoutem w panelu sklepu.',
            'blocks' => 'Sprawdź trasę Store API i działanie Checkout Block na stagingu.',
            'orders' => 'Porównaj wynik z konfiguracją WooCommerce i testowym zamówieniem na stagingu.',
            'readiness' => 'Potwierdź konfigurację sklepu na stagingu, a następnie porównaj dwa kolejne raporty.',
        );
        return isset( $by_area[ $category ] ) ? $by_area[ $category ] : 'Wykonaj ponowny skan i przetestuj ścieżkę zakupową na stagingu.';
    }

    public static function enrich( $report ) {
        if ( ! is_array( $report ) || ! isset( $report['checks'] ) || ! is_array( $report['checks'] ) ) {
            return $report;
        }
        $plan = array();
        $groups = array();
        $confirmed = 0;
        $signals = 0;
        foreach ( $report['checks'] as $index => $check ) {
            if ( ! is_array( $check ) || ! in_array( isset( $check['status'] ) ? $check['status'] : '', array( 'critical', 'warning' ), true ) ) { continue; }
            $status = $check['status'];
            $confidence = isset( $check['confidence'] ) && 'confirmed' === $check['confidence'] ? 'confirmed' : 'signal';
            $category = isset( $check['category'] ) && is_string( $check['category'] ) ? $check['category'] : 'core';
            $id = isset( $check['id'] ) && is_string( $check['id'] ) ? $check['id'] : 'unknown';
            $priority_number = 'critical' === $status && 'confirmed' === $confidence ? 1 : ( 'confirmed' === $confidence ? 2 : 3 );
            $priority = 'P' . $priority_number;
            $plan[] = array(
                'key' => $id . '-' . (int) $index,
                'check_id' => $id,
                'priority' => $priority,
                'priority_number' => $priority_number,
                'category' => $category,
                'status' => $status,
                'confidence' => $confidence,
                'title' => isset( $check['title'] ) ? (string) $check['title'] : 'Wymaga analizy',
                'evidence' => isset( $check['evidence'] ) ? (string) $check['evidence'] : '',
                'action' => isset( $check['recommendation'] ) ? (string) $check['recommendation'] : '',
                'verify' => self::validate_step( $id, $category ),
                'validation_required' => 'signal' === $confidence,
                'safety' => 'Zmiany wprowadzaj najpierw na stagingu i wykonaj kopię zapasową.',
            );
            if ( 'confirmed' === $confidence ) { $confirmed++; } else { $signals++; }
            if ( ! isset( $groups[ $category ] ) ) { $groups[ $category ] = array(); }
            $groups[ $category ][] = $id;
        }
        usort( $plan, function( $a, $b ) {
            if ( $a['priority_number'] !== $b['priority_number'] ) { return $a['priority_number'] - $b['priority_number']; }
            return strcmp( $a['key'], $b['key'] );
        } );
        // Correlations are only co-occurrence of separate checks, never causality or an automatic risk score.
        $relations = array();
        foreach ( $groups as $category => $ids ) {
            $unique = array_values( array_unique( $ids ) );
            if ( count( $unique ) < 2 ) { continue; }
            $relations[] = array(
                'category' => $category,
                'check_ids' => array_slice( $unique, 0, 12 ),
                'check_count' => count( $unique ),
                'conclusion' => 'W tej kategorii współwystępuje kilka ustaleń. To nie potwierdza wspólnej przyczyny ani rzeczywistej awarii.',
                'next_step' => 'Zweryfikuj każde ustalenie osobno, a następnie porównaj zachowanie checkoutu na stagingu.',
            );
        }
        $report['repair_plan'] = $plan;
        $report['correlations'] = $relations;
        $report['repair_summary'] = array(
            'actions' => count( $plan ),
            'confirmed_facts_to_address' => $confirmed,
            'signals_to_verify' => $signals,
            'p1' => count( array_filter( $plan, function( $p ) { return 'P1' === $p['priority']; } ) ),
            'p2' => count( array_filter( $plan, function( $p ) { return 'P2' === $p['priority']; } ) ),
            'p3' => count( array_filter( $plan, function( $p ) { return 'P3' === $p['priority']; } ) ),
        );
        return $report;
    }

    /** Prevent spreadsheet formula injection without modifying JSON and HTML report contents. */
    public static function csv_cell( $value ) {
        $text = (string) $value;
        // Leading whitespace can conceal a formula trigger in spreadsheet applications.
        if ( preg_match( '/^\s*[=+@\-]/u', $text ) || preg_match( '/^[\t\r\n]/', $text ) ) { return "'" . $text; }
        return $text;
    }

    public static function csv_rows( $report ) {
        $rows = array( array( 'priority', 'module', 'check_id', 'title', 'evidence', 'confidence', 'recommended_action', 'how_to_verify' ) );
        $plan = isset( $report['repair_plan'] ) && is_array( $report['repair_plan'] ) ? $report['repair_plan'] : array();
        foreach ( $plan as $action ) {
            $rows[] = array_map( array( __CLASS__, 'csv_cell' ), array(
                $action['priority'], $action['category'], $action['check_id'], $action['title'], $action['evidence'],
                $action['confidence'], $action['action'], $action['verify'],
            ) );
        }
        return $rows;
    }

    public static function html_report( $report ) {
        $e = function( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); };
        $date = isset( $report['generated_at'] ) ? $report['generated_at'] : '';
        $rows = '';
        $plan = isset( $report['repair_plan'] ) && is_array( $report['repair_plan'] ) ? $report['repair_plan'] : array();
        foreach ( $plan as $action ) {
            $rows .= '<article><header><span class="pill">' . $e( $action['priority'] ) . '</span><h2>' . $e( $action['title'] ) . '</h2></header>';
            $rows .= '<p class="minor">Moduł: ' . $e( $action['category'] ) . ' · Check: ' . $e( $action['check_id'] ) . ' · ' . $e( $action['confidence'] ) . '</p>';
            $rows .= '<dl><dt>Dowód</dt><dd>' . $e( $action['evidence'] ) . '</dd><dt>Co zrobić</dt><dd>' . $e( $action['action'] ) . '</dd><dt>Jak potwierdzić naprawę</dt><dd>' . $e( $action['verify'] ) . '</dd></dl></article>';
        }
        if ( '' === $rows ) { $rows = '<p>Brak ustaleń krytycznych i ostrzeżeń w wykonanym skanie. To nie jest gwarancja braku awarii.</p>'; }
        $total = isset( $report['repair_summary']['actions'] ) ? (int) $report['repair_summary']['actions'] : 0;
        return '<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Heterodyna Checkout Doctor — Raport napraw</title><style>' .
            'body{font:15px/1.6 system-ui,Arial,sans-serif;margin:0 auto;padding:36px;max-width:970px;background:#071524;color:#e9f1ff}h1{font-size:30px;margin:8px 0}header{display:flex;align-items:center;gap:14px}h2{font-size:19px;margin:0}article{border:1px solid #34506b;border-radius:12px;padding:18px 24px;margin:20px 0;background:#122438}.pill{background:#175778;color:#fff;border-radius:8px;padding:5px 12px;font-weight:bold}.minor{color:#acc6dd}dt{color:#7ad5f8;font-weight:bold;margin-top:11px}dd{margin:2px 0 0;white-space:pre-wrap;overflow-wrap:anywhere}footer{margin-top:35px;color:#a7bed2}@media print{body{background:white;color:#111;padding:12px}article{background:white;border-color:#999;break-inside:avoid}dt,.minor,footer{color:#333}.pill{color:#111;background:#ddd}}</style></head><body>' .
            '<p class="minor">HETERODYNA TEAM SOFTWARE · OPEN SOURCE · RAPORT LOKALNY</p><h1>Checkout Doctor — plan napraw</h1><p>Data UTC: ' . $e( $date ) . ' · Liczba działań: ' . $total . '</p><p>Priorytety wskazują kolejność analizy, nie poziom ryzyka biznesowego. Sygnały wymagają osobnego potwierdzenia.</p>' . $rows .
            '<footer>Raport pasywny: bez testu transakcji, bez gwarancji sprawności sklepu. Zmiany najpierw na stagingu.</footer></body></html>';
    }
}
