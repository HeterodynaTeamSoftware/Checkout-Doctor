<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ZIP 9: static, read-only scenario audit. Never executes gateway is_available(),
 * canMakePayment, shipping rate calculations, checkout submissions or cart changes.
 */
final class HCD_Scenarios {
    private static function item( &$checks, $id, $status, $title, $evidence, $meaning, $recommendation, $confidence = 'signal' ) {
        $checks[] = array(
            'id' => $id, 'category' => 'scenarios', 'status' => $status,
            'title' => $title, 'evidence' => $evidence, 'meaning' => $meaning,
            'recommendation' => $recommendation, 'confidence' => $confidence,
        );
    }

    public static function safe_id( $raw ) {
        if ( ! is_string( $raw ) && ! is_int( $raw ) ) { return ''; }
        $v = strtolower( (string) $raw );
        return substr( preg_replace( '/[^a-z0-9_:\/-]/', '', $v ), 0, 64 );
    }

    public static function normalize_methods( $raw ) {
        if ( ! is_array( $raw ) ) { return array(); }
        $result = array();
        foreach ( array_slice( $raw, 0, 100 ) as $method ) {
            $id = self::safe_id( $method );
            if ( '' !== $id ) { $result[ $id ] = true; }
        }
        return array_keys( $result );
    }

    /**
     * Pure configuration-only hints.  Never reports that a gateway will actually work.
     * Scenario identifiers are controlled and contain no cart, customer or address data.
     */
    public static function cod_scenarios( $enable_for_virtual, $restricted_methods ) {
        $virtual_ok = ( 'yes' === $enable_for_virtual );
        $restrictions = self::normalize_methods( $restricted_methods );
        $restricted = ! empty( $restrictions );
        $scenarios = array(
            array( 'id' => 'virtual', 'name' => 'Koszyk wyłącznie cyfrowy', 'shipping' => '' ),
            array( 'id' => 'flat_rate', 'name' => 'Towar fizyczny · dostawa standardowa', 'shipping' => 'flat_rate' ),
            array( 'id' => 'local_pickup', 'name' => 'Towar fizyczny · odbiór osobisty', 'shipping' => 'local_pickup' ),
        );
        $out = array();
        foreach ( $scenarios as $scenario ) {
            $result = 'dynamic';
            $reason = 'Dostępność zależy od koszyka, klienta, filtrów i ewentualnych integracji JavaScript.';
            if ( 'virtual' === $scenario['id'] ) {
                if ( ! $virtual_ok ) {
                    $result = 'config_restriction';
                    $reason = 'W opcji COD wyłączono obsługę zamówień wyłącznie wirtualnych.';
                } else {
                    $reason = 'COD ma włączoną obsługę wirtualnych koszyków; nadal wymagany jest test realnej dostępności.';
                }
            } elseif ( $restricted ) {
                $matches = in_array( $scenario['shipping'], $restrictions, true );
                foreach ( $restrictions as $value ) {
                    if ( 0 === strpos( $value, $scenario['shipping'] . ':' ) ) { $matches = true; }
                }
                $result = $matches ? 'config_condition' : 'config_restriction';
                $reason = $matches
                    ? 'Metoda mieści się w konfiguracji ograniczeń COD. Konkretna instancja dostawy i warunki koszyka wymagają testu.'
                    : 'Nie znaleziono tej metody w liście dozwolonych metod COD. Może to ograniczać pobranie dla tej dostawy.';
            } else {
                $reason = 'Brak listy ograniczeń COD do metod wysyłki; pozostałe warunki nie zostały sprawdzone.';
            }
            $out[] = array( 'scenario' => $scenario['id'], 'name' => $scenario['name'], 'gateway' => 'cod',
                'result' => $result, 'reason' => $reason );
        }
        return $out;
    }

    public static function free_shipping_rule( $requires, $min_amount, $coupons_enabled ) {
        $allowed = array( '' => 'Brak warunku wstępnego', 'coupon' => 'Wymagany poprawny kupon',
            'min_amount' => 'Wymagana kwota minimalna', 'either' => 'Kupon LUB kwota minimalna',
            'both' => 'Kupon ORAZ kwota minimalna' );
        $requires = is_string( $requires ) ? $requires : '';
        $known = array_key_exists( $requires, $allowed );
        $coupon_needed = in_array( $requires, array( 'coupon', 'both' ), true );
        $coupon_used = in_array( $requires, array( 'coupon', 'both', 'either' ), true );
        // Amounts are store configuration (not customer/order values); emit only presence.
        $amount_configured = is_scalar( $min_amount ) && is_numeric( $min_amount ) && (float) $min_amount >= 0;
        return array(
            'condition' => $known ? $requires : 'unknown',
            'description' => $known ? $allowed[ $requires ] : 'Nierozpoznany warunek rozszerzenia',
            'has_minimum' => in_array( $requires, array( 'min_amount', 'either', 'both' ), true ),
            'minimum_configured' => $amount_configured,
            'coupon_conflict' => $coupon_needed && ! $coupons_enabled,
            'coupon_used' => $coupon_used,
            'status' => $known ? 'known_rule' : 'dynamic',
        );
    }

