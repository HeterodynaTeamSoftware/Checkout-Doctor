<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ZIP 4/5: bounded, read-only inventory of theme template overrides, runtime
 * checkout hooks and locally stored WooCommerce log severity indicators.
 * NEVER returns raw log lines, file paths, customer data or exception messages.
 */
final class HCD_Conflicts_Logs {
    private static function item( &$items, $id, $category, $severity, $title, $evidence, $meaning, $recommendation, $confidence = 'signal' ) {
        $items[] = array( 'id' => $id, 'category' => $category, 'status' => $severity,
            'title' => $title, 'evidence' => $evidence, 'meaning' => $meaning,
            'recommendation' => $recommendation, 'confidence' => $confidence );
    }

    public static function run( &$meta ) {
        $items = array();
        self::theme_templates( $items, $meta );
        self::checkout_hooks( $items, $meta );
        self::log_indicators( $items, $meta );
        return $items;
    }

    /** Only template identifiers, not full filesystem paths, enter the report. */
    private static function theme_templates( &$items, &$meta ) {
        $targets = array( 'checkout/form-checkout.php', 'checkout/payment.php',
            'checkout/review-order.php', 'checkout/form-billing.php', 'checkout/form-shipping.php',
            'checkout/form-coupon.php', 'checkout/terms.php', 'checkout/thankyou.php',
            'checkout/order-receipt.php', 'checkout/payment-method.php', 'cart/cart.php',
            'cart/cart-totals.php', 'cart/shipping-calculator.php' );
        $theme_roots = array();
        if ( function_exists( 'get_stylesheet_directory' ) ) { $theme_roots['motyw potomny / aktywny'] = get_stylesheet_directory(); }
        if ( function_exists( 'get_template_directory' ) ) { $theme_roots['motyw nadrzędny'] = get_template_directory(); }
        $theme_roots = array_unique( array_filter( $theme_roots ) );
        $wc_path = '';
        if ( function_exists( 'WC' ) ) {
            try {
                $wc = WC();
                if ( is_object( $wc ) && method_exists( $wc, 'plugin_path' ) ) { $wc_path = $wc->plugin_path(); }
            } catch ( Throwable $e ) { /* Never return exception contents. */ }
        }
        if ( ! $theme_roots ) {
            $meta['theme_overrides'] = array();
            $meta['theme_override_count'] = 0;
            $meta['theme_override_older_count'] = 0;
            self::item( $items, 'conflicts_template_versions', 'conflicts', 'info',
                'Nie można ustalić ścieżek motywu', 'Brak katalogu aktywnego lub nadrzędnego motywu w kontekście skanu.',
                'Nie przeprowadzono kontroli plików nadpisań.', 'Uruchom skan w aktywnej instalacji WordPress.', 'signal' );
            return;
        }
        $details = array();
        $outdated = array();
        foreach ( $theme_roots as $label => $root ) {
            $root = rtrim( (string) $root, '/\\' );
            foreach ( $targets as $target ) {
                $custom = $root . '/woocommerce/' . $target;
                if ( ! is_file( $custom ) || ! is_readable( $custom ) ) { continue; }
                $original = $wc_path ? rtrim( $wc_path, '/\\' ) . '/templates/' . $target : '';
                $installed = self::template_version( $custom );
                $upstream = $original && is_file( $original ) ? self::template_version( $original ) : '';
                $state = 'nieustalona';
                if ( $installed && $upstream ) {
                    $state = version_compare( $installed, $upstream, '<' ) ? 'starsza' :
                        ( version_compare( $installed, $upstream, '>' ) ? 'nowsza' : 'zgodna' );
                }
                $record = array( 'location' => $label, 'template' => $target,
                    'theme_version' => $installed ?: 'brak', 'woocommerce_version' => $upstream ?: 'brak',
                    'version_relation' => $state );
                $details[] = $record;
                if ( 'starsza' === $state ) { $outdated[] = $record; }
            }
        }
        $meta['theme_overrides'] = array_slice( $details, 0, 26 );
        $meta['theme_override_count'] = count( $details );
        $meta['theme_override_older_count'] = count( $outdated );
        if ( $outdated ) {
            $labels = array();
            foreach ( array_slice( $outdated, 0, 5 ) as $record ) {
                $labels[] = $record['location'] . ': ' . $record['template'] . ' @version ' .
                    $record['theme_version'] . ' < ' . $record['woocommerce_version'];
            }
            self::item( $items, 'conflicts_template_versions', 'conflicts', 'warning',
                'Starsze wersje szablonów w motywie', implode( '; ', $labels ) .
                ( count( $outdated ) > 5 ? '; i kolejne: ' . ( count( $outdated ) - 5 ) : '' ),
                'Nagłówek @version w nadpisaniu jest starszy od szablonu WooCommerce. To potwierdzona różnica wersji, a nie dowód awarii.',
                'Porównaj wskazane pliki z bieżącymi szablonami WooCommerce, sprawdź zmiany i przetestuj checkout na stagingu.', 'confirmed' );
        } elseif ( $details ) {
            self::item( $items, 'conflicts_template_versions', 'conflicts', 'info',
                'Znaleziono nadpisania szablonów WooCommerce', 'Liczba sprawdzonych nadpisań: ' . count( $details ) .
                    '. Starsze według @version: 0. Brak nagłówka nie oznacza zgodności.',
                'Nadpisanie szablonu jest dozwolone. Porównanie wersji nie testuje jego wykonania.',
                'Jeśli checkout sprawia problemy, porównaj szablony z aktualnym WooCommerce i użyj stagingu.', 'signal' );
        } else {
            self::item( $items, 'conflicts_template_versions', 'conflicts', 'pass',
                'Nie znaleziono typowych nadpisań checkout / cart',
                'Sprawdzono ' . count( $targets ) . ' standardowych ścieżek szablonów dla aktywnego motywu.',
                'To nie wyklucza nadpisań przez hooki, kreatory stron ani niestandardowe szablony.',
                'Brak wymaganej zmiany; jeśli są objawy awarii, sprawdź pozostałe ustalenia.', 'confirmed' );
        }
    }

