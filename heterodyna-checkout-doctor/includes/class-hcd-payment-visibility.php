<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * ZIP 10. Non-invasive frontend payment method visibility observation on staging ONLY.
 * Stores bounded anonymous counts; never reads payment form values, POSTs checkout,
 * invokes gateways, executes payment callbacks, or requests payment providers.
 */
final class HCD_Payment_Visibility {
    const KEY = 'hcd_payment_visibility_samples';
    const TTL = 604800;

    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 100 );
        add_action( 'wp_ajax_hcd_payment_visibility', array( __CLASS__, 'receive' ) );
        add_action( 'admin_post_hcd_clear_payment_visibility', array( __CLASS__, 'clear' ) );
    }

    public static function staging() {
        return function_exists( 'wp_get_environment_type' ) && in_array( wp_get_environment_type(), array( 'local', 'development', 'staging' ), true );
    }

    public static function allowed() {
        return is_user_logged_in() && ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) );
    }

    public static function enabled() {
        return self::staging() && 'yes' === get_option( 'hcd_payment_visibility_enabled', 'no' );
    }

    public static function enqueue() {
        if ( ! self::enabled() || ! self::allowed() || ! function_exists( 'is_checkout' ) || ! is_checkout() ) { return; }
        if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) { return; }
        if ( function_exists( 'is_checkout_pay_page' ) && is_checkout_pay_page() ) { return; }
        $cart_state = 'unknown';
        if ( function_exists( 'WC' ) ) {
            try {
                $wc = WC();
                if ( is_object( $wc ) && isset( $wc->cart ) && is_object( $wc->cart ) && method_exists( $wc->cart, 'is_empty' ) ) {
                    $cart_state = $wc->cart->is_empty() ? 'empty' : 'nonempty';
                }
            } catch ( Throwable $e ) { $cart_state = 'unknown'; }
        }
        wp_enqueue_script( 'hcd-payment-visibility', HCD_URL . 'assets/js/payment-visibility.js', array(), HCD_VERSION, true );
        wp_localize_script( 'hcd-payment-visibility', 'HCD_PAYMENT_VISIBILITY', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'hcd_payment_visibility' ),
            'cartState' => $cart_state,
        ) );
    }

    public static function normalize( $item ) {
        if ( ! is_array( $item ) ) { return null; }
        $mode = isset( $item['mode'] ) && is_string( $item['mode'] ) ? $item['mode'] : '';
        $cart = isset( $item['cart'] ) && is_string( $item['cart'] ) ? $item['cart'] : '';
        if ( ! in_array( $mode, array( 'block', 'classic', 'mixed', 'unknown' ), true ) ) { return null; }
        if ( ! in_array( $cart, array( 'empty', 'nonempty', 'unknown' ), true ) ) { return null; }
        $number = isset( $item['visible'] ) ? $item['visible'] : null;
        if ( ! is_int( $number ) || $number < 0 || $number > 20 ) { return null; }
        return array( 'mode' => $mode, 'cart' => $cart, 'visible' => $number );
    }

    public static function receive() {
        if ( ! self::enabled() || ! self::allowed() ) { wp_send_json_error( array( 'message' => 'Test dostępny tylko na stagingu dla administratora.' ), 403 ); }
        check_ajax_referer( 'hcd_payment_visibility', 'nonce' );
        $key = 'hcd_payment_vis_limit_' . get_current_user_id();
        $count = (int) get_transient( $key );
        if ( $count >= 6 ) { wp_send_json_error( array( 'message' => 'Przekroczony limit prób.' ), 429 ); }
        set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
        $raw = isset( $_POST['snapshot'] ) && is_string( $_POST['snapshot'] ) ? wp_unslash( $_POST['snapshot'] ) : '';
        if ( strlen( $raw ) > 250 ) { wp_send_json_error( array( 'message' => 'Nieprawidłowa próbka.' ), 400 ); }
        $snapshot = self::normalize( json_decode( $raw, true ) );
        if ( null === $snapshot ) { wp_send_json_error( array( 'message' => 'Nieprawidłowa próbka.' ), 400 ); }
        $snapshot['at'] = gmdate( 'c' );
        $old = get_transient( self::KEY );
        $old = is_array( $old ) ? $old : array();
        $old[] = $snapshot;
        set_transient( self::KEY, array_slice( $old, -12 ), self::TTL );
        wp_send_json_success( array( 'accepted' => 1 ) );
    }

    public static function clear() {
        if ( ! self::allowed() ) { wp_die( 'Brak uprawnień.', '', array( 'response' => 403 ) ); }
        check_admin_referer( 'hcd_clear_payment_visibility' );
        delete_transient( self::KEY );
        wp_safe_redirect( admin_url( 'admin.php?page=hcd-checkout-doctor&tab=visibility&cleared_visibility=1' ) );
        exit;
    }

    public static function summary() {
        $raw = get_transient( self::KEY );
        $raw = is_array( $raw ) ? array_slice( $raw, -12 ) : array();
        $rows = array();
        foreach ( $raw as $entry ) {
            $safe = self::normalize( $entry );
            if ( null === $safe ) { continue; }
            $safe['at'] = isset( $entry['at'] ) && is_string( $entry['at'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}T/', $entry['at'] ) ? substr( $entry['at'], 0, 25 ) : '';
            $rows[] = $safe;
        }
        return array( 'enabled' => self::enabled(), 'staging' => self::staging(), 'samples' => $rows,
            'count' => count( $rows ), 'last' => count( $rows ) ? $rows[count( $rows ) - 1] : null );
    }

    public static function run( &$meta ) {
        $state = self::summary();
        $meta['payment_visibility'] = $state;
        $checks = array();
        $add = function( $id, $status, $title, $evidence, $meaning, $recommendation, $confidence = 'signal' ) use ( &$checks ) {
            $checks[] = compact( 'id', 'status', 'title', 'evidence', 'meaning', 'recommendation', 'confidence' ) + array( 'category' => 'visibility' );
        };
        if ( ! $state['staging'] ) {
            $add( 'visibility_staging_only', 'info', 'Test widoczności dostępny tylko na stagingu',
                'Środowisko WordPress nie ma typu local/development/staging.',
                'Na produkcji obserwator nie ładuje się i nie śledzi kupujących.',
                'Skonfiguruj kopię testową i ustaw WP_ENVIRONMENT_TYPE na staging. Wykonaj test tam.', 'confirmed' );
            return $checks;
        }
        if ( ! $state['enabled'] ) {
            $add( 'visibility_disabled', 'info', 'Obserwator widoczności jest wyłączony',
                'Test jest opcjonalny; aktualnie nie zbiera próbek.',
                'Nie oceniono renderowania metod płatności w przeglądarce.',
                'W ustawieniach włącz test widoczności; otwórz checkout jako administrator.', 'confirmed' );
            return $checks;
        }
        if ( ! $state['last'] ) {
            $add( 'visibility_no_samples', 'info', 'Brak próbek widoczności bramek',
                'Obserwator aktywny, lecz nie otrzymał pomiaru.',
                'Brak danych nie oznacza błędu płatności.',
                'Dodaj testowy produkt na stagingu, odwiedź checkout jako administrator i odśwież raport.' );
            return $checks;
        }
        $last = $state['last'];
        $add( 'visibility_last_sample', 'info', 'Liczba wyrenderowanych standardowych opcji płatności',
            'Tryb: ' . $last['mode'] . '; opcje: ' . $last['visible'] . '; koszyk: ' . $last['cart'] . '; próbki: ' . $state['count'] . '.',
            'Obserwacja dotyczy tylko standardowych kontrolek w checkout administratora na stagingu. Nie mierzy portfeli express ani skuteczności transakcji.',
            'Porównaj wynik z ustawieniami bramek i panelem DevTools. Sprawdź dodatkowo checkout jako gość.' );
        if ( 'nonempty' === $last['cart'] && 0 === $last['visible'] && in_array( $last['mode'], array( 'block', 'classic' ), true ) ) {
            $add( 'visibility_no_methods', 'warning', 'Nie wykryto standardowych opcji płatności przy niepustym koszyku',
                'Próbka staging: tryb ' . $last['mode'] . '; 0 rozpoznanych kontrolek płatności.',
                'Może to być poprawna konfiguracja, niestandardowy checkout, asynchroniczne renderowanie, bezpłatny koszyk albo problem integracji. Nie jest to dowód awarii.',
                'Sprawdź warunki koszyka, DevTools, rejestrację JS bramek i logi. Nie wykonuj płatności produkcyjnej.' );
        }
        return $checks;
    }
}
