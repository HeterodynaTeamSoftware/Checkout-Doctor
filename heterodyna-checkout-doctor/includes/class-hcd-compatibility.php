<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ZIP 11: read-only compatibility inventory; API presence is not proof of interoperability.
 * Never runs WC gateway availability callbacks, creates carts/orders, or sends HTTP requests.
 */
final class HCD_Compatibility {
    const MIN_WP = '6.2';
    const MIN_PHP = '7.4';

    private static function add( &$checks, $id, $status, $title, $evidence, $meaning, $recommendation, $confidence = 'confirmed' ) {
        $checks[] = compact( 'id', 'status', 'title', 'evidence', 'meaning', 'recommendation', 'confidence' )
            + array( 'category' => 'compatibility' );
    }

    /** The version must be a numeric version, otherwise no comparison is inferred. */
    public static function classify_version( $current, $minimum ) {
        if ( ! is_string( $current ) || ! preg_match( '/^[0-9]+(?:\.[0-9]+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/D', $current ) ) {
            return 'unknown';
        }
        return version_compare( $current, $minimum, '>=' ) ? 'supported' : 'below';
    }

    public static function run( &$meta ) {
        $checks = array();
        $wp = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : '';
        $wc = defined( 'WC_VERSION' ) ? (string) WC_VERSION : '';
        $woo_enabled = class_exists( 'WooCommerce' );
        $wp_class = self::classify_version( $wp, self::MIN_WP );
        $php_class = self::classify_version( PHP_VERSION, self::MIN_PHP );
        $features = array(
            'wc_get_orders' => function_exists( 'wc_get_orders' ),
            'wc_get_page_id' => function_exists( 'wc_get_page_id' ),
            'wc_container' => function_exists( 'wc_get_container' ),
            'wc_blocks_registry' => class_exists( '\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry' ),
            'action_scheduler' => function_exists( 'as_schedule_recurring_action' ),
            'wp_rest_server' => function_exists( 'rest_get_server' ),
        );
        $environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'unknown';
        if ( ! in_array( $environment, array( 'production', 'staging', 'development', 'local' ), true ) ) { $environment = 'unknown'; }
        $meta['compatibility'] = array(
            'wordpress' => $wp !== '' ? $wp : 'unknown',
            'php' => PHP_VERSION,
            'woocommerce' => $wc !== '' ? $wc : 'unknown',
            'environment' => $environment,
            'features' => $features,
            'scope' => 'local_api_inventory_not_integration_test',
        );
        self::add( $checks, 'compat_wp', 'below' === $wp_class ? 'warning' : ( 'supported' === $wp_class ? 'pass' : 'info' ),
            'Wersja WordPress',
            'WordPress: ' . ( $wp !== '' ? $wp : 'nieodczytany' ) . '; wymaganie nagłówka: ' . self::MIN_WP . '+.',
            'To porównanie minimalnej wersji PHP/WP deklarowanej przez wtyczkę, a nie test całej zgodności sklepu.',
            'below' === $wp_class ? 'Zaktualizuj WordPress na stagingu przed aktualizacją produkcji.' : 'Przetestuj pełny checkout po zmianach wersji.',
            'unknown' === $wp_class ? 'signal' : 'confirmed' );
        self::add( $checks, 'compat_php', 'below' === $php_class ? 'warning' : ( 'supported' === $php_class ? 'pass' : 'info' ),
            'Wersja PHP', 'PHP: ' . PHP_VERSION . '; minimalna deklarowana: ' . self::MIN_PHP . '+.',
            'Zgodność wersji nie potwierdza zgodności wszystkich aktywnych rozszerzeń.',
            'below' === $php_class ? 'Wykonaj aktualizację PHP na stagingu z testami sklepu.' : 'Przeprowadź testy checkoutu z aktywnymi rozszerzeniami.',
            'unknown' === $php_class ? 'signal' : 'confirmed' );
        if ( ! $woo_enabled ) {
            self::add( $checks, 'compat_wc', 'info', 'Interfejs WooCommerce nieaktywny',
                'Klasa WooCommerce nie jest dostępna.',
                'Brak odczytu wersji i API WooCommerce. Skan główny zgłasza to oddzielnie.',
                'Włącz WooCommerce, potem uruchom diagnostykę.', 'signal' );
            return $checks;
        }
        self::add( $checks, 'compat_wc', 'info', 'Wersja WooCommerce — informacja',
            'WC_VERSION: ' . ( $wc !== '' ? $wc : 'nieodczytany' ) . '.',
            'Sama wersja WooCommerce nie jest dowodem zgodności bramek, motywu i Checkout Block.',
            'Przetestuj checkout klasyczny i blokowy, z aktywnymi bramkami i HPOS na stagingu.', 'signal' );
        $missing = array();
        foreach ( array( 'wc_get_orders', 'wc_get_page_id' ) as $name ) {
            if ( ! $features[$name] ) { $missing[] = $name; }
        }
        self::add( $checks, 'compat_core_apis', $missing ? 'warning' : 'pass', 'Dostępność podstawowych API WooCommerce',
            $missing ? 'Brak w bieżącym przebiegu: ' . implode( ', ', $missing ) . '.' : 'wc_get_orders i wc_get_page_id są dostępne.',
            'Funkcje są wymagane do lokalnej diagnostyki; ich obecność nie testuje poprawności wyników.',
            $missing ? 'Sprawdź aktywację WooCommerce i błędy inicjalizacji; wykonaj test na stagingu.' : 'Testuj odczyt statusów i stron w rzeczywistym sklepie.',
            $missing ? 'signal' : 'confirmed' );
        self::add( $checks, 'compat_block_api', 'info', 'Integracje blokowe — stan API',
            'Rejestr PHP metod płatności: ' . ( $features['wc_blocks_registry'] ? 'dostępny' : 'niepotwierdzony w tym przebiegu' ) .
            '; REST WordPress: ' . ( $features['wp_rest_server'] ? 'dostępne' : 'niepotwierdzone' ) . '.',
            'To wyłącznie obecność interfejsów w chwili skanu, nie test obsługi Checkout Block ani canMakePayment.',
            'Sprawdź wygląd checkoutu na stagingu i wykonaj zamówienie wyłącznie testowo w sandboxie.', 'signal' );
        self::add( $checks, 'compat_scheduler', 'info', 'Action Scheduler — obecność interfejsu',
            'Funkcje Action Scheduler: ' . ( $features['action_scheduler'] ? 'dostępne' : 'niepotwierdzone' ) . '.',
            'Obecność API nie potwierdza, że kolejka zadań działa i webhooki są przetwarzane.',
            'Sprawdź w WooCommerce → Status → Zaplanowane akcje zaległe lub nieudane zadania.', 'signal' );
        return $checks;
    }
}
