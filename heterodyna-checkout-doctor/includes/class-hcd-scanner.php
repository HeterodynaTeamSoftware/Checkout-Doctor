<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Read-only diagnostics. No external network calls, payments, or order mutations. */
final class HCD_Scanner {
    private static function item( &$items, $id, $severity, $title, $evidence, $why, $fix, $confidence = 'confirmed' ) {
        $items[] = array(
            'id' => $id,
            'status' => $severity,
            'title' => $title,
            'evidence' => $evidence,
            'meaning' => $why,
            'recommendation' => $fix,
            'confidence' => $confidence,
            'category' => self::category( $id ),
        );
    }

    private static function category( $id ) {
        if ( 'payment_gateways' === $id ) { return 'payments'; }
        if ( 'shipping' === $id ) { return 'shipping'; }
        if ( 'failed_orders' === $id ) { return 'orders'; }
        if ( 'monitoring' === $id ) { return 'events'; }
        return 'core';
    }

    public static function run() {
        $checks = array();
        $meta = array(
            'woocommerce' => class_exists( 'WooCommerce' ),
            'woocommerce_version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'nieaktywne',
            'wp_version' => get_bloginfo( 'version' ),
            'php_version' => PHP_VERSION,
            'tested_at_utc' => gmdate( 'c' ),
            'monitoring' => HCD_Events::enabled(),
        );

        if ( ! class_exists( 'WooCommerce' ) ) {
            self::item( $checks, 'woo_missing', 'critical', 'WooCommerce jest nieaktywne',
                'Nie znaleziono aktywnej klasy WooCommerce.',
                'Bez WooCommerce nie ma standardowego checkout do diagnozowania.',
                'Zainstaluj i aktywuj WooCommerce, następnie uruchom skan ponownie.' );
            if ( class_exists( 'HCD_Compatibility' ) ) {
                try { $checks = array_merge( $checks, HCD_Compatibility::run( $meta ) ); } catch ( Throwable $e ) { /* WooCommerce unavailable remains decisive. */ }
            }
            if ( class_exists( 'HCD_Release_Gate' ) ) {
                try { $checks = array_merge( $checks, HCD_Release_Gate::run( $meta ) ); }
                catch ( Throwable $e ) { /* WooCommerce missing remains decisive. */ }
            }
            return self::finish( $checks, $meta );
        }

        // ZIP 11: read-only runtime compatibility inventory; no dynamic gateway callbacks.
        if ( class_exists( 'HCD_Compatibility' ) ) {
            try {
                $checks = array_merge( $checks, HCD_Compatibility::run( $meta ) );
            } catch ( Throwable $e ) {
                self::item( $checks, 'compat_unavailable', 'info', 'Matryca zgodności niedostępna',
                    'Nie ukończono kontroli API i wersji; treść wyjątku jest ukryta.',
                    'Brak wyniku nie oznacza niezgodności sklepu.',
                    'Sprawdź log PHP i wykonaj ponowny test.', 'signal' );
                $checks[count($checks)-1]['category'] = 'compatibility';
            }
        }

        // Read actual WooCommerce configuration, not guesses about payment gateway configuration.
        $checkout_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'checkout' ) : -1;
        $checkout = $checkout_id > 0 ? get_post( $checkout_id ) : null;
        if ( ! $checkout ) {
            self::item( $checks, 'checkout_page', 'critical', 'Brak strony zamówienia',
                'WooCommerce nie wskazuje istniejącej strony Checkout (ID: ' . $checkout_id . ').',
                'Klasyczny proces zamówienia może kierować do brakującego zasobu.',
                'WooCommerce → Ustawienia → Zaawansowane: przypisz opublikowaną stronę Zamówienie.' );
        } elseif ( 'publish' !== $checkout->post_status ) {
            self::item( $checks, 'checkout_page', 'critical', 'Strona Checkout nie jest opublikowana',
                'ID ' . $checkout_id . ' · status: ' . $checkout->post_status,
                'Użytkownicy mogą nie mieć dostępu do standardowej strony zamówienia.',
                'Opublikuj stronę i zweryfikuj przypisanie w ustawieniach WooCommerce.' );
        } else {
            self::item( $checks, 'checkout_page', 'pass', 'Strona Checkout jest opublikowana',
                'ID ' . $checkout_id . ' · status: publish.',
                'Podstawowa konfiguracja strony zamówienia została potwierdzona.',
                'Nie wymaga działania.' );
        }

