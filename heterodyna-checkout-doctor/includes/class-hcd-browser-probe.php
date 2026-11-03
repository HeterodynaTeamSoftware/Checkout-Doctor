<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Opt-in browser diagnostics run ONLY for an administrator testing their own checkout.
 * Store enumerated event categories only; never accept messages, stacks, URLs or order data.
 */
final class HCD_Browser_Probe {
    const KEY = 'hcd_browser_probe_samples';
    const TTL = 604800; // 7 days, sliding TTL; maximum 24 samples.

    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 99 );
        add_action( 'wp_ajax_hcd_browser_probe', array( __CLASS__, 'ajax_receive' ) );
        add_action( 'admin_post_hcd_clear_browser', array( __CLASS__, 'clear_action' ) );
    }

    public static function can_test() {
        return is_user_logged_in() && ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) );
    }

    public static function enabled() {
        return 'yes' === get_option( 'hcd_browser_observer_enabled', 'no' );
    }

    public static function enqueue() {
        if ( ! self::enabled() || ! self::can_test() || ! function_exists( 'is_checkout' ) || ! is_checkout() ) { return; }
        if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) { return; }
        wp_enqueue_script( 'hcd-browser-observer', HCD_URL . 'assets/js/checkout-observer.js', array(), HCD_VERSION, false );
        wp_localize_script( 'hcd-browser-observer', 'HCD_BROWSER_PROBE', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'hcd_browser_probe' ),
        ) );
    }

    /** Fixed enum validation prevents persistence of untrusted JavaScript error content. */
    public static function normalize_event( $item ) {
        if ( ! is_array( $item ) ) { return null; }
        $kinds = array( 'render', 'js_error', 'promise_rejection', 'resource_error', 'store_resource' );
        $contexts = array( 'block', 'classic', 'mixed', 'missing', 'checkout', 'cart', 'other' );
        $buckets = array( 'none', 'fast', 'medium', 'slow', 'unknown' );
        $kind = isset( $item['kind'] ) && is_string( $item['kind'] ) ? $item['kind'] : '';
        $context = isset( $item['context'] ) && is_string( $item['context'] ) ? $item['context'] : '';
        $bucket = isset( $item['bucket'] ) && is_string( $item['bucket'] ) ? $item['bucket'] : '';
        if ( ! in_array( $kind, $kinds, true ) || ! in_array( $context, $contexts, true ) || ! in_array( $bucket, $buckets, true ) ) { return null; }
        if ( 'render' === $kind && ! in_array( $context, array( 'block', 'classic', 'mixed', 'missing' ), true ) ) { return null; }
        if ( 'store_resource' === $kind && ! in_array( $context, array( 'checkout', 'cart' ), true ) ) { return null; }
        if ( in_array( $kind, array( 'js_error', 'promise_rejection', 'resource_error' ), true ) && 'other' !== $context ) { return null; }
        return array( 'kind' => $kind, 'context' => $context, 'bucket' => $bucket );
    }

    public static function ajax_receive() {
        if ( ! self::can_test() || ! self::enabled() ) { wp_send_json_error( array( 'message' => 'Funkcja niedostępna.' ), 403 ); }
        check_ajax_referer( 'hcd_browser_probe', 'nonce' );
        $uid = get_current_user_id();
        $key = 'hcd_browser_rate_' . absint( $uid );
        $requests = (int) get_transient( $key );
        if ( $requests >= 12 ) { wp_send_json_error( array( 'message' => 'Limit zgłoszeń.' ), 429 ); }
        set_transient( $key, $requests + 1, MINUTE_IN_SECONDS );
        $raw = isset( $_POST['events'] ) && is_string( $_POST['events'] ) ? wp_unslash( $_POST['events'] ) : '';
        if ( strlen( $raw ) > 2048 ) { wp_send_json_error( array( 'message' => 'Nieprawidłowe zgłoszenie.' ), 400 ); }
        $items = json_decode( $raw, true );
        if ( ! is_array( $items ) || count( $items ) > 8 ) { wp_send_json_error( array( 'message' => 'Nieprawidłowe zgłoszenie.' ), 400 ); }
        $existing = get_transient( self::KEY );
        $existing = is_array( $existing ) ? $existing : array();
        $accepted = 0;
        foreach ( $items as $item ) {
            $safe = self::normalize_event( $item );
            if ( ! $safe ) { continue; }
            $safe['at'] = gmdate( 'c' );
            $existing[] = $safe;
            ++$accepted;
        }
        if ( $accepted ) { set_transient( self::KEY, array_slice( $existing, -24 ), self::TTL ); }
        wp_send_json_success( array( 'accepted' => $accepted ) );
    }

    public static function clear_action() {
        if ( ! self::can_test() ) { wp_die( 'Brak uprawnień.', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'hcd_clear_browser' );
        delete_transient( self::KEY );
        wp_safe_redirect( admin_url( 'admin.php?page=hcd-checkout-doctor&tab=browser&cleared_browser=1' ) );
        exit;
    }

    public static function summary() {
        $raw = get_transient( self::KEY );
        $samples = is_array( $raw ) ? $raw : array();
        $counts = array( 'render' => 0, 'js_error' => 0, 'promise_rejection' => 0, 'resource_error' => 0, 'store_resource' => 0, 'slow_store' => 0, 'missing' => 0 );
        $last = '';
        foreach ( $samples as $item ) {
            $validated = self::normalize_event( $item );
            if ( ! $validated ) { continue; }
            ++$counts[ $validated['kind'] ];
            if ( 'render' === $validated['kind'] && 'missing' === $validated['context'] ) { ++$counts['missing']; }
            if ( 'store_resource' === $validated['kind'] && 'slow' === $validated['bucket'] ) { ++$counts['slow_store']; }
            $last = isset( $item['at'] ) && is_string( $item['at'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $item['at'] ) ? $item['at'] : $last;
        }
        return array( 'enabled' => self::enabled(), 'sample_count' => array_sum( array_slice( $counts, 0, 5 ) ), 'counts' => $counts, 'last_at_utc' => $last );
    }

    public static function run( &$meta ) {
        $data = self::summary();
        $meta['browser_probe'] = $data;
        $checks = array();
        $add = function( $id, $status, $title, $evidence, $meaning, $recommendation, $confidence = 'signal' ) use ( &$checks ) {
            $checks[] = compact( 'id', 'status', 'title', 'evidence', 'meaning', 'recommendation', 'confidence' ) + array( 'category' => 'browser' );
        };
        if ( ! $data['enabled'] ) {
            $add( 'browser_disabled', 'info', 'Obserwator przeglądarki jest wyłączony',
                'Domyślnie wyłączony. Brak nasłuchu JavaScript klientów.',
                'Bez uruchomienia testu przeglądarkowego nie można ocenić rzeczywistego renderowania formularza.',
                'Na stronie testowej włącz obserwator w Ustawieniach i zalogowany jako administrator odwiedź checkout.', 'confirmed' );
            return $checks;
        }
        if ( 0 === $data['sample_count'] ) {
            $add( 'browser_no_samples', 'info', 'Brak obserwacji przeglądarkowych',
                'Obserwator jest włączony, ale nie otrzymał jeszcze próbek.',
                'Brak próbek nie oznacza poprawnego działania checkoutu.',
                'Odwiedź checkout jako administrator, poczekaj kilka sekund i ponów skan.' );
            return $checks;
        }
        $c = $data['counts'];
        $add( 'browser_coverage', 'info', 'Obserwacje testu przeglądarkowego',
            'Próbki: ' . $data['sample_count'] . '; renderowanie: ' . $c['render'] . '; zasoby Store API: ' . $c['store_resource'] . '.',
            'Pomiar dotyczy jedynie strony odwiedzonej przez zalogowanego administratora. Nie jest testem transakcji.',
            'Powtórz test na stagingu w różnych przeglądarkach; nie porównuj tego licznika ze sprzedażą.', 'confirmed' );
        $errors = $c['js_error'] + $c['promise_rejection'] + $c['resource_error'];
        if ( $errors ) {
            $add( 'browser_js_signals', 'warning', 'Wykryto sygnały błędów JavaScript',
                'Błędy JS: ' . $c['js_error'] . '; odrzucone Promise: ' . $c['promise_rejection'] . '; błędy zasobów: ' . $c['resource_error'] . '.',
                'Nie znamy treści błędów ani ich przyczyny; mogą nie dotyczyć samego checkoutu.',
                'Na stagingu otwórz DevTools → Console/Network i porównaj czas testu z informacjami konsoli.' );
        }
        if ( $c['missing'] ) {
            $add( 'browser_missing_form', 'warning', 'Nie wykryto standardowego formularza checkout',
                'Obserwacje bez standardowego formularza: ' . $c['missing'] . '.',
                'Może to wynikać z pustego koszyka, przekierowania, niestandardowego szablonu lub problemu renderowania.',
                'Na stagingu dodaj produkt testowy do koszyka i sprawdź zawartość strony checkout.' );
        }
        if ( $c['slow_store'] ) {
            $add( 'browser_slow_store', 'warning', 'Wolne zasoby Store API podczas testu',
                'Zasoby Store API z czasem >1500 ms: ' . $c['slow_store'] . '.',
                'Czas zasobu w przeglądarce nie potwierdza awarii ani błędu serwera; może zależeć od sieci.',
                'Na stagingu porównaj czasy w DevTools → Network, na kilku próbach i urządzeniach.' );
        }
        return $checks;
    }
}
