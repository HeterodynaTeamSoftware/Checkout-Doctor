<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Checkout Block integration inventory. Read-only: no requests, sessions, callbacks,
 * order/customer/field values or gateway credentials are inspected.
 */
final class HCD_Block_Compat {
    private static function item( &$out, $id, $status, $title, $evidence, $meaning, $recommendation, $confidence = 'signal' ) {
        $out[] = array(
            'id' => $id, 'category' => 'block_compat', 'status' => $status,
            'title' => $title, 'evidence' => $evidence, 'meaning' => $meaning,
            'recommendation' => $recommendation, 'confidence' => $confidence,
        );
    }

    /** Only expose normalized technical identifiers, never labels/settings/field contents. */
    public static function safe_id( $value ) {
        if ( ! is_string( $value ) && ! is_int( $value ) ) { return ''; }
        $value = strtolower( (string) $value );
        $value = preg_replace( '/[^a-z0-9_\/-]/', '', $value );
        return substr( (string) $value, 0, 80 );
    }

    public static function summarize_fields( $by_location ) {
        $summary = array( 'contact' => 0, 'address' => 0, 'order' => 0, 'total' => 0, 'required' => 0, 'conditional_required' => 0, 'types' => array(), 'samples' => array() );
        foreach ( array( 'contact', 'address', 'order' ) as $location ) {
            $definitions = isset( $by_location[$location] ) && is_array( $by_location[$location] ) ? $by_location[$location] : array();
            $summary[$location] = count( $definitions );
            $summary['total'] += count( $definitions );
            foreach ( $definitions as $id => $definition ) {
                if ( ! is_array( $definition ) ) { continue; }
                $type = isset( $definition['type'] ) ? self::safe_id( $definition['type'] ) : 'text';
                if ( '' === $type ) { $type = 'unknown'; }
                if ( ! isset( $summary['types'][$type] ) ) { $summary['types'][$type] = 0; }
                $summary['types'][$type]++;
                $required = isset( $definition['required'] ) ? $definition['required'] : false;
                if ( true === $required ) { $summary['required']++; }
                if ( is_array( $required ) ) { $summary['conditional_required']++; }
                if ( count( $summary['samples'] ) < 30 ) {
                    $summary['samples'][] = array(
                        'id' => self::safe_id( $id ), 'location' => $location,
                        'type' => $type, 'required' => is_array( $required ) ? 'conditional' : ( true === $required ? 'yes' : 'no' ),
                    );
                }
            }
        }
        ksort( $summary['types'] );
        return $summary;
    }

    /** Returns facts about static payment registries, not the set visible to a shopper. */
    public static function compare_gateways( $gateway_ids, $registered_ids ) {
        $gateways = array();
        foreach ( (array) $gateway_ids as $id ) { $id = self::safe_id( $id ); if ( '' !== $id ) { $gateways[$id] = true; } }
        $registered = array();
        foreach ( (array) $registered_ids as $id ) { $id = self::safe_id( $id ); if ( '' !== $id ) { $registered[$id] = true; } }
        $not_matched = array_diff( array_keys( $gateways ), array_keys( $registered ) );
        sort( $not_matched );
        return array( 'enabled' => count( $gateways ), 'registered' => count( $registered ), 'name_matches' => count( $gateways ) - count( $not_matched ), 'unmatched_ids' => array_slice( $not_matched, 0, 30 ), 'unmatched_total' => count( $not_matched ) );
    }