        if ( $checkout ) {
            $has_block = function_exists( 'has_block' ) && has_block( 'woocommerce/checkout', $checkout );
            $has_shortcode = has_shortcode( $checkout->post_content, 'woocommerce_checkout' );
            $meta['checkout_type'] = $has_block ? 'WooCommerce Checkout Block' : ( $has_shortcode ? 'Klasyczny shortcode' : 'Niewykryty / szablon niestandardowy' );
            if ( $has_block || $has_shortcode ) {
                self::item( $checks, 'checkout_render', 'pass', 'Rozpoznano mechanizm Checkout',
                    $meta['checkout_type'] . ' na stronie ID ' . $checkout_id . '.',
                    'Strona zawiera typowy element WooCommerce obsługujący zamówienia.',
                    'Nie wymaga działania.' );
            } else {
                self::item( $checks, 'checkout_render', 'warning', 'Nie rozpoznano formularza Checkout',
                    'Nie odnaleziono bloku woocommerce/checkout ani shortcode [woocommerce_checkout].',
                    'Strona może korzystać z szablonu niestandardowego lub nie zawierać formularza zamówień.',
                    'Otwórz edytor tej strony i sprawdź formularz. Przy własnym szablonie zweryfikuj ręcznie działanie.', 'signal' );
            }
        }