    /** Read first 4KB only: WC template @version metadata sits in the header. */
    private static function template_version( $path ) {
        $contents = @file_get_contents( $path, false, null, 0, 4096 );
        if ( ! is_string( $contents ) ) { return ''; }
        return preg_match( '/@version\s+([0-9]+(?:\.[0-9]+){1,3})\b/i', $contents, $match ) ? $match[1] : '';
    }

    private static function callback_file( $callback ) {
        try {
            if ( $callback instanceof Closure ) { return (new ReflectionFunction( $callback ))->getFileName(); }
            if ( is_array( $callback ) && count( $callback ) === 2 ) {
                return (new ReflectionMethod( $callback[0], $callback[1] ))->getFileName();
            }
            if ( is_string( $callback ) && function_exists( $callback ) ) {
                return (new ReflectionFunction( $callback ))->getFileName();
            }
            if ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
                return (new ReflectionMethod( $callback, '__invoke' ))->getFileName();
            }
        } catch ( Throwable $e ) { /* Reflection may fail for third-party callbacks. */ }
        return '';
    }

    public static function owner_for_file( $file, $plugin_dir ) {
        $file = str_replace( '\\', '/', (string) $file );
        $root = rtrim( str_replace( '\\', '/', (string) $plugin_dir ), '/' ) . '/';
        if ( ! $file || '/' === $root || 0 !== strpos( $file, $root ) ) { return ''; }
        $relative = substr( $file, strlen( $root ) );
        $pieces = explode( '/', $relative );
        // Single-file plugin identifiers are permitted. No absolute paths are emitted.
        $slug = count( $pieces ) > 1 ? $pieces[0] : preg_replace( '/\.php$/i', '', $pieces[0] );
        return preg_match( '/^[a-zA-Z0-9_-]{1,80}$/', $slug ) ? strtolower( $slug ) : '';
    }

    private static function checkout_hooks( &$items, &$meta ) {
        global $wp_filter;
        $hooks = array( 'woocommerce_checkout_process', 'woocommerce_after_checkout_validation',
            'woocommerce_checkout_fields', 'woocommerce_available_payment_gateways',
            'woocommerce_package_rates', 'woocommerce_before_calculate_totals',
            'woocommerce_store_api_checkout_update_order_from_request',
            'woocommerce_checkout_create_order', 'woocommerce_checkout_order_processed' );
        $root = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : '';
        $hook_info = array();
        $multi = array();
        $hook_errors = 0;
        foreach ( $hooks as $hook ) {
            $owners = array();
            $total = 0;
            try {
                $registered = isset( $wp_filter[ $hook ] ) && is_object( $wp_filter[ $hook ] ) &&
                    isset( $wp_filter[ $hook ]->callbacks ) && is_array( $wp_filter[ $hook ]->callbacks ) ?
                    $wp_filter[ $hook ]->callbacks : array();
                foreach ( $registered as $priority => $bucket ) {
                    if ( ! is_array( $bucket ) ) { continue; }
                    foreach ( $bucket as $entry ) {
                        if ( ! is_array( $entry ) || ! isset( $entry['function'] ) ) { continue; }
                        $total++;
                        if ( $total > 200 ) { break 2; }
                        $owner = self::owner_for_file( self::callback_file( $entry['function'] ), $root );
                        if ( $owner && 'heterodyna-checkout-doctor' !== $owner ) { $owners[ $owner ] = true; }
                    }
                }
            } catch ( Throwable $e ) { $hook_errors++; }
            $names = array_keys( $owners );
            sort( $names, SORT_STRING );
            $hook_info[ $hook ] = array( 'callbacks_count' => $total, 'plugin_owners' => array_slice( $names, 0, 15 ) );
            if ( count( $names ) >= 2 ) { $multi[] = $hook . ' (' . implode( ', ', array_slice( $names, 0, 4 ) ) . ')'; }
        }
        $meta['hook_inspection'] = $hook_info;
        $meta['checkout_hooks_with_multiple_plugin_owners'] = count( $multi );
        if ( $multi ) {
            self::item( $items, 'conflicts_shared_hooks', 'conflicts', 'info',
                'Kilka rozszerzeń obsługuje te same hooki checkout',
                implode( '; ', array_slice( $multi, 0, 5 ) ) . ( count( $multi ) > 5 ? '; pozostałe: ' . ( count( $multi ) - 5 ) : '' ),
                'Współdzielenie hooka jest prawidłowym mechanizmem WordPress, a nie dowodem konfliktu. Identyfikujemy właścicieli kodu bez wywoływania callbacków.',
                'Jeśli checkout zgłasza błędy, na stagingu porównaj działanie z wyłączonymi podejrzanymi rozszerzeniami.', 'signal' );
        } else {
            self::item( $items, 'conflicts_shared_hooks', 'conflicts', 'info',
                'Inwentaryzacja hooków checkout', 'Przeanalizowano ' . count( $hooks ) .
                    ' hooków; nie ustalono współdzielonych przez kilka różnych wtyczek. Niezidentyfikowane callbacki nie są liczone jako właściciel.',
                'Nie dowodzi to braku konfliktów JavaScript, templatów ani kodu ładowanego w innych żądaniach.',
                'W przypadku problemu wykonaj test przepływu zakupowego na stagingu.', 'signal' );
        }
        if ( $hook_errors ) {
            self::item( $items, 'conflicts_hook_errors', 'conflicts', 'info', 'Niepełna inwentaryzacja hooków',
                'Liczba błędów odczytu callbacków: ' . $hook_errors . '.',
                'Nie wszystkie źródła hooków mogły zostać odczytane.', 'Zweryfikuj zgodność aktywnych rozszerzeń.', 'signal' );
        }
    }

    private static function get_log_directory() {
        // Current WC file logger directory; do not assume the legacy handler is active.
        $cls = '\\Automattic\\WooCommerce\\Utilities\\LoggingUtil';
        if ( class_exists( $cls ) && method_exists( $cls, 'get_log_directory' ) ) {
            try { return $cls::get_log_directory(); } catch ( Throwable $e ) { return ''; }
        }
        return defined( 'WC_LOG_DIR' ) ? WC_LOG_DIR : '';
    }

    public static function classify_line( $line ) {
        // Return only fixed classifications. Never persist or expose original log contents.
        if ( preg_match( '/PHP Fatal error|Uncaught (?:[\\\\A-Za-z_]+)?(?:Exception|Error)|\bCRITICAL\b|\bEMERGENCY\b|\bALERT\b/i', $line ) ) { return 'critical'; }
        if ( preg_match( '/PHP Warning|\bWARNING\b|\bERROR\b|\bError:\s/i', $line ) ) { return 'error'; }
        if ( preg_match( '/PHP Deprecated|PHP Notice|\bDEPRECATED\b|\bNOTICE\b/i', $line ) ) { return 'notice'; }
        return '';
    }

    private static function log_indicators( &$items, &$meta ) {
        $stats = array( 'files_inspected' => 0, 'critical' => 0, 'error' => 0, 'notice' => 0,
            'bytes_inspected' => 0, 'truncated_files' => 0, 'available_files' => 0 );
        $path = self::get_log_directory();
        $root = is_string( $path ) && $path ? realpath( $path ) : false;
        // Local WooCommerce logs only. No scanning arbitrary PHP error_log paths.
        if ( ! $root || ! is_dir( $root ) || ! is_readable( $root ) ) {
            $meta['technical_logs'] = $stats;
            self::item( $items, 'logs_availability', 'logs', 'info', 'Nie można odczytać katalogu logów WooCommerce',
                'Brak czytelnego lokalnego katalogu logów lub aktywny inny backend przechowywania.',
                'Nie oznacza braku błędów. WooCommerce może korzystać z bazy danych albo własnego handlera logów.',
                'WooCommerce → Status → Dzienniki: sprawdź aktywny sposób rejestrowania.', 'signal' );
            return;
        }
        $names = @scandir( $root );
        if ( ! is_array( $names ) ) { $names = array(); }
        $files = array();
        foreach ( $names as $name ) {
            if ( ! preg_match( '/^[a-zA-Z0-9._-]{1,200}\.log$/', (string) $name ) ) { continue; }
            $file = $root . DIRECTORY_SEPARATOR . $name;
            if ( is_link( $file ) || ! is_file( $file ) || ! is_readable( $file ) ) { continue; }
            $mtime = @filemtime( $file );
            $files[] = array( 'path' => $file, 'mtime' => $mtime ?: 0 );
            if ( count( $files ) > 500 ) { break; }
        }
        usort( $files, function ( $a, $b ) { return $b['mtime'] <=> $a['mtime']; } );
        $stats['available_files'] = count( $files );
        foreach ( array_slice( $files, 0, 8 ) as $f ) {
            $handle = @fopen( $f['path'], 'rb' );
            if ( ! $handle ) { continue; }
            try {
                $size = @filesize( $f['path'] );
                $size = is_int( $size ) ? $size : 0;
                $max = 65536; // Max 64KB per file, eight files => 512KB raw IO.
                $seek = max( 0, $size - $max );
                if ( $seek > 0 && 0 !== @fseek( $handle, $seek ) ) { continue; }
                $contents = @stream_get_contents( $handle, $max );
                if ( ! is_string( $contents ) ) { continue; }
                if ( $seek > 0 ) {
                    $stats['truncated_files']++;
                    $first_break = strpos( $contents, "\n" );
                    $contents = false !== $first_break ? substr( $contents, $first_break + 1 ) : '';
                }
                $stats['files_inspected']++;
                $stats['bytes_inspected'] += strlen( $contents );
                // Split only a bounded string. Count indicators, NOT distinct failures.
                foreach ( explode( "\n", $contents ) as $line ) {
                    $kind = self::classify_line( $line );
                    if ( $kind ) { $stats[ $kind ]++; }
                }
                unset( $contents );
            } finally { fclose( $handle ); }
        }
        $meta['technical_logs'] = $stats;
        $signals = $stats['critical'] + $stats['error'];
        if ( $signals ) {
            self::item( $items, 'logs_indicators', 'logs', 'warning', 'Sygnały błędów w lokalnych logach WooCommerce',
                'Linie oznaczone jako krytyczne: ' . $stats['critical'] . '; błędy / warning: ' . $stats['error'] .
                '; notice / deprecated: ' . $stats['notice'] . '; pliki odczytane: ' . $stats['files_inspected'] . '.',
                'Zliczane są słowa-klucze w końcowych fragmentach logów. Mogą dotyczyć innych funkcji sklepu lub wielokrotnie opisywać ten sam problem. Nie stanowią dowodu awarii checkoutu.',
                'WooCommerce → Status → Dzienniki: sprawdź odpowiednie wpisy w oryginalnych logach. Nie przesyłaj publicznie logów z danymi klientów.', 'signal' );
        } else {
            self::item( $items, 'logs_indicators', 'logs', 'info', 'Brak sklasyfikowanych błędów w próbie logów',
                'Odczytano ' . $stats['files_inspected'] . ' z ' . $stats['available_files'] .
                ' plików; maksymalnie 64 KiB na plik. Zliczenia błędów: 0.',
                'Ten wynik nie dowodzi braku błędów. Niektóre logi są w bazie danych, pozostałe fragmenty plików nie były badane.',
                'W razie objawów sprawdź logi WooCommerce i PHP w swoim panelu hostingu.', 'signal' );
        }
    }
}
