<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Payments & Shipping — passive configuration diagnostics.
 * No checkout simulation, API requests, log-content reads, order mutation or secrets.
 */
final class HCD_Payments_Shipping {
    private static function finding( &$checks, $id, $category, $status, $title, $evidence, $meaning, $fix, $confidence = 'confirmed' ) {
        $checks[] = array(
            'id' => $id, 'category' => $category, 'status' => $status,
            'title' => $title, 'evidence' => $evidence, 'meaning' => $meaning,
            'recommendation' => $fix, 'confidence' => $confidence,
        );
    }

    public static function run( &$meta ) {
        $checks = array();
        self::payments( $checks, $meta );
        self::shipping( $checks, $meta );
        return $checks;
    }

    private static function option( $name, $default = null ) {
        return function_exists( 'get_option' ) ? get_option( $name, $default ) : $default;
    }

    private static function method_id( $object ) {
        if ( ! is_object( $object ) ) { return ''; }
        $id = isset( $object->id ) ? $object->id : ( isset( $object->method_id ) ? $object->method_id : '' );
        if ( ! is_scalar( $id ) ) { return ''; }
        return function_exists( 'sanitize_key' ) ? sanitize_key( (string) $id ) : preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $id ) );
    }

    private static function payments( &$checks, &$meta ) {
        $meta['payment_gateways_total'] = null;
        $meta['gateways_enabled'] = null;
        $meta['payment_gateway_ids'] = array();
        try {
            $wc = function_exists( 'WC' ) ? WC() : null;
            $manager = $wc && method_exists( $wc, 'payment_gateways' ) ? $wc->payment_gateways() : null;
            if ( ! $manager || ! method_exists( $manager, 'payment_gateways' ) ) {
                self::finding( $checks, 'payment_gateways', 'payments', 'info', 'Nie można odczytać listy bramek',
                    'API WooCommerce Payment Gateways jest niedostępne w tym przebiegu.',
                    'Nie można ustalić konfiguracji płatności.',
                    'Sprawdź stan WooCommerce oraz WooCommerce → Ustawienia → Płatności.', 'signal' );
                return;
            }
            $gateways = $manager->payment_gateways();
            if ( ! is_array( $gateways ) ) {
                self::finding( $checks, 'payment_gateways', 'payments', 'info', 'Lista bramek nie została zwrócona',
                    'WooCommerce zwrócił wartość inną niż lista.', 'Stan bramek pozostaje niezweryfikowany.',
                    'Sprawdź konfigurację i diagnostykę WooCommerce.', 'signal' );
                return;
            }
            $enabled = array();
            $registered = 0;
            foreach ( $gateways as $gateway ) {
                if ( ! is_object( $gateway ) ) { continue; }
                $registered++;
                $id = self::method_id( $gateway );
                if ( '' === $id ) { continue; }
                if ( isset( $gateway->enabled ) && 'yes' === $gateway->enabled ) { $enabled[ $id ] = $gateway; }
            }
            $ids = array_keys( $enabled );
            sort( $ids, SORT_STRING );
            $meta['payment_gateways_total'] = $registered;
            $meta['gateways_enabled'] = count( $ids );
            $meta['payment_gateway_ids'] = $ids;
            if ( ! $ids ) {
                self::finding( $checks, 'payment_gateways', 'payments', 'warning', 'Nie wykryto włączonych bramek płatności',
                    'Zarejestrowane: ' . $registered . '; włączone: 0.',
                    'Standardowy checkout może nie oferować płatności. Samo ustawienie nie dowodzi awarii sklepu (np. sklep z niestandardową metodą).',
                    'W WooCommerce → Ustawienia → Płatności aktywuj właściwą metodę i wykonaj test na stagingu.', 'signal' );
            } else {
                self::finding( $checks, 'payment_gateways', 'payments', 'info', 'Zarejestrowane i włączone bramki',
                    'Zarejestrowane: ' . $registered . '; włączone: ' . count( $ids ) . '; identyfikatory: ' . implode( ', ', $ids ) . '.',
                    'To wyłącznie odczyt przełączników. Dostępność dla konkretnego klienta, prawidłowość kluczy i połączeń z operatorem nie zostały sprawdzone.',
                    'Przetestuj każdą wymaganą metodę w piaskownicy operatora.', 'confirmed' );
            }
            foreach ( $enabled as $id => $gateway ) {
                // Only public identifiers and fixed-format configuration facts. Never expose get_settings(), credentials, titles or descriptions.
                self::finding( $checks, 'payment_method_' . $id, 'payments', 'info', 'Włączona metoda: ' . $id,
                    'ID: ' . $id . ' · enabled=yes.',
                    'Metoda jest włączona w ustawieniach. Nie jest to dowód, że płatność przechodzi lub metoda pojawi się w koszyku.',
                    'Wykonaj próbę w środowisku testowym odpowiedniego dostawcy.', 'confirmed' );
                if ( 'cod' === $id && method_exists( $gateway, 'get_option' ) ) {
                    try {
                        $restricted = $gateway->get_option( 'enable_for_methods', array() );
                        if ( is_array( $restricted ) && $restricted ) {
                            self::finding( $checks, 'payments_cod_shipping_rules', 'payments', 'info', 'Pobranie ograniczone do wybranych metod dostawy',
                                'Dla COD ustawiono listę ' . count( $restricted ) . ' dopuszczonych metod wysyłki (bez eksportu szczegółów).',
                                'Pobranie nie będzie dostępne dla każdej przesyłki; nie oznacza to usterki.',
                                'Sprawdź spójność warunków COD ze strefami i metodami dostawy.', 'confirmed' );
                        }
                    } catch ( Throwable $e ) {
                        self::finding( $checks, 'payments_cod_config', 'payments', 'warning', 'Nie można odczytać ograniczeń pobrania',
                            'Odczyt opcji COD zakończył się wyjątkiem (szczegóły nie są eksportowane).',
                            'Nie ustalono, dla których metod dostawy pobranie jest dostępne.',
                            'Sprawdź ustawienia COD w panelu WooCommerce i logi dostawcy.', 'signal' );
                    }
                }
            }
            // WooCommerce webhooks are NOT evidence of gateway callbacks. Do not mislabel them.
            self::finding( $checks, 'payments_callback_scope', 'payments', 'info', 'Callbacki i webhooki operatorów — ograniczenie testu',
                'Wykonano pasywny odczyt konfiguracji bramek; nie testowano adresów callback, podpisów ani łączności z operatorem.',
                'Webhooki WooCommerce związane z zamówieniami nie muszą być callbackami bramek płatniczych.',
                'Sprawdź zdarzenia i callbacki na panelu operatora, następnie porównaj je z zamówieniami w stagingu.', 'confirmed' );
        } catch ( Throwable $e ) {
            self::finding( $checks, 'payment_gateways', 'payments', 'warning', 'Błąd inicjalizacji komponentu płatności',
                'Odczyt bramek wywołał wyjątek; komunikat, ścieżka i stack trace zostały celowo pominięte.',
                'Nie można ustalić przyczyny bez logów lokalnego sklepu. Błąd wystąpił w czasie diagnostyki.',
                'Sprawdź dzienniki PHP/WooCommerce i odizoluj problem w środowisku testowym.', 'signal' );
        }
    }

    private static function shipping( &$checks, &$meta ) {
        $meta['shipping_enabled'] = null;
        $meta['shipping_zones_count'] = null;
        $meta['shipping_methods_enabled'] = null;
        $meta['shipping_zones_without_methods'] = array();
        try {
            if ( ! function_exists( 'wc_shipping_enabled' ) ) {
                self::finding( $checks, 'shipping', 'shipping', 'info', 'API wysyłki niedostępne',
                    'Brak wc_shipping_enabled().', 'Nie udało się zweryfikować ustawień dostaw.',
                    'Sprawdź WooCommerce → Ustawienia → Wysyłka.', 'signal' );
                return;
            }
            $enabled = (bool) wc_shipping_enabled();
            $meta['shipping_enabled'] = $enabled;
            if ( ! $enabled ) {
                self::finding( $checks, 'shipping', 'shipping', 'info', 'Wysyłka jest wyłączona',
                    'wc_shipping_enabled() = false.',
                    'Może to być poprawna konfiguracja sklepu oferującego wyłącznie produkty cyfrowe.',
                    'Jeśli sprzedajesz towary fizyczne, sprawdź konfigurację dostaw.', 'confirmed' );
                return;
            }
            if ( ! class_exists( 'WC_Shipping_Zones' ) || ! method_exists( 'WC_Shipping_Zones', 'get_zones' ) ) {
                self::finding( $checks, 'shipping', 'shipping', 'info', 'Brak dostępu do rejestru stref',
                    'Wysyłka włączona, ale nie znaleziono API WC_Shipping_Zones::get_zones().',
                    'Nie można określić aktywnych metod dla regionów.', 'Sprawdź moduły wysyłki WooCommerce.', 'signal' );
                return;
            }
            $zones = WC_Shipping_Zones::get_zones();
            if ( ! is_array( $zones ) ) { throw new RuntimeException( 'invalid zone result' ); }
            $meta['shipping_zones_count'] = count( $zones );
            $zone_ids = array();
            foreach ( $zones as $key => $data ) {
                if ( is_array( $data ) && isset( $data['zone_id'] ) ) { $zone_ids[] = (int) $data['zone_id']; }
                elseif ( is_numeric( $key ) ) { $zone_ids[] = (int) $key; }
            }
            // Zone 0 (“rest of world”) must be considered separately.
            $zone_ids = array_values( array_unique( array_filter( $zone_ids, function( $id ) { return $id > 0; } ) ) );
            sort( $zone_ids, SORT_NUMERIC );
            $counts = array( 'enabled' => 0, 'disabled' => 0, 'inspected' => 0 );
            $incomplete = 0;
            foreach ( $zone_ids as $id ) {
                $object = method_exists( 'WC_Shipping_Zones', 'get_zone' ) ? WC_Shipping_Zones::get_zone( $id ) : null;
                $data = isset( $zones[$id] ) && is_array( $zones[$id] ) ? $zones[$id] : array();
                $methods = self::zone_methods( $object, $data );
                if ( null === $methods ) { $incomplete++; continue; }
                self::evaluate_zone( $checks, $id, $object, $methods, $counts, $meta, false );
            }
            $fallback = method_exists( 'WC_Shipping_Zones', 'get_zone' ) ? WC_Shipping_Zones::get_zone( 0 ) : null;
            $rest_methods = self::zone_methods( $fallback, array() );
            if ( null !== $rest_methods ) {
                self::evaluate_zone( $checks, 0, $fallback, $rest_methods, $counts, $meta, true );
            } else { $incomplete++; }
            $meta['shipping_methods_enabled'] = $counts['enabled'];
            $meta['shipping_methods_disabled'] = $counts['disabled'];
            $meta['shipping_zones_inspected'] = $counts['inspected'];
            $meta['shipping_zones_uninspected'] = $incomplete;
            $zone_count = count( $zone_ids );
            if ( $incomplete ) {
                self::finding( $checks, 'shipping', 'shipping', 'info', 'Wysyłka włączona — częściowy odczyt metod',
                    'Dodatkowe strefy: ' . $zone_count . '; odczytane: ' . $counts['inspected'] . '; pominięte: ' . $incomplete . '.',
                    'Nie wszystkie metody były dostępne przez API. Nie można wnioskować o braku dostaw.',
                    'Sprawdź strefy i dostępność metod w WooCommerce → Ustawienia → Wysyłka.', 'signal' );
            } elseif ( 0 === $counts['enabled'] ) {
                self::finding( $checks, 'shipping', 'shipping', 'warning', 'Wysyłka włączona, ale brak aktywnych metod w strefach',
                    'Odczytano ' . $counts['inspected'] . ' stref (łącznie z domyślną); aktywne metody: 0.',
                    'W standardowym mechanizmie dostawy nie wykryto sposobu wysyłki. Produkty wirtualne lub własny moduł mogą działać inaczej.',
                    'Przypisz aktywną metodę do odpowiedniej strefy i przetestuj adres klienta na stagingu.', 'signal' );
            } else {
                self::finding( $checks, 'shipping', 'shipping', 'info', 'Włączone metody wysyłki w strefach',
                    'Strefy dodatkowe: ' . $zone_count . '; aktywne metody: ' . $counts['enabled'] . '; nieaktywne: ' . $counts['disabled'] . '.',
                    'Nie sprawdzono dopasowania stref do rzeczywistego adresu, produktów ani stawek.',
                    'Sprawdź adresy docelowych odbiorców i obliczenia stawek na stagingu.', 'confirmed' );
            }
            self::shipping_country_rules( $checks );
            self::shipping_cart_calculator( $checks );
        } catch ( Throwable $e ) {
            self::finding( $checks, 'shipping', 'shipping', 'warning', 'Nie można odczytać konfiguracji wysyłki',
                'Moduł wysyłki zgłosił wyjątek; prywatna treść błędu nie została zapisana.',
                'Ten skan nie potwierdził stanu metod i stref.',
                'Przejrzyj dzienniki WooCommerce/PHP i sprawdź rozszerzenia dostaw.', 'signal' );
        }
    }

    /** null = read impossible; [] = confirmed empty zone. */
    private static function zone_methods( $zone, $zone_data ) {
        if ( is_object( $zone ) && method_exists( $zone, 'get_shipping_methods' ) ) {
            $items = $zone->get_shipping_methods( false );
            return is_array( $items ) ? $items : null;
        }
        return isset( $zone_data['shipping_methods'] ) && is_array( $zone_data['shipping_methods'] ) ? $zone_data['shipping_methods'] : null;
    }

    private static function evaluate_zone( &$checks, $id, $zone, $methods, &$counts, &$meta, $fallback ) {
        $counts['inspected']++;
        $active_ids = array();
        $disabled = 0;
        $seen = 0;
        foreach ( $methods as $method ) {
            if ( ! is_object( $method ) ) { continue; }
            $seen++;
            $method_id = self::method_id( $method );
            $is_enabled = isset( $method->enabled ) && 'yes' === $method->enabled;
            if ( $is_enabled ) {
                $counts['enabled']++;
                $active_ids[] = $method_id ? $method_id : 'unknown';
                if ( 'free_shipping' === $method_id ) { self::free_shipping_rules( $checks, $id, $method ); }
            } else { $counts['disabled']++; $disabled++; }
        }
        $suffix = $fallback ? 'pozostałe regiony' : 'ID ' . $id;
        $evidence = 'Strefa ' . $suffix . ' · aktywne: ' . count( $active_ids ) . ' · nieaktywne: ' . $disabled . ' · ID metod: ' . ( $active_ids ? implode( ', ', array_unique( $active_ids ) ) : 'brak' ) . '.';
        if ( ! $active_ids ) {
            if ( ! $fallback ) { $meta['shipping_zones_without_methods'][] = $id; }
            self::finding( $checks, 'shipping_zone_' . $id, 'shipping', $fallback ? 'info' : 'warning',
                $fallback ? 'Brak dostawy w strefie domyślnej' : 'Brak aktywnych metod w strefie ' . $id,
                $evidence,
                $fallback ? 'Wysyłka poza specjalnie zdefiniowane strefy może być celowo niedostępna.' : 'Klient pasujący do tej strefy może nie otrzymać żadnej standardowej metody dostawy.',
                $fallback ? 'Jeśli sprzedajesz globalnie, rozważ metodę domyślną. W innym przypadku brak metody jest dopuszczalny.' : 'Sprawdź, czy ta strefa powinna realizować dostawy; jeśli tak, włącz metodę i przetestuj adres.', 'signal' );
        } else {
            self::finding( $checks, 'shipping_zone_' . $id, 'shipping', 'pass', 'Znaleziono aktywne metody — strefa ' . $id,
                $evidence, 'Metody są włączone. Ten test nie wykonuje obliczenia kosztów dla koszyka.',
                'Przetestuj stawki i adresy dla reprezentatywnych produktów.', 'confirmed' );
        }
    }

    private static function free_shipping_rules( &$checks, $zone_id, $method ) {
        if ( ! method_exists( $method, 'get_option' ) ) { return; }
        try {
            $requirement = $method->get_option( 'requires', '' );
            $coupons = self::option( 'woocommerce_enable_coupons', 'yes' );
            if ( in_array( $requirement, array( 'coupon', 'both' ), true ) && 'no' === $coupons ) {
                self::finding( $checks, 'shipping_free_coupon_zone_' . $zone_id, 'shipping', 'warning',
                    'Darmowa wysyłka wymaga kuponu, lecz kupony są wyłączone',
                    'Strefa ' . $zone_id . ' · free_shipping requires=' . $requirement . ' · woocommerce_enable_coupons=no.',
                    'Reguła darmowej dostawy opiera się na kuponach, które wyłączono globalnie. To potwierdzona niespójność ustawień, lecz nie dowód awarii checkout.',
                    'Włącz kupony lub zmień regułę darmowej dostawy w ustawieniach tej metody.', 'confirmed' );
            }
        } catch ( Throwable $e ) {
            self::finding( $checks, 'shipping_free_config_zone_' . $zone_id, 'shipping', 'info',
                'Nie odczytano reguły darmowej wysyłki', 'Strefa ' . $zone_id . ' · wyjątek podczas odczytu opcji (bez szczegółów).',
                'Nie można potwierdzić wymagań tej metody.', 'Sprawdź ustawienia darmowej dostawy w WooCommerce.', 'signal' );
        }
    }

    private static function shipping_country_rules( &$checks ) {
        $scope = self::option( 'woocommerce_ship_to_countries', 'all' );
        if ( 'specific' === $scope ) {
            $countries = self::option( 'woocommerce_specific_ship_to_countries', array() );
            $count = is_array( $countries ) ? count( $countries ) : 0;
            self::finding( $checks, 'shipping_countries', 'shipping', $count ? 'pass' : 'warning',
                $count ? 'Skonfigurowano kraje dostawy' : 'Wybrano konkretne kraje, ale lista jest pusta',
                'Tryb: specific · liczba krajów: ' . $count . '.',
                $count ? 'Ograniczenie krajów jest zapisane; zgodność adresów ze strefami wymaga osobnego testu.' : 'W ustawieniach nie wskazano żadnego dozwolonego kraju dostawy.',
                $count ? 'Sprawdź strefy wysyłki i kraje obsługiwane w praktyce.' : 'Wybierz kraje w WooCommerce → Ustawienia → Ogólne.', $count ? 'confirmed' : 'confirmed' );
        } elseif ( is_string( $scope ) && $scope ) {
            self::finding( $checks, 'shipping_countries', 'shipping', 'info', 'Zakres krajów dostawy',
                'Tryb konfiguracji: ' . preg_replace( '/[^a-z_]/', '', $scope ) . '.',
                'To ustawienie globalne; dostępność konkretnej stawki wymaga dopasowania strefy i koszyka.',
                'Sprawdź politykę wysyłki i strefy w WooCommerce.', 'confirmed' );
        }
    }

    private static function shipping_cart_calculator( &$checks ) {
        $setting = self::option( 'woocommerce_enable_shipping_calc', null );
        if ( 'no' === $setting ) {
            self::finding( $checks, 'shipping_cart_calculator', 'shipping', 'info', 'Kalkulator dostawy na koszyku jest ukryty',
                'woocommerce_enable_shipping_calc=no.',
                'Wyłączenie kalkulatora w koszyku nie wyłącza automatycznie stawek podczas checkout.',
                'Jeśli chcesz pokazywać koszty wcześniej, włącz kalkulator na stronie koszyka.', 'confirmed' );
        }
    }
}