    public static function run( &$meta ) {
        $checks = array();
        $inventory = array( 'gateways' => array(), 'scenarios' => array(), 'shipping_rules' => array(),
            'status' => 'not_checked', 'note' => 'Statyczne wskazówki konfiguracyjne. Nie wykonano żadnej symulacji zamówienia.' );
        $meta['scenario_audit'] = $inventory;
        if ( ! function_exists( 'WC' ) ) {
            self::item( $checks, 'scenario_api', 'info', 'Analiza scenariuszy niedostępna',
                'Brak funkcji WC().', 'Nie oceniono ustawień bramek.', 'Włącz WooCommerce i ponów skan.' );
            return $checks;
        }
        try {
            $wc = WC();
            $manager = is_object( $wc ) && method_exists( $wc, 'payment_gateways' ) ? $wc->payment_gateways() : null;
            if ( ! is_object( $manager ) || ! method_exists( $manager, 'payment_gateways' ) ) { throw new RuntimeException( 'Manager missing' ); }
            $all = $manager->payment_gateways();
            if ( ! is_array( $all ) ) { throw new RuntimeException( 'Manager result invalid' ); }
            $seen = array();
            foreach ( $all as $gateway ) {
                if ( ! is_object( $gateway ) || ! isset( $gateway->id, $gateway->enabled ) || 'yes' !== $gateway->enabled ) { continue; }
                $id = self::safe_id( $gateway->id );
                if ( '' === $id || isset( $seen[$id] ) ) { continue; }
                $seen[$id] = true;
                if ( count( $inventory['gateways'] ) >= 30 ) { break; }
                $inventory['gateways'][] = array( 'id' => $id, 'status' => 'enabled', 'availability' => 'not_evaluated' );
                if ( 'cod' === $id ) {
                    try {
                        $virtual = method_exists( $gateway, 'get_option' ) ? $gateway->get_option( 'enable_for_virtual', 'yes' ) : 'unknown';
                        $restricted = method_exists( $gateway, 'get_option' ) ? $gateway->get_option( 'enable_for_methods', array() ) : array();
                        if ( ! in_array( $virtual, array( 'yes', 'no' ), true ) ) { $virtual = 'unknown'; }
                        if ( 'unknown' !== $virtual ) {
                            $inventory['scenarios'] = array_merge( $inventory['scenarios'], self::cod_scenarios( $virtual, $restricted ) );
                        } else {
                            self::item( $checks, 'scenario_cod_unknown', 'info', 'Nieznane ustawienie COD dla produktów cyfrowych',
                                'Nie można jednoznacznie odczytać opcji enable_for_virtual.',
                                'Nie oceniono tego wariantu.', 'Sprawdź ustawienie COD w WooCommerce → Płatności.' );
                        }
                    } catch ( Throwable $e ) {
                        self::item( $checks, 'scenario_cod_unavailable', 'info', 'Nie odczytano warunków pobrania',
                            'Wystąpił wyjątek przy odczycie opcji COD.', 'Nie ustalono ograniczeń.',
                            'Sprawdź konfigurację pobrania w panelu sklepu.' );
                    }
                }
            }
            if ( ! $inventory['gateways'] ) {
                self::item( $checks, 'scenario_no_gateways', 'info', 'Brak włączonych bramek w rejestrze',
                    'Liczba włączonych bramek w API: 0.', 'Testy warunków płatności nie mają danych wejściowych.',
                    'Sprawdź WooCommerce → Ustawienia → Płatności.' );
            } else {
                self::item( $checks, 'scenario_gateway_scope', 'info', 'Dostępność płatności zależna od koszyka',
                    'Odczytano ' . count( $inventory['gateways'] ) . ' włączonych identyfikatorów; warunki COD: ' . count( $inventory['scenarios'] ) . '.',
                    'Nie wywołano is_available ani canMakePayment; nie wiemy, jakie metody zobaczy kupujący.',
                    'Porównaj scenariusze na stagingu, osobno dla produktów cyfrowych i fizycznych.', 'confirmed' );
            }
            $inventory['status'] = 'read';
        } catch ( Throwable $e ) {
            $inventory['status'] = 'unavailable';
            self::item( $checks, 'scenario_gateway_error', 'info', 'Rejestr bramek nie został odczytany',
                'Odczyt konfiguracji zakończył się niepowodzeniem.', 'Wynik nie mówi nic o dostępności bramek.',
                'Sprawdź WooCommerce i lokalne logi PHP.' );
        }

        // Free shipping: collect static rule types only, never calculate rates or modify a cart.
        if ( class_exists( 'WC_Shipping_Zones' ) && method_exists( 'WC_Shipping_Zones', 'get_zones' ) && class_exists( 'WC_Shipping_Zone' ) ) {
            try {
                $zones = WC_Shipping_Zones::get_zones();
                if ( ! is_array( $zones ) ) { throw new RuntimeException( 'Zones invalid' ); }
                $zone_ids = array( 0 );
                foreach ( $zones as $key => $zone ) {
                    $zone_id = is_array( $zone ) && isset( $zone['zone_id'] ) ? (int) $zone['zone_id'] : ( is_numeric( $key ) ? (int) $key : -1 );
                    if ( $zone_id > 0 ) { $zone_ids[] = $zone_id; }
                }
                $zone_ids = array_slice( array_unique( $zone_ids ), 0, 30 );
                $coupon_setting = function_exists( 'get_option' ) ? get_option( 'woocommerce_enable_coupons', 'yes' ) : 'yes';
                foreach ( $zone_ids as $zone_id ) {
                    $zone = new WC_Shipping_Zone( $zone_id );
                    if ( ! method_exists( $zone, 'get_shipping_methods' ) ) { continue; }
                    foreach ( $zone->get_shipping_methods( true ) as $method ) {
                        if ( ! is_object( $method ) || ! isset( $method->id ) || 'free_shipping' !== (string) $method->id ) { continue; }
                        if ( count( $inventory['shipping_rules'] ) >= 30 ) { break 2; }
                        $requires = method_exists( $method, 'get_option' ) ? $method->get_option( 'requires', '' ) : '';
                        $min = method_exists( $method, 'get_option' ) ? $method->get_option( 'min_amount', '' ) : '';
                        $rule = self::free_shipping_rule( $requires, $min, 'yes' === $coupon_setting );
                        $inventory['shipping_rules'][] = array( 'zone_id' => $zone_id, 'method' => 'free_shipping',
                            'instance_id' => isset( $method->instance_id ) ? (int) $method->instance_id : 0,
                            'condition' => $rule['condition'], 'description' => $rule['description'],
                            'status' => $rule['status'], 'coupon_conflict' => $rule['coupon_conflict'] );
                        if ( $rule['coupon_conflict'] ) {
                            self::item( $checks, 'scenario_coupon_zone_' . $zone_id . '_' . count( $inventory['shipping_rules'] ),
                                'warning', 'Kupony wymagane, lecz wyłączone',
                                'Strefa ' . $zone_id . ' · darmowa wysyłka: ' . $rule['description'] . '; kupony sklepu: wyłączone.',
                                'Dla tej reguły kuponowej bez dodatkowej konfiguracji warunek nie zostanie spełniony.',
                                'Sprawdź konfigurację kuponów i tej instancji darmowej dostawy.', 'confirmed' );
                        }
                    }
                }
            } catch ( Throwable $e ) {
                self::item( $checks, 'scenario_shipping_unavailable', 'info', 'Nie ukończono analizy warunków darmowej wysyłki',
                    'API stref lub metod dostawy nie zwróciło pełnych danych.',
                    'Nie oznacza to błędu w obsłudze dostaw.', 'Sprawdź strefy i metody na stagingu.' );
            }
        }
        self::item( $checks, 'scenario_runtime_not_tested', 'info', 'Test koszyka w przeglądarce nadal wymagany',
            'Oceniono tylko statyczne ustawienia płatności i darmowej wysyłki.',
            'Bramki mogą stosować dodatkowe filtry PHP i canMakePayment JavaScript, zależne od adresu, kuponu, koszyka i waluty.',
            'Na stagingu przetestuj wirtualny produkt, dostawę standardową, odbiór, kupon i różne adresy; płatności tylko w sandboxie.' );
        $meta['scenario_audit'] = $inventory;
        return $checks;
    }
}