        $cart_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'cart' ) : -1;
        $cart = $cart_id > 0 ? get_post( $cart_id ) : null;
        if ( ! $cart || 'publish' !== $cart->post_status ) {
            self::item( $checks, 'cart_page', 'warning', 'Strona koszyka wymaga weryfikacji',
                'Skonfigurowane ID: ' . $cart_id . '; status: ' . ( $cart ? $cart->post_status : 'brak strony' ) . '.',
                'Tradycyjna ścieżka koszyk → zamówienie może działać nieprawidłowo; sklepy mogą stosować koszyk boczny lub checkout bez koszyka.',
                'Sprawdź stronę koszyka w WooCommerce → Ustawienia → Zaawansowane oraz przetestuj ścieżkę zakupu.', 'signal' );
        } else {
            self::item( $checks, 'cart_page', 'pass', 'Strona koszyka jest dostępna',
                'ID ' . $cart_id . ' · status: publish.',
                'Standardowa strona koszyka została poprawnie przypisana.',
                'Nie wymaga działania.' );
        }

        $url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/' );
        $scheme = wp_parse_url( $url, PHP_URL_SCHEME );
        if ( 'https' === strtolower( (string) $scheme ) ) {
            self::item( $checks, 'https', 'pass', 'Adres Checkout używa HTTPS',
                'Schemat adresu zamówienia: HTTPS.',
                'Wygenerowany URL zamówienia korzysta z szyfrowanego protokołu.',
                'Nie wymaga działania. Ten test nie sprawdza certyfikatu ani całej ścieżki sieciowej.' );
        } else {
            self::item( $checks, 'https', 'critical', 'Adres Checkout nie używa HTTPS',
                'Schemat adresu zamówienia: ' . ( $scheme ? strtoupper( $scheme ) : 'brak' ) . '.',
                'Dane przekazywane do checkout powinny być przesyłane po HTTPS.',
                'Włącz poprawny certyfikat TLS i skonfiguruj adresy WordPress/WooCommerce na HTTPS.' );
        }

        // v1.2: dedicated read-only Payments & Shipping analyzer.
        if ( class_exists( 'HCD_Payments_Shipping' ) ) {
            try { $checks = array_merge( $checks, HCD_Payments_Shipping::run( $meta ) ); } catch ( Throwable $e ) {
                self::item( $checks, 'payments_shipping_unavailable', 'info', 'Nie ukończono kontroli płatności / wysyłki',
                    'Moduł nie zwrócił wyników.', 'Brak pomiaru nie jest dowodem awarii.',
                    'Sprawdź dzienniki PHP i ponów skan.', 'signal' );
                $checks[count($checks)-1]['category'] = 'payments';
            }
        }

        if ( function_exists( 'wc_get_orders' ) ) {
            try {
                $week_ago = (string) ( time() - 7 * DAY_IN_SECONDS );
                $result = wc_get_orders( array(
                    'status' => array( 'failed' ), 'date_created' => '>=' . $week_ago,
                    'limit' => 1, 'paginate' => true, 'return' => 'ids',
                ) );
                $failed = is_object( $result ) && isset( $result->total ) ? (int) $result->total : null;
                if ( null !== $failed ) {
                    $meta['failed_orders_last_7_days'] = $failed;
                    self::item( $checks, 'failed_orders', $failed ? 'warning' : 'info',
                        'Zamówienia ze statusem Failed w ostatnich 7 dniach',
                        'WooCommerce zwrócił ' . $failed . ' zamówień ze statusem failed od UTC ' . gmdate( 'Y-m-d H:i:s', (int) $week_ago ) . ' (według daty utworzenia).',
                        'Status Failed nie rozstrzyga, czy przyczyną był błąd oprogramowania, odrzucona płatność czy działanie klienta.',
                        $failed ? 'Sprawdź logi konkretnej bramki i zamówienia w panelu WooCommerce; nie traktuj tego licznika jako dowodu usterki.' : 'Brak takich zamówień nie dowodzi bezawaryjnego checkout.',
                        'signal' );
                }
            } catch ( Throwable $e ) {
                self::item( $checks, 'failed_orders', 'info', 'Nie odczytano historii Failed',
                    'Zapytanie wc_get_orders nie powiodło się.',
                    'Błąd API historii zamówień uniemożliwił tę część diagnostyki.',
                    'Sprawdź stan WooCommerce i dzienniki PHP; skaner nie zmienia zamówień.', 'signal' );
            }
        }

        // Expanded v1.1 checkout path checks (no HTTP calls, payments or order mutations).
        if ( class_exists( 'HCD_Checkout_Core' ) ) {
            try { $checks = array_merge( $checks, HCD_Checkout_Core::run( $meta ) ); } catch ( Throwable $e ) {
                self::item( $checks, 'core_module_unavailable', 'info', 'Nie ukończono testów silnika checkoutu',
                    'Moduł nie zwrócił wyników.', 'Nie oceniono tej części sklepu.',
                    'Sprawdź dzienniki PHP i ponów skan.', 'signal' );
                $checks[count($checks)-1]['category'] = 'core';
            }
        }

        // New local readiness probes (no front-end requests or order mutations).
        if ( class_exists( 'HCD_Readiness' ) ) {
            try {
                $checks = array_merge( $checks, HCD_Readiness::run( $meta ) );
            } catch ( Throwable $e ) {
                self::item( $checks, 'readiness_unavailable', 'info', 'Nie ukończono kontroli gotowości',
                    'Moduł gotowości nie zwrócił wyników.', 'Nie można ocenić tej sekcji.',
                    'Sprawdź dzienniki PHP; szczegóły wyjątków nie są eksportowane.', 'signal' );
                $checks[count($checks)-1]['category'] = 'readiness';
            }
        }

        // ZIP 8: checkout block integration inventory; no field/order values or network calls.
        if ( class_exists( 'HCD_Block_Compat' ) ) {
            try {
                $checks = array_merge( $checks, HCD_Block_Compat::run( $meta ) );
            } catch ( Throwable $e ) {
                self::item( $checks, 'block_compat_unavailable', 'info', 'Analiza integracji blokowych niedostępna',
                    'Nie ukończono odczytu rejestrów Checkout Block.',
                    'Nie jest to dowód awarii checkoutu.',
                    'Sprawdź dzienniki PHP i ponów skan na stagingu.', 'signal' );
                $checks[count($checks)-1]['category'] = 'block_compat';
            }
        }

        // ZIP 9: bounded, passive scenario hints derived from static configuration only.
        if ( class_exists( 'HCD_Scenarios' ) ) {
            try {
                $checks = array_merge( $checks, HCD_Scenarios::run( $meta ) );
            } catch ( Throwable $e ) {
                self::item( $checks, 'scenarios_unavailable', 'info', 'Nie ukończono analizy scenariuszy',
                    'Odczyt lokalnej konfiguracji nie zwrócił pełnego wyniku.',
                    'Brak wyniku nie dowodzi awarii płatności ani wysyłki.',
                    'Sprawdź logi WooCommerce i uruchom test ponownie.', 'signal' );
                $checks[count($checks)-1]['category'] = 'scenarios';
            }
        }

        // ZIP 10: opt-in staging-only browser visibility snapshots (no checkout submission).
        if ( class_exists( 'HCD_Payment_Visibility' ) ) {
            try {
                $checks = array_merge( $checks, HCD_Payment_Visibility::run( $meta ) );
            } catch ( Throwable $e ) {
                self::item( $checks, 'visibility_unavailable', 'info', 'Test widoczności nie został ukończony',
                    'Nie można było odczytać metadanych obserwatora.', 'Nie jest to dowód problemu z bramką.',
                    'Sprawdź konfigurację stagingu i odśwież skan.', 'signal' );
                $checks[count($checks)-1]['category'] = 'visibility';
            }
        }

        // ZIP 4/5: independent passive conflicts and technical-log indicators.
        if ( class_exists( 'HCD_Conflicts_Logs' ) ) {
            try {
                $checks = array_merge( $checks, HCD_Conflicts_Logs::run( $meta ) );
            } catch ( Throwable $e ) {
                self::item( $checks, 'conflicts_module_unavailable', 'info', 'Moduł konfliktów nie został ukończony',
                    'Wystąpił wyjątek podczas lokalnego odczytu metadanych.',
                    'Nie wszystkie kontrole są dostępne. Szczegóły wyjątku nie trafiają do raportu.',
                    'Sprawdź dziennik PHP; skan nie zmienia plików.', 'signal' );
                $checks[count($checks)-1]['category'] = 'conflicts';
            }
        }

        // Opt-in, administrator-only frontend browser signals (no customer data or raw errors).
        try {
            $checks = array_merge( $checks, HCD_Browser_Probe::run( $meta ) );
        } catch ( Throwable $e ) {
            self::item( $checks, 'browser_unavailable', 'info', 'Obserwator przeglądarki niedostępny',
                'Brak wyników z modułu przeglądarkowego.', 'Nie oceniono błędów JS.',
                'Sprawdź instalację modułu i ponów skan.', 'signal' );
            $checks[count($checks)-1]['category'] = 'browser';
        }

        // ZIP 14: passive browser-collected HTTP status signals. Never invokes REST checkout.
        if ( class_exists( 'HCD_Store_API_Observer' ) ) {
            try { $checks = array_merge( $checks, HCD_Store_API_Observer::run( $meta ) ); }
            catch ( Throwable $e ) {
                self::item( $checks, 'store_observer_unavailable', 'info', 'Brak obserwacji Store API',
                    'Moduł obserwacji nie został ukończony.', 'Nie przesądza to o usterce checkoutu.',
                    'Sprawdź PHP i ponów skan na stagingu.', 'signal' );
                $checks[count($checks)-1]['category'] = 'store_api';
            }
        }

        // ZIP 12: opt-in human staging test ledger; never auto-labels an integration as passed.
        if ( class_exists( 'HCD_Integration_Tests' ) ) {
            try { $checks = array_merge( $checks, HCD_Integration_Tests::run( $meta ) ); } catch ( Throwable $e ) {
                self::item( $checks, 'integration_unavailable', 'info', 'Nie odczytano checklisty integracyjnej',
                    'Brak wyniku ręcznych testów.', 'Nie rozstrzyga to działania checkoutu.',
                    'Sprawdź konfigurację WordPress i odśwież stronę.', 'signal' );
                $checks[count($checks)-1]['category'] = 'integration';
            }
        }

        $counts = HCD_Events::summary( 7 );
        $meta['events_last_7_days'] = $counts;
        if ( HCD_Events::enabled() ) {
            $err_count = $counts['store_api_error'];
            self::item( $checks, 'monitoring', $err_count ? 'warning' : 'info',
                'Monitoring checkout – ostatnie 7 dni',
                'Store API POST /checkout – błędne odpowiedzi: ' . $err_count . '; błędy walidacji klasycznej: ' . $counts['classic_validation'] . '; zamówienia Failed: ' . $counts['order_failed'] . '.',
                'Są to zarejestrowane zdarzenia, nie pełny pomiar skuteczności płatności. Brak zdarzeń nie gwarantuje braku problemów.',
                $err_count ? 'Przejdź do dziennika zdarzeń i porównaj kody z logami odpowiedniego operatora płatności.' : 'Obserwuj dane i wykonaj osobne testy sandbox.', 'signal' );
        } else {
            self::item( $checks, 'monitoring', 'info', 'Monitoring zdarzeń jest wyłączony',
                'Zapis zdarzeń checkout: wyłączony (ustawienie domyślne).',
                'Skan ustawień działa, ale bez aktywnego monitoringu nie powstaje historia nowych prób checkout.',
                'Opcjonalnie włącz monitoring w Ustawieniach. Zbierane są wyłącznie kody i znaczniki czasu.', 'confirmed' );
        }

        // ZIP 15: correlate only already aggregated evidence, never raw Store API samples or log lines.
        if ( class_exists( 'HCD_Evidence_Correlation' ) ) {
            try { $checks = array_merge( $checks, HCD_Evidence_Correlation::run( $meta ) ); }
            catch ( Throwable $e ) {
                self::item( $checks, 'correlation_unavailable', 'info', 'Nie udało się zestawić dowodów',
                    'Brak porównania źródeł.', 'Nie przesądza to o sprawności checkoutu.',
                    'Sprawdź działanie modułów i ponów skan.', 'signal' );
                $checks[ count( $checks ) - 1 ]['category'] = 'correlation';
            }
        }

        // ZIP 16: human verification gate, not an automated production approval.
        if ( class_exists( 'HCD_Release_Gate' ) ) {
            try { $checks = array_merge( $checks, HCD_Release_Gate::run( $meta ) ); }
            catch ( Throwable $e ) {
                self::item( $checks, 'release_gate_unavailable', 'info', 'Brak kontroli przed publikacją',
                    'Moduł bramki wydania nie został ukończony.', 'Brak wyniku nie oznacza gotowości produkcyjnej.',
                    'Przejrzyj checklistę na stagingu i ponów skan.', 'signal' );
                $checks[ count( $checks ) - 1 ]['category'] = 'compatibility';
            }
        }
        return self::finish( $checks, $meta );
    }

    private static function finish( $checks, $meta ) {
        $summary = array( 'critical' => 0, 'warning' => 0, 'pass' => 0, 'info' => 0 );
        foreach ( $checks as $check ) { $summary[ $check['status'] ]++; }
        $report = array( 'version' => HCD_VERSION, 'generated_at' => gmdate( 'c' ), 'summary' => $summary,
            'meta' => $meta, 'checks' => $checks );
        return class_exists( 'HCD_Repair_Reports' ) ? HCD_Repair_Reports::enrich( $report ) : $report;
    }
}