    public static function run( &$meta ) {
        $out = array();
        $mode = isset( $meta['checkout_mode'] ) ? $meta['checkout_mode'] : 'unknown';
        $meta['block_compat'] = array( 'checkout_mode' => $mode, 'field_status' => 'not_checked', 'payment_status' => 'not_checked' );
        if ( 'blocks' !== $mode ) {
            self::item( $out, 'block_compat_mode', 'info', 'Zgodność Checkout Block nie jest oceniana',
                'Wykryty tryb strony: ' . self::safe_id( $mode ) . '.',
                'Testy rejestrów blokowych mają znaczenie przede wszystkim dla Checkout Block.',
                'Przy checkout niestandardowym sprawdź ręcznie, jaki mechanizm renderuje zamówienia.' );
            return $out;
        }
        self::item( $out, 'block_compat_mode', 'pass', 'Checkout Block — analiza integracji włączona',
            'W przypisanej stronie znaleziono blok woocommerce/checkout.',
            'Weryfikowane są rejestry po stronie PHP. Nie jest wykonywany test bramek z aktywnym koszykiem.',
            'Aby potwierdzić działanie bramek i pól, wykonaj osobny test w przeglądarce na stagingu.', 'confirmed' );

        // Public WooCommerce Blocks registry APIs are version-dependent; handle absence gracefully.
        $registry_class = '\\Automattic\\WooCommerce\\Blocks\\Payments\\PaymentMethodRegistry';
        $package_class  = '\\Automattic\\WooCommerce\\Blocks\\Package';
        if ( class_exists( $registry_class ) && class_exists( $package_class ) && method_exists( $package_class, 'container' ) ) {
            try {
                $registry = $package_class::container()->get( $registry_class );
                if ( ! is_object( $registry ) || ! method_exists( $registry, 'get_all_registered' ) ) { throw new RuntimeException( 'Registry unavailable' ); }
                $integrations = $registry->get_all_registered();
                if ( ! is_array( $integrations ) ) { throw new RuntimeException( 'Invalid registry data' ); }
                $registered = array_keys( $integrations );
                $gateway_ids = array();
                if ( function_exists( 'WC' ) ) {
                    $wc = WC();
                    if ( is_object( $wc ) && method_exists( $wc, 'payment_gateways' ) ) {
                        $manager = $wc->payment_gateways();
                        if ( is_object( $manager ) && method_exists( $manager, 'payment_gateways' ) ) {
                            foreach ( (array) $manager->payment_gateways() as $gateway ) {
                                if ( is_object( $gateway ) && isset( $gateway->id ) && isset( $gateway->enabled ) && 'yes' === $gateway->enabled ) { $gateway_ids[] = $gateway->id; }
                            }
                        }
                    }
                }
                $comparison = self::compare_gateways( $gateway_ids, $registered );
                $meta['block_compat']['payment_status'] = 'registry_read';
                $meta['block_compat']['gateways'] = $comparison;
                self::item( $out, 'block_compat_gateways', 'info', 'Integracje płatności Checkout Block — rejestr PHP',
                    'Włączone bramki WooCommerce: ' . $comparison['enabled'] . '; zarejestrowane integracje PHP: ' . $comparison['registered'] . '; zgodnych identyfikatorów: ' . $comparison['name_matches'] . '; bez dopasowania identyfikatora: ' . $comparison['unmatched_total'] . '.',
                    'Brak zgodnej nazwy NIE dowodzi braku kompatybilności. Integracje mogą używać odrębnych nazw oraz warunkowej dostępności. PHP nie potwierdza rejestracji JS ani tego, co widzi kupujący.',
                    'Otwórz checkout jako testowy klient, sprawdź widoczne metody i ich zachowanie; porównaj dokumentację poszczególnych bramek.' );
                if ( $comparison['unmatched_total'] ) {
                    self::item( $out, 'block_compat_gateway_names', 'warning', 'Bramki do ręcznego sprawdzenia w Checkout Block',
                        'Niepowiązane po identyfikatorze: ' . implode( ', ', $comparison['unmatched_ids'] ) . '.',
                        'To nie są potwierdzone konflikty — niektóre integracje wystawiają własne nazwy rejestrów lub obsługują metody express.',
                        'Na stagingu sprawdź widoczność każdej bramki na blokowym checkout oraz oficjalną deklarację zgodności.', 'signal' );
                }
            } catch ( Throwable $e ) {
                $meta['block_compat']['payment_status'] = 'unavailable';
                self::item( $out, 'block_compat_gateways', 'info', 'Rejestr metod płatności niedostępny',
                    'Nie udało się odczytać rejestru PHP integracji płatności.',
                    'Brak odczytu nie oznacza braku kompatybilności metod.',
                    'Zaktualizuj WooCommerce i sprawdź zgodność w przeglądarce lub oficjalnej dokumentacji bramki.' );
            }
        } else {
            $meta['block_compat']['payment_status'] = 'api_missing';
            self::item( $out, 'block_compat_gateways', 'info', 'Brak API rejestru integracji płatności',
                'PaymentMethodRegistry lub Blocks Package nie jest dostępne podczas diagnostyki.',
                'Ta wersja lub konfiguracja WooCommerce nie pozwala na bezpieczny odczyt rejestru.',
                'Sprawdź wersję WooCommerce i ręcznie zweryfikuj metody płatności w Checkout Block.' );
        }

        $fields_class = '\\Automattic\\WooCommerce\\Blocks\\Domain\\Services\\CheckoutFields';
        if ( class_exists( $fields_class ) && class_exists( $package_class ) && method_exists( $package_class, 'container' ) ) {
            try {
                $service = $package_class::container()->get( $fields_class );
                if ( ! is_object( $service ) || ! method_exists( $service, 'get_fields_for_location' ) ) { throw new RuntimeException( 'Fields unavailable' ); }
                $locations = array();
                foreach ( array( 'contact', 'address', 'order' ) as $location ) { $locations[$location] = $service->get_fields_for_location( $location ); }
                $fields = self::summarize_fields( $locations );
                $meta['block_compat']['field_status'] = 'registry_read';
                $meta['block_compat']['fields'] = $fields;
                self::item( $out, 'block_compat_fields', 'info', 'Dodatkowe pola Checkout Block — rejestr',
                    'Łącznie: ' . $fields['total'] . '; kontakt: ' . $fields['contact'] . '; adres: ' . $fields['address'] . '; zamówienie: ' . $fields['order'] . '; wymagane bezwarunkowo: ' . $fields['required'] . '; wymagane warunkowo: ' . $fields['conditional_required'] . '.',
                    'Odczytano wyłącznie definicje pól. Warunki widoczności i obowiązkowości zależne od kontekstu zamówienia nie są wykonywane.',
                    'Sprawdź na stagingu formularz dla różnych adresów, koszyków i użytkowników. Nie są odczytywane wartości pól klientów.' );
                $unusual_types = array_diff( array_keys( $fields['types'] ), array( 'text', 'select', 'checkbox', 'date' ) );
                if ( count( $unusual_types ) ) {
                    self::item( $out, 'block_compat_field_types', 'warning', 'Niestandardowe typy pól wymagają weryfikacji',
                        'Typy poza listą bazową API: ' . implode( ', ', array_slice( $unusual_types, 0, 10 ) ) . '.',
                        'Typy obsługiwane przez dodatkowe rozszerzenia mogą być poprawne, lecz skaner nie sprawdza ich renderowania.',
                        'Sprawdź rejestrację zgodną z API WooCommerce i działanie pola w Checkout Block.' );
                }
            } catch ( Throwable $e ) {
                $meta['block_compat']['field_status'] = 'unavailable';
                self::item( $out, 'block_compat_fields', 'info', 'Nie odczytano dodatkowych pól Checkout Block',
                    'Rejestr CheckoutFields nie zwrócił listy pól.',
                    'Brak odczytu nie oznacza, że pola nie istnieją.',
                    'Sprawdź aktywność modułu Blocks i wersję WooCommerce; w razie potrzeby skontroluj pola ręcznie.' );
            }
        } else {
            $meta['block_compat']['field_status'] = 'api_missing';
            self::item( $out, 'block_compat_fields', 'info', 'API dodatkowych pól jest niedostępne',
                'Nie znaleziono CheckoutFields ani kontenera WooCommerce Blocks.',
                'Skan nie ocenił pól; nie jest to dowód ich błędnej konfiguracji.',
                'Zalecana aktualizacja WooCommerce i weryfikacja pól na stagingu.' );
        }
        return $out;
    }
}
