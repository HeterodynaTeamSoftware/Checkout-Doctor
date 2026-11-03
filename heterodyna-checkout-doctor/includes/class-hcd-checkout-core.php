<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * v1.1: static, read-only checkout path inspection. It deliberately does NOT
 * execute checkout, payment callbacks, Store API handlers, or customer sessions.
 */
final class HCD_Checkout_Core {
    private static function item( &$checks, $id, $category, $status, $title, $evidence, $meaning, $recommendation, $confidence = 'confirmed' ) {
        $checks[] = array(
            'id' => $id, 'category' => $category, 'status' => $status,
            'title' => $title, 'evidence' => $evidence, 'meaning' => $meaning,
            'recommendation' => $recommendation, 'confidence' => $confidence,
        );
    }

    /** Inspect nested Gutenberg blocks, including checkout embedded in groups. */
    private static function count_block( $blocks, $name ) {
        $count = 0;
        foreach ( $blocks as $block ) {
            if ( ! is_array( $block ) ) { continue; }
            if ( isset( $block['blockName'] ) && $name === $block['blockName'] ) { $count++; }
            if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
                $count += self::count_block( $block['innerBlocks'], $name );
            }
        }
        return $count;
    }

    public static function run( &$meta ) {
        $checks = array();
        if ( ! class_exists( 'WooCommerce' ) ) { return $checks; }

        $checkout_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'checkout' ) : -1;
        $cart_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'cart' ) : -1;
        $checkout = $checkout_id > 0 && function_exists( 'get_post' ) ? get_post( $checkout_id ) : null;
        $cart = $cart_id > 0 && function_exists( 'get_post' ) ? get_post( $cart_id ) : null;
        $checkout_content = $checkout && isset( $checkout->post_content ) ? (string) $checkout->post_content : '';
        $cart_content = $cart && isset( $cart->post_content ) ? (string) $cart->post_content : '';

        if ( $checkout_id > 0 && $checkout_id === $cart_id ) {
            self::item( $checks, 'core_page_collision', 'core', 'critical', 'Koszyk i Checkout wskazują tę samą stronę',
                'WooCommerce checkout ID = cart ID = ' . $checkout_id . '.',
                'Dwa różne etapy zakupowe są przypisane do jednego zasobu; standardowy przepływ może ulec zakłóceniu.',
                'Przypisz osobne strony w WooCommerce → Ustawienia → Zaawansowane.' );
        } elseif ( $checkout_id > 0 && $cart_id > 0 ) {
            self::item( $checks, 'core_page_collision', 'core', 'pass', 'Koszyk i Checkout mają różne strony',
                'Checkout ID ' . $checkout_id . ' · Cart ID ' . $cart_id . '.',
                'Przypisania nie kolidują identyfikatorem strony.', 'Nie wymaga działania.' );
        } else {
            self::item( $checks, 'core_page_collision', 'core', 'info', 'Nie można porównać przypisań stron',
                'Checkout ID ' . $checkout_id . ' · Cart ID ' . $cart_id . '.',
                'Co najmniej jednej standardowej strony nie przypisano; osobny test ocenia jej dostępność.',
                'Weryfikuj konfigurację stron koszyka i zamówienia.', 'signal' );
        }

        if ( $checkout && ! empty( $checkout->post_password ) ) {
            self::item( $checks, 'core_checkout_password', 'core', 'critical', 'Strona zamówienia jest chroniona hasłem',
                'post_password: ustawione (wartość hasła nie jest odczytywana do raportu).',
                'Zwykli klienci nie otworzą standardowego checkoutu bez hasła strony.',
                'Usuń ochronę hasłem strony Checkout, jeśli to publiczny sklep.' );
        } elseif ( $checkout ) {
            self::item( $checks, 'core_checkout_password', 'core', 'pass', 'Checkout bez hasła strony',
                'post_password: brak ochrony hasłem.', 'Nie wykryto blokady wejścia przez hasło strony.', 'Nie wymaga działania.' );
        }

        $blocks_checkout = 0;
        $blocks_cart = 0;
        if ( function_exists( 'parse_blocks' ) ) {
            try {
                $blocks_checkout = self::count_block( parse_blocks( $checkout_content ), 'woocommerce/checkout' );
                $blocks_cart = self::count_block( parse_blocks( $cart_content ), 'woocommerce/cart' );
            } catch ( Throwable $e ) { /* fall back to has_block */ }
        }
        if ( ! $blocks_checkout && $checkout && function_exists( 'has_block' ) && has_block( 'woocommerce/checkout', $checkout ) ) {
            $blocks_checkout = 1;
        }
        if ( ! $blocks_cart && $cart && function_exists( 'has_block' ) && has_block( 'woocommerce/cart', $cart ) ) {
            $blocks_cart = 1;
        }
        $classic_checkout = function_exists( 'has_shortcode' ) && has_shortcode( $checkout_content, 'woocommerce_checkout' );
        $classic_cart = function_exists( 'has_shortcode' ) && has_shortcode( $cart_content, 'woocommerce_cart' );
        $meta['checkout_mode'] = $blocks_checkout ? 'blocks' : ( $classic_checkout ? 'classic' : 'custom_or_unknown' );
        $meta['cart_mode'] = $blocks_cart ? 'blocks' : ( $classic_cart ? 'classic' : 'custom_or_unknown' );
        $meta['checkout_page_id'] = $checkout_id;
        $meta['cart_page_id'] = $cart_id;
        $meta['checkout_block_count'] = $blocks_checkout;
        $meta['checkout_shortcode_found'] = (bool) $classic_checkout;
        $meta['cart_block_count'] = $blocks_cart;
        $meta['cart_shortcode_found'] = (bool) $classic_cart;

        if ( $blocks_checkout && $classic_checkout ) {
            self::item( $checks, 'core_checkout_collision', 'core', 'warning', 'Checkout ma blok i shortcode jednocześnie',
                'Blok woocommerce/checkout: obecny; [woocommerce_checkout]: obecny.',
                'Dwa mechanizmy mogą renderować równoległe formularze, ale efekt zależy od szablonu.',
                'W edytorze strony sprawdź, czy ma pozostać jeden mechanizm checkoutu.', 'signal' );
        } elseif ( $blocks_checkout > 1 ) {
            self::item( $checks, 'core_checkout_collision', 'core', 'warning', 'Wiele bloków Checkout na stronie',
                'Liczba znalezionych bloków woocommerce/checkout: ' . $blocks_checkout . '.',
                'Wielokrotny formularz na jednej stronie może zakłócać działanie.',
                'Usuń zdublowane bloki i sprawdź widok strony.', 'signal' );
        } elseif ( $blocks_checkout || $classic_checkout ) {
            self::item( $checks, 'core_checkout_collision', 'core', 'pass', 'Znaleziono pojedynczy mechanizm Checkout',
                'Tryb: ' . ( $blocks_checkout ? 'Checkout Block' : 'shortcode klasyczny' ) . '.',
                'Konfiguracja treści nie wskazuje na dwa równoległe mechanizmy.',
                'Sam wykryty formularz nie oznacza poprawnego ukończenia zamówienia.' );
        } else {
            self::item( $checks, 'core_checkout_collision', 'core', 'info', 'Checkout może być niestandardowy',
                'Nie odnaleziono podstawowego bloku ani shortcode w treści przypisanej strony.',
                'Własny szablon może dostarczać checkout poza treścią strony; skan nie potwierdza jego działania.',
                'Sprawdź ręcznie szablon strony lub konfigurację używanego kreatora.', 'signal' );
        }

        if ( $blocks_cart && $classic_cart ) {
            self::item( $checks, 'core_cart_content', 'core', 'warning', 'Koszyk ma blok i shortcode jednocześnie',
                'Blok woocommerce/cart i [woocommerce_cart] są obecne.',
                'Może pojawić się podwójny koszyk lub konflikt renderowania.',
                'Wybierz jeden mechanizm w edytorze strony.', 'signal' );
        } else {
            self::item( $checks, 'core_cart_content', 'core', 'info', 'Silnik strony koszyka',
                'Tryb koszyka: ' . $meta['cart_mode'] . '. Typ strony: ' . ( $cart ? (string) $cart->post_status : 'brak' ) . '.',
                'Mieszany typ koszyka i checkoutu może być poprawny. Sama obecność bloku/shortcode nie testuje przepływu zakupowego.',
                'Zweryfikuj ręcznie dodawanie produktu i przejście do checkoutu.', 'signal' );
        }

        $guest = function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_enable_guest_checkout', 'no' ) : 'nieodczytane';
        $create = function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' ) : 'nieodczytane';
        $meta['guest_checkout_enabled'] = 'yes' === $guest;
        $meta['account_creation_at_checkout'] = 'yes' === $create;
        self::item( $checks, 'core_accounts', 'core', 'info', 'Dostęp klientów do checkoutu',
            'Gość może zamawiać: ' . ( 'yes' === $guest ? 'TAK' : 'NIE' ) . '; rejestracja/logowanie z checkout: ' . ( 'yes' === $create ? 'TAK' : 'NIE' ) . '.',
            'To odczyt ustawień. Zakaz zakupów gościnnych może być świadomą polityką sklepu, a konta mogą być zakładane wcześniej.',
            'Sprawdź ścieżkę zakupową jako gość i jako zalogowany klient, zgodnie z polityką sklepu.', 'signal' );

        if ( function_exists( 'get_option' ) ) {
            $terms_id = (int) get_option( 'woocommerce_terms_page_id', 0 );
            if ( $terms_id > 0 ) {
                $terms = function_exists( 'get_post' ) ? get_post( $terms_id ) : null;
                if ( ! $terms || 'publish' !== $terms->post_status ) {
                    self::item( $checks, 'core_terms', 'core', 'warning', 'Wskazana strona regulaminu nie jest publiczna',
                        'WooCommerce terms_page_id: ' . $terms_id . '; status: ' . ( $terms ? (string) $terms->post_status : 'brak strony' ) . '.',
                        'Checkout może odwoływać się do strony regulaminu, której klient nie zobaczy.',
                        'Popraw przypisanie lub opublikuj stronę regulaminu.', 'confirmed' );
                } else {
                    self::item( $checks, 'core_terms', 'core', 'pass', 'Przypisany regulamin jest opublikowany',
                        'WooCommerce terms_page_id: ' . $terms_id . '; status publish.',
                        'Wskazana strona jest opublikowana; nie jest to ocena prawnej kompletności regulaminu.',
                        'Nie wymaga działania.' );
                }
            } else {
                self::item( $checks, 'core_terms', 'core', 'info', 'WooCommerce nie wskazuje strony regulaminu',
                    'woocommerce_terms_page_id: 0 / brak.',
                    'Nie rozstrzyga to zgodności prawnej sklepu ani sposobu prezentacji warunków zakupów.',
                    'Zweryfikuj dostępność regulaminu i wymaganych informacji prawnych.', 'signal' );
            }
        }

        if ( $blocks_checkout ) {
            $block_registered = class_exists( 'WP_Block_Type_Registry' ) && method_exists( 'WP_Block_Type_Registry', 'get_instance' ) &&
                null !== WP_Block_Type_Registry::get_instance()->get_registered( 'woocommerce/checkout' );
            self::item( $checks, 'blocks_registration', 'blocks', $block_registered ? 'pass' : 'warning',
                $block_registered ? 'Checkout Block zarejestrowany' : 'Nie widać rejestracji Checkout Block',
                'WP_Block_Type_Registry: ' . ( $block_registered ? 'woocommerce/checkout obecny.' : 'brak bloku w rejestrze.' ),
                $block_registered ? 'Rejestr WordPress rozpoznaje blok checkoutu; nie testuje renderowania ani skryptów.' :
                    'Strona zawiera blok, którego nie udało się potwierdzić w rejestrze podczas skanu administracyjnego.',
                $block_registered ? 'Przetestuj złożenie zamówienia na stagingu.' : 'Sprawdź wersję WooCommerce, stan bloków i błędy PHP.',
                $block_registered ? 'confirmed' : 'signal' );

            // Route registry inspection only; no network requests, no nonce/cart/session creation.
            if ( function_exists( 'rest_get_server' ) ) {
                try {
                    $routes = rest_get_server()->get_routes();
                    $cart_route = isset( $routes['/wc/store/v1/cart'] ) || isset( $routes['/wc/store/v1/cart/'] );
                    $checkout_route = isset( $routes['/wc/store/v1/checkout'] ) || isset( $routes['/wc/store/v1/checkout/'] );
                    $meta['store_api_checkout_route_registered'] = $checkout_route;
                    $meta['store_api_cart_route_registered'] = $cart_route;
                    if ( $cart_route && $checkout_route ) {
                        self::item( $checks, 'blocks_store_api_routes', 'blocks', 'pass', 'Store API: obie trasy zarejestrowane',
                            'Rejestr REST: /wc/store/v1/cart = TAK; /wc/store/v1/checkout = TAK.',
                            'Trasy znajdują się w rejestrze REST. Nie wykonano żadnego żądania i nie sprawdzono ich odpowiedzi.',
                            'W osobnym środowisku sprawdź rzeczywiste odpowiedzi i działanie checkoutu.' );
                    } else {
                        self::item( $checks, 'blocks_store_api_routes', 'blocks', 'warning', 'Brak części tras Store API w rejestrze',
                            'Rejestr tras: cart = ' . ( $cart_route ? 'TAK' : 'NIE' ) . '; checkout = ' . ( $checkout_route ? 'TAK' : 'NIE' ) . '.',
                            'Nie udało się potwierdzić standardowych tras WooCommerce Blocks; rejestracja może zależeć od środowiska.',
                            'Sprawdź błędy ładowania WooCommerce i dostępność REST API na stagingu.', 'signal' );
                    }
                } catch ( Throwable $e ) {
                    self::item( $checks, 'blocks_store_api_routes', 'blocks', 'warning', 'Nie udało się odczytać rejestru Store API',
                        'Odczyt tras WordPress REST API zakończył się błędem; treść wyjątku została ukryta.',
                        'Skan nie może potwierdzić rejestracji tras checkout i cart.',
                        'Sprawdź logi serwera i dostępność Store API.', 'signal' );
                }
            } else {
                self::item( $checks, 'blocks_store_api_routes', 'blocks', 'info', 'Rejestr Store API nieodczytany',
                    'Funkcja rest_get_server nie jest dostępna w tym przebiegu.',
                    'Nie sprawdzono tras bez tworzenia żądań HTTP.',
                    'Zweryfikuj Store API w panelu WordPress.', 'signal' );
            }
        } elseif ( $classic_checkout ) {
            self::item( $checks, 'classic_mode', 'classic', 'pass', 'Aktywny klasyczny checkout',
                'Znaleziono [woocommerce_checkout] na stronie zamówienia.',
                'Mechanizm shortcode rozpoznany w treści przypisanej strony. Nie testuje wykonania walidacji formularza.',
                'Sprawdź ręcznie przejście od koszyka do płatności na stagingu.' );
            $theme_path = function_exists( 'get_stylesheet_directory' ) ? get_stylesheet_directory() : '';
            $parent_path = function_exists( 'get_template_directory' ) ? get_template_directory() : '';
            $overrides = array();
            foreach ( array_unique( array_filter( array( $theme_path, $parent_path ) ) ) as $path ) {
                if ( is_file( trailingslashit( $path ) . 'woocommerce/checkout/form-checkout.php' ) ) {
                    $overrides[] = basename( $path );
                }
            }
            $meta['classic_checkout_override_count'] = count( $overrides );
            self::item( $checks, 'classic_template_override', 'classic', 'info', 'Nadpisanie szablonu klasycznego Checkout',
                'Motyw/child-theme nadpisują form-checkout.php: ' . ( $overrides ? implode( ', ', $overrides ) : 'nie wykryto' ) . '.',
                'Nadpisanie nie jest automatycznie błędem; nawet obecność pliku nie potwierdza, że jest używany.',
                $overrides ? 'Porównaj wersję szablonu z WooCommerce i przetestuj na stagingu.' : 'Brak działania; inne wtyczki wciąż mogą modyfikować checkout.', 'signal' );
        } else {
            self::item( $checks, 'core_custom_checkout', 'core', 'info', 'Niestandardowy mechanizm lub brak formularza',
                'Nie odnaleziono standardowego bloku ani shortcode.',
                'Skan statyczny nie może udowodnić, czy checkout wstawia kreator strony lub kod motywu.',
                'Przejrzyj szablon oraz przeprowadź ręczny test na stagingu.', 'signal' );
        }

        try {
            if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' ) &&
                method_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil', 'custom_orders_table_usage_is_enabled' ) ) {
                $hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
                $meta['hpos_enabled'] = (bool) $hpos;
                self::item( $checks, 'orders_hpos', 'orders', 'info', 'Magazyn zamówień WooCommerce',
                    'HPOS: ' . ( $hpos ? 'WŁĄCZONY' : 'WYŁĄCZONY / legacy' ) . '.',
                    'Checkout Doctor używa wc_get_orders; sam stan HPOS nie dowodzi problemu ani kompatybilności innych rozszerzeń.',
                    'W razie problemów z zamówieniami sprawdź zgodność pozostałych rozszerzeń z HPOS.', 'confirmed' );
            } else {
                self::item( $checks, 'orders_hpos', 'orders', 'info', 'Nie odczytano stanu HPOS',
                    'API OrderUtil nie jest dostępne.',
                    'Stan magazynu zamówień nie został ustalony.',
                    'Sprawdź konfigurację WooCommerce → Ustawienia → Zaawansowane.', 'signal' );
            }
        } catch ( Throwable $e ) {
            self::item( $checks, 'orders_hpos', 'orders', 'warning', 'Błąd odczytu stanu HPOS',
                'OrderUtil rzucił wyjątek (treść ukryta).',
                'Nie udało się sprawdzić mechanizmu przechowywania zamówień.',
                'Sprawdź logi WooCommerce.', 'signal' );
        }
        return $checks;
    }
}
