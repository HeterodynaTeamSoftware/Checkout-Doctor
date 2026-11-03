<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class HCD_Admin {
    const PAGE = 'hcd-checkout-doctor';

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_action( 'wp_ajax_hcd_run_scan', array( __CLASS__, 'ajax_run_scan' ) );
        add_action( 'admin_post_hcd_save_settings', array( __CLASS__, 'save_settings' ) );
        add_action( 'admin_post_hcd_clear_events', array( __CLASS__, 'clear_events' ) );
        add_action( 'admin_post_hcd_export_report', array( __CLASS__, 'export_report' ) );
        add_action( 'admin_post_hcd_export_plan', array( __CLASS__, 'export_plan' ) );
        add_action( 'admin_post_hcd_export_events', array( __CLASS__, 'export_events' ) );
        add_action( 'admin_post_hcd_export_comparison', array( __CLASS__, 'export_comparison' ) );
        add_action( 'admin_post_hcd_save_integration_tests', array( __CLASS__, 'save_integration_tests' ) );
        add_action( 'admin_post_hcd_export_integration_tests', array( __CLASS__, 'export_integration_tests' ) );
        add_action( 'admin_post_hcd_export_release_snapshot', array( __CLASS__, 'export_release_snapshot' ) );
    }

    private static function can_manage() {
        return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
    }

    private static function authorize( $nonce_action ) {
        if ( ! self::can_manage() ) { wp_die( esc_html__( 'Brak uprawnień.', 'heterodyna-checkout-doctor' ), '', array( 'response' => 403 ) ); }
        check_admin_referer( $nonce_action );
    }

    public static function menu() {
        $capability = current_user_can( 'manage_woocommerce' ) ? 'manage_woocommerce' : 'manage_options';
        if ( class_exists( 'WooCommerce' ) ) {
            add_submenu_page( 'woocommerce', 'Heterodyna Checkout Doctor', 'Checkout Doctor', $capability,
                self::PAGE, array( __CLASS__, 'render' ) );
        } else {
            add_menu_page( 'Heterodyna Checkout Doctor', 'Checkout Doctor', $capability,
                self::PAGE, array( __CLASS__, 'render' ), 'dashicons-shield-alt', 58 );
        }
    }

    public static function enqueue( $hook ) {
        // Keep other WordPress admin screens completely untouched.
        if ( ! isset( $_GET['page'] ) || ! is_string( $_GET['page'] ) || self::PAGE !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { return; }
        wp_enqueue_style( 'hcd-admin', HCD_URL . 'assets/css/admin.css', array(), HCD_VERSION );
        wp_enqueue_script( 'hcd-admin', HCD_URL . 'assets/js/admin.js', array(), HCD_VERSION, true );
        wp_localize_script( 'hcd-admin', 'HCD_CONFIG', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'hcd_scan' ),
            'lastScan' => HCD_Repair_Reports::enrich( get_option( 'hcd_last_scan', array() ) ),
            'events' => HCD_Events::recent( 50 ),
            'counts' => HCD_Events::summary( 7 ),
            'monitoring' => HCD_Events::enabled(),
        ) );
    }

    public static function ajax_run_scan() {
        if ( ! self::can_manage() ) { wp_send_json_error( array( 'message' => 'Brak uprawnień.' ), 403 ); }
        check_ajax_referer( 'hcd_scan', 'nonce' );
        // Atomic per-user option insertion avoids get/set transient race windows.
        $user_id = get_current_user_id();
        $token = HCD_Scan_Lock::acquire( $user_id );
        if ( false === $token ) {
            wp_send_json_error( array( 'message' => 'Skan trwa już w innej karcie lub nie można założyć blokady. Spróbuj ponownie.' ), 429 );
            return;
        }
        try {
            $previous = get_option( 'hcd_last_scan', array() );
            $report = HCD_Scanner::run();
            $report['comparison'] = HCD_Readiness::compare( $report, $previous );
            update_option( 'hcd_last_scan', HCD_Store_API_Observer::for_archive( $report ), false );
        } catch ( Throwable $e ) {
            HCD_Scan_Lock::release( $user_id, $token );
            // Never expose exception messages, paths, credentials or error bodies.
            wp_send_json_error( array( 'message' => 'Skan nie został ukończony. Sprawdź log PHP/WooCommerce i spróbuj ponownie.' ), 500 );
            return;
        }
        HCD_Scan_Lock::release( $user_id, $token );
        wp_send_json_success( $report );
    }

    public static function save_settings() {
        self::authorize( 'hcd_settings' );
        $enabled = isset( $_POST['hcd_monitoring_enabled'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['hcd_monitoring_enabled'] ) );
        $days = isset( $_POST['hcd_retention_days'] ) ? absint( $_POST['hcd_retention_days'] ) : 14;
        if ( ! in_array( $days, array( 7, 14, 30 ), true ) ) { $days = 14; }
        update_option( 'hcd_monitoring_enabled', $enabled ? 'yes' : 'no', false );
        $browser_enabled = isset( $_POST['hcd_browser_observer_enabled'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['hcd_browser_observer_enabled'] ) );
        update_option( 'hcd_browser_observer_enabled', $browser_enabled ? 'yes' : 'no', false );
        // This separate opt-in cannot be enabled on production, even via crafted admin POST.
        $visibility_enabled = HCD_Payment_Visibility::staging() && isset( $_POST['hcd_payment_visibility_enabled'] )
            && 'yes' === sanitize_text_field( wp_unslash( $_POST['hcd_payment_visibility_enabled'] ) );
        update_option( 'hcd_payment_visibility_enabled', $visibility_enabled ? 'yes' : 'no', false );
        $store_enabled = HCD_Store_API_Observer::staging() && isset( $_POST['hcd_store_api_observer_enabled'] )
            && 'yes' === sanitize_text_field( wp_unslash( $_POST['hcd_store_api_observer_enabled'] ) );
        update_option( HCD_Store_API_Observer::OPTION, $store_enabled ? 'yes' : 'no', false );
        if ( ! $store_enabled ) { delete_transient( HCD_Store_API_Observer::KEY ); HCD_Store_API_Observer::clear_archive(); }
        if ( ! $visibility_enabled ) { delete_transient( HCD_Payment_Visibility::KEY ); }
        if ( ! $browser_enabled ) { delete_transient( HCD_Browser_Probe::KEY ); }
        update_option( 'hcd_retention_days', $days, false );
        HCD_Events::cleanup();
        wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&tab=settings&saved=1' ) );
        exit;
    }

    public static function clear_events() {
        self::authorize( 'hcd_clear_events' );
        HCD_Events::purge();
        wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&tab=events&cleared=1' ) );
        exit;
    }

    public static function export_report() {
        self::authorize( 'hcd_export_report' );
        $report = get_option( 'hcd_last_scan', array() );
        if ( ! is_array( $report ) || empty( $report['generated_at'] ) ) {
            wp_die( 'Najpierw uruchom skan.' );
        }
        $report = HCD_Repair_Reports::enrich( $report );
        nocache_headers();
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="heterodyna-checkout-doctor-report-' . gmdate( 'Ymd-His' ) . '.json"' );
        echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        exit;
    }

    public static function export_plan() {
        self::authorize( 'hcd_export_plan' );
        $report = get_option( 'hcd_last_scan', array() );
        if ( ! is_array( $report ) || empty( $report['generated_at'] ) ) { wp_die( 'Najpierw uruchom skan.' ); }
        $report = HCD_Repair_Reports::enrich( $report );
        $format = isset( $_GET['format'] ) && is_string( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : '';
        if ( ! in_array( $format, array( 'csv', 'html' ), true ) ) {
            wp_die( 'Nieobsługiwany format raportu.', '', array( 'response' => 400 ) );
        }
        nocache_headers();
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Disposition: attachment; filename="heterodyna-checkout-plan-' . gmdate( 'Ymd-His' ) . '.' . $format . '"' );
        if ( 'csv' === $format ) {
            header( 'Content-Type: text/csv; charset=utf-8' );
            $stream = fopen( 'php://output', 'w' );
            if ( false !== $stream ) {
                foreach ( HCD_Repair_Reports::csv_rows( $report ) as $row ) { fputcsv( $stream, $row ); }
                fclose( $stream );
            }
        } else {
            header( 'Content-Type: text/html; charset=utf-8' );
            header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; img-src 'none'" );
            echo HCD_Repair_Reports::html_report( $report ); // Escapes every variable, contains no JS or remote assets.
        }
        exit;
    }

    public static function export_comparison() {
        self::authorize( 'hcd_export_comparison' );
        $report = get_option( 'hcd_last_scan', array() );
        if ( ! is_array( $report ) || empty( $report['generated_at'] ) ) {
            wp_die( 'Najpierw uruchom skan.' );
        }
        $comparison = isset( $report['comparison'] ) && is_array( $report['comparison'] ) ? $report['comparison'] : array();
        if ( empty( $comparison['available'] ) ) {
            wp_die( 'Porównanie wymaga dwóch zakończonych skanów. Uruchom skan ponownie.' );
        }
        nocache_headers();
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="heterodyna-checkout-porownanie-' . gmdate( 'Ymd-His' ) . '.csv"' );
        $stream = fopen( 'php://output', 'w' );
        if ( false !== $stream ) {
            foreach ( HCD_Readiness::csv_rows( $comparison ) as $row ) { fputcsv( $stream, $row ); }
            fclose( $stream );
        }
        exit;
    }

    public static function save_integration_tests() {
        self::authorize( 'hcd_save_integration_tests' );
        $raw = isset( $_POST['hcd_scenario'] ) && is_array( $_POST['hcd_scenario'] )
            ? wp_unslash( $_POST['hcd_scenario'] ) : array();
        $current = get_option( HCD_Integration_Tests::OPTION, array() );
        $reconfirm = isset( $_POST['hcd_reconfirm'] ) && is_array( $_POST['hcd_reconfirm'] )
            ? wp_unslash( $_POST['hcd_reconfirm'] ) : array();
        $next = HCD_Integration_Tests::store( $raw, $current, null, $reconfirm );
        update_option( HCD_Integration_Tests::OPTION, $next, false );
        wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&tab=integration&integration_saved=1' ) );
        exit;
    }

    /** Privacy-restricted export suitable for attaching to an issue after review. */
    public static function export_release_snapshot() {
        self::authorize( 'hcd_export_release_snapshot' );
        $report = get_option( 'hcd_last_scan', array() );
        $environment = array(
            'type' => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'unknown',
            'php' => PHP_VERSION,
            'wordpress' => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '',
            'woocommerce' => defined( 'WC_VERSION' ) ? WC_VERSION : '',
            'woocommerce_active' => class_exists( 'WooCommerce' ),
        );
        $snapshot = HCD_Release_Snapshot::build( $report, HCD_Integration_Tests::snapshot(), $environment );
        nocache_headers();
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Cache-Control: private, no-store' );
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="heterodyna-checkout-support-' . gmdate( 'Ymd-His' ) . '.json"' );
        echo wp_json_encode( $snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        exit;
    }

    public static function export_integration_tests() {
        self::authorize( 'hcd_export_integration_tests' );
        nocache_headers();
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="heterodyna-checkout-integration-' . gmdate( 'Ymd-His' ) . '.json"' );
        echo wp_json_encode( array(
            'plugin_version' => HCD_VERSION,
            'exported_utc' => gmdate( 'c' ),
            'environment' => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'unknown',
            'manual_results' => HCD_Integration_Tests::snapshot(),
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        exit;
    }

    public static function export_events() {
        self::authorize( 'hcd_export_events' );
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="heterodyna-checkout-events-' . gmdate( 'Ymd-His' ) . '.csv"' );
        $stream = fopen( 'php://output', 'w' );
        if ( false !== $stream ) {
            // Explicitly limited to 250 latest rows. No names, emails, order IDs or IPs are stored.
            fputcsv( $stream, array( 'utc_time', 'event', 'source', 'machine_code' ) );
            foreach ( HCD_Events::recent( 250 ) as $row ) {
                fputcsv( $stream, array( $row['recorded_at'], $row['event_type'], $row['source'], $row['detail'] ) );
            }
            fclose( $stream );
        }
        exit;
    }

    private static function action_url( $action ) {
        return admin_url( 'admin-post.php?action=' . rawurlencode( $action ) );
    }

    public static function render() {
        if ( ! self::can_manage() ) { wp_die( 'Brak uprawnień.' ); }
        $monitoring = HCD_Events::enabled();
        $days = (int) get_option( 'hcd_retention_days', 14 );
        $integration = HCD_Integration_Tests::snapshot();
        ?>
        <div class="wrap hcd-root" id="hcd-app">
            <header class="hcd-topbar">
                <div class="hcd-brand">
                    <div class="hcd-logo-plate"><img src="<?php echo esc_url( HCD_URL . 'assets/img/heterodyna-original.webp' ); ?>" alt="Logo Heterodyna Team Software" width="210" height="70"></div>
                    <div><div class="hcd-eyebrow">WORDPRESS / WOOCOMMERCE</div><div class="hcd-brand-title">Checkout Doctor</div></div>
                </div>
                <div class="hcd-top-right"><span class="hcd-meta">v<?php echo esc_html( HCD_VERSION ); ?></span><span class="hcd-pill <?php echo $monitoring ? 'hcd-pill-live' : 'hcd-pill-neutral'; ?>"><?php echo $monitoring ? '● MONITORING ON' : '○ MONITORING OFF'; ?></span><span class="hcd-pill hcd-open">OPEN SOURCE</span></div>
            </header>
            <div class="hcd-layout">
                <aside class="hcd-sidebar">
                    <div class="hcd-side-label">PRZESTRZEŃ ROBOCZA</div>
                    <nav aria-label="Nawigacja Checkout Doctor">
                        <button type="button" data-hcd-tab="dashboard" class="hcd-nav is-active"><span class="dashicons dashicons-dashboard"></span> Pulpit</button>
                        <button type="button" data-hcd-tab="diagnostics" class="hcd-nav"><span class="dashicons dashicons-search"></span> Diagnostyka</button>
                        <button type="button" data-hcd-tab="checkout-core" class="hcd-nav"><span class="dashicons dashicons-cart"></span> Silnik Checkout</button>
                        <button type="button" data-hcd-tab="payments" class="hcd-nav"><span class="dashicons dashicons-money-alt"></span> Płatności</button>
                        <button type="button" data-hcd-tab="shipping" class="hcd-nav"><span class="dashicons dashicons-car"></span> Wysyłka</button>
                        <button type="button" data-hcd-tab="conflicts" class="hcd-nav"><span class="dashicons dashicons-admin-plugins"></span> Konflikty</button>
                        <button type="button" data-hcd-tab="logs" class="hcd-nav"><span class="dashicons dashicons-media-text"></span> Logi techniczne</button>
                        <button type="button" data-hcd-tab="repairs" class="hcd-nav"><span class="dashicons dashicons-yes-alt"></span> Plan napraw</button>
                        <button type="button" data-hcd-tab="readiness" class="hcd-nav"><span class="dashicons dashicons-chart-line"></span> Gotowość i zmiany</button>
                        <button type="button" data-hcd-tab="compatibility" class="hcd-nav"><span class="dashicons dashicons-admin-tools"></span> Zgodność i wydanie</button>
                        <button type="button" data-hcd-tab="integration" class="hcd-nav"><span class="dashicons dashicons-clipboard"></span> Testy integracyjne</button>
                        <button type="button" data-hcd-tab="block-compat" class="hcd-nav"><span class="dashicons dashicons-block-default"></span> Zgodność bloków</button>
                        <button type="button" data-hcd-tab="scenarios" class="hcd-nav"><span class="dashicons dashicons-filter"></span> Scenariusze koszyka</button>
                        <button type="button" data-hcd-tab="visibility" class="hcd-nav"><span class="dashicons dashicons-visibility"></span> Widoczność płatności</button>
                        <button type="button" data-hcd-tab="store-api" class="hcd-nav"><span class="dashicons dashicons-rest-api"></span> Błędy Store API</button>
                        <button type="button" data-hcd-tab="correlation" class="hcd-nav"><span class="dashicons dashicons-chart-area"></span> Korelacja dowodów</button>
                        <button type="button" data-hcd-tab="browser" class="hcd-nav"><span class="dashicons dashicons-desktop"></span> Przeglądarka JS</button>
                        <button type="button" data-hcd-tab="events" class="hcd-nav"><span class="dashicons dashicons-list-view"></span> Dziennik zdarzeń</button>
                        <button type="button" data-hcd-tab="settings" class="hcd-nav"><span class="dashicons dashicons-admin-settings"></span> Ustawienia</button>
                    </nav>
                    <div class="hcd-side-footer"><strong>DIAGNOSTYKA LOKALNA</strong><p>Skan nie tworzy zamówień, nie obciąża kart i nie wysyła danych do usług zewnętrznych.</p><small>GPL-2.0-or-later · 100% kod źródłowy</small></div>
                </aside>
                <main class="hcd-main">
                    <?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
                        <div class="hcd-notice">WooCommerce nie jest aktywne. Wtyczka uruchomi się, ale diagnostyka zamówień wymaga WooCommerce.</div>
                    <?php endif; ?>
                    <?php if ( isset( $_GET['saved'] ) ) : ?><div class="hcd-notice hcd-success">Zapisano ustawienia monitoringu.</div><?php endif; ?>
                    <?php if ( isset( $_GET['cleared'] ) ) : ?><div class="hcd-notice hcd-success">Dziennik został wyczyszczony.</div><?php endif; ?>
                    <?php if ( isset( $_GET['cleared_browser'] ) ) : ?><div class="hcd-notice hcd-success">Próbki przeglądarkowe zostały wyczyszczone.</div><?php endif; ?>
                    <?php if ( isset( $_GET['cleared_store_api'] ) ) : ?><div class="hcd-notice hcd-success">Pomiary Store API zostały wyczyszczone.</div><?php endif; ?>
                    <?php if ( isset( $_GET['cleared_visibility'] ) ) : ?><div class="hcd-notice hcd-success">Próbki widoczności zostały wyczyszczone.</div><?php endif; ?>
                    <section class="hcd-page" data-hcd-page="dashboard">
                        <div class="hcd-section-head"><div><div class="hcd-eyebrow">COMMAND CENTER</div><h1>Stan procesu zakupowego</h1><p>Jeden skan, konkretne dowody i jasne zalecenia.</p></div><button type="button" id="hcd-run-scan" class="hcd-btn hcd-btn-main"><span class="dashicons dashicons-search"></span> Uruchom diagnostykę</button></div>
                        <div id="hcd-message" class="hcd-flash" role="status" aria-live="polite"></div>
                        <div class="hcd-kpis" id="hcd-kpis"><div class="hcd-kpi"><span>ZGŁOSZENIA KRYTYCZNE</span><strong>—</strong><small>Ostatni skan</small></div><div class="hcd-kpi"><span>DO SPRAWDZENIA</span><strong>—</strong><small>Ostatni skan</small></div><div class="hcd-kpi"><span>POTWIERDZONE OK</span><strong>—</strong><small>Ostatni skan</small></div><div class="hcd-kpi"><span>ZDARZENIA CHECKOUT</span><strong>—</strong><small>Ostatnie 7 dni</small></div></div>
                        <div class="hcd-core-strip" id="hcd-core-strip" aria-live="polite"><div class="hcd-core-mode"><small>CHECKOUT ENGINE</small><strong>—</strong><span>Typ checkoutu</span></div><div class="hcd-core-mode"><small>CART ENGINE</small><strong>—</strong><span>Typ koszyka</span></div><div class="hcd-core-mode"><small>STORE API</small><strong>—</strong><span>Rejestr tras</span></div><div class="hcd-core-mode"><small>ORDER STORAGE</small><strong>—</strong><span>HPOS</span></div></div>
                        <div class="hcd-commerce-strip" id="hcd-commerce-strip"><div class="hcd-commerce-stat"><small>AKTYWNE PŁATNOŚCI</small><strong data-hcd-commerce="gateways">—</strong><span>Konfiguracja bramek</span></div><div class="hcd-commerce-stat"><small>STREFY WYSYŁKI</small><strong data-hcd-commerce="zones">—</strong><span>Strefy dodatkowe</span></div><div class="hcd-commerce-stat"><small>AKTYWNE METODY DOSTAWY</small><strong data-hcd-commerce="methods">—</strong><span>Łącznie ze strefą domyślną</span></div></div>
                        <div class="hcd-two-cols"><article class="hcd-card"><div class="hcd-card-heading"><h2>Wynik ostatniej diagnostyki</h2><span id="hcd-scan-date">Brak skanu</span></div><div id="hcd-overview" class="hcd-empty">Kliknij „Uruchom diagnostykę”, aby sprawdzić stan konfiguracji sklepu.</div></article><article class="hcd-card"><div class="hcd-card-heading"><h2>Jak działa Checkout Doctor?</h2><span class="hcd-tag">PRYWATNOŚĆ</span></div><div class="hcd-feature"><span>01</span><div><b>Analiza konfiguracji</b><p>Checkout, koszyk, HTTPS, metody płatności, wysyłka i historia Failed.</p></div></div><div class="hcd-feature"><span>02</span><div><b>Przejrzyste dowody</b><p>Każde ustalenie ma obserwację, znaczenie i propozycję kolejnego kroku.</p></div></div><div class="hcd-feature"><span>03</span><div><b>Opcjonalna telemetria</b><p>Bez danych osobowych klientów i bez wykonywania próbnych płatności.</p></div></div></article></div>
                    </section>
                    <section class="hcd-page" data-hcd-page="diagnostics" hidden>
                        <div class="hcd-section-head"><div><div class="hcd-eyebrow">DIAGNOSTICS</div><h1>Wyniki i dowody</h1><p>Obserwacje potwierdzone oddzielamy od sygnałów wymagających dalszej analizy.</p></div><div class="hcd-actions"><button type="button" class="hcd-btn" id="hcd-run-scan-secondary">Skanuj ponownie</button><a class="hcd-btn" href="<?php echo esc_url( wp_nonce_url( self::action_url( 'hcd_export_report' ), 'hcd_export_report' ) ); ?>">Pełny JSON</a></div></div>
                        <div class="hcd-filters"><label for="hcd-module-filter">Moduł</label><select id="hcd-module-filter"><option value="all">Wszystkie moduły</option><option value="core">Checkout i konfiguracja</option><option value="classic">Klasyczny checkout</option><option value="blocks">Checkout Block / Store API</option><option value="orders">Zamówienia / HPOS</option><option value="payments">Płatności</option><option value="shipping">Wysyłka</option><option value="conflicts">Konflikty / motyw</option><option value="logs">Logi techniczne</option><option value="readiness">Gotowość WooCommerce</option><option value="compatibility">Zgodność i wydanie</option><option value="integration">Testy integracyjne</option><option value="browser">Przeglądarka JS</option><option value="block_compat">Zgodność Checkout Block</option><option value="scenarios">Scenariusze koszyka</option><option value="visibility">Widoczność płatności</option><option value="store_api">Błędy Store API</option><option value="correlation">Korelacja dowodów</option><option value="events">Monitoring</option></select><label for="hcd-filter">Filtr wyników</label><select id="hcd-filter"><option value="all">Wszystkie</option><option value="critical">Krytyczne</option><option value="warning">Wymaga uwagi</option><option value="pass">Potwierdzone OK</option><option value="info">Informacyjne</option></select><span id="hcd-check-count"></span></div>
                        <div id="hcd-checks" class="hcd-checks"><div class="hcd-empty">Brak raportu. Wykonaj pierwszy skan na pulpicie.</div></div>
                    </section>
                    <section class="hcd-page" data-hcd-page="checkout-core" hidden>
                        <div class="hcd-section-head"><div><div class="hcd-eyebrow">CORE CHECKOUT DIAGNOSTICS · ZIP 2/5</div><h1>Silnik procesu zakupowego</h1><p>Osobne testy ścieżki klasycznej, Checkout Block, Store API i magazynu zamówień.</p></div><button type="button" id="hcd-run-core-scan" class="hcd-btn hcd-btn-main">Uruchom diagnostykę</button></div>
                        <div class="hcd-card hcd-card-muted"><strong>Zakres testu</strong><p>Sprawdzamy statycznie przypisanie stron, zduplikowane mechanizmy, ochronę hasłem, ustawienia kont, regulamin, rejestrację bloków i tras REST oraz HPOS. Nie wysyłamy żądań do Store API, nie tworzymy sesji klienta ani zamówień i nie przeprowadzamy płatności.</p></div>
                        <div id="hcd-core-findings" class="hcd-checks"><div class="hcd-empty">Wykonaj diagnostykę na pulpicie, aby zobaczyć wyniki silnika Checkout.</div></div>
                    </section>
                    <section class="hcd-page" data-hcd-page="payments" hidden>
                        <div class="hcd-section-head"><div><div class="hcd-eyebrow">PAYMENT INSPECTOR · ZIP 3/5</div><h1>Diagnostyka płatności</h1><p>Lista włączonych metod, ustawienia pobrania, ograniczenia callbacków i konkretne zalecenia.</p></div><button type="button" id="hcd-run-payments" class="hcd-btn hcd-btn-main">Testuj płatności</button></div>
                        <div class="hcd-card hcd-card-muted"><strong>Zakres analizy</strong><p>Odczyt aktywnych bramek i ich publicznych identyfikatorów. Nie odczytujemy kluczy API, tokenów, logów transakcji, konfiguracji prywatnych ani danych kart. Brak testu callbacków operatora i brak zewnętrznych żądań HTTP.</p></div>
                        <div class="hcd-mini-stats"><div><span>ZAREJESTROWANE BRAMKI</span><strong id="hcd-payment-registered">—</strong></div><div><span>AKTYWNE BRAMKI</span><strong id="hcd-payment-enabled">—</strong></div><div><span>USTALENIA PŁATNICZE</span><strong id="hcd-payment-checks">—</strong></div></div>
                        <div id="hcd-payments-findings" class="hcd-checks"><div class="hcd-empty">Uruchom diagnostykę, aby zobaczyć rzeczywiste ustawienia płatności.</div></div>
                    </section>
                    <section class="hcd-page" data-hcd-page="shipping" hidden>
                        <div class="hcd-section-head"><div><div class="hcd-eyebrow">SHIPPING INSPECTOR · ZIP 3/5</div><h1>Diagnostyka dostawy</h1><p>Strefy wysyłki, aktywne metody, reguły kuponów i konfiguracja krajów.</p></div><button type="button" id="hcd-run-shipping" class="hcd-btn hcd-btn-main">Testuj wysyłkę</button></div>
                        <div class="hcd-card hcd-card-muted"><strong>Zakres analizy</strong><p>Weryfikujemy dane konfiguracyjne WooCommerce, w tym strefę „Pozostałe regiony”. Nie tworzymy próbnego koszyka, nie weryfikujemy stawek dla podanego adresu ani nie kontaktujemy się z kurierami. Brak metody w strefie domyślnej może być zamierzony.</p></div>
                        <div class="hcd-mini-stats"><div><span>STREFY DODATKOWE</span><strong id="hcd-shipping-zones">—</strong></div><div><span>AKTYWNE METODY</span><strong id="hcd-shipping-enabled">—</strong></div><div><span>STREFY DO SPRAWDZENIA</span><strong id="hcd-shipping-issues">—</strong></div></div>
                        <div id="hcd-shipping-findings" class="hcd-checks"><div class="hcd-empty">Uruchom diagnostykę, aby sprawdzić strefy i metody dostaw.</div></div>
                    </section>
                    <section class="hcd-page" data-hcd-page="conflicts" hidden>
                        <div class="hcd-section-head"><div><div class="hcd-eyebrow">CONFLICT DETECTOR · ZIP 4/5</div><h1>Motywy i rozszerzenia</h1><p>Wersje nadpisań szablonów, hooki checkout i właściciele callbacków — bez wyłączania wtyczek.</p></div><button type="button" id="hcd-run-conflicts" class="hcd-btn hcd-btn-main">Testuj konflikty</button></div>
                        <div class="hcd-card hcd-card-muted"><strong>Jak interpretować wyniki?</strong><p>Różnica wersji szablonu jest faktem, ale nie dowodzi awarii. Kilka wtyczek korzystających z tego samego hooka to niekoniecznie konflikt. Bezpieczny skan nie wyłącza rozszerzeń, nie uruchamia callbacków i nie wykonuje JavaScript checkoutu. Test A/B wykonuj wyłącznie na stagingu.</p></div>
                        <div class="hcd-mini-stats"><div><span>NADPISANE SZABLONY</span><strong id="hcd-conflict-overrides">—</strong></div><div><span>STARSZE @VERSION</span><strong id="hcd-conflict-outdated">—</strong></div><div><span>WSPÓŁDZIELONE HOOKI</span><strong id="hcd-conflict-hooks">—</strong></div></div>
                        <div id="hcd-conflicts-findings" class="hcd-checks"><div class="hcd-empty">Uruchom diagnostykę, aby zobaczyć wyniki kontroli konfliktów.</div></div>
                        <div class="hcd-card"><div class="hcd-card-heading"><h2>Lista nadpisań szablonów</h2><span>Tylko ścieżki względne motywu, bez absolutnych ścieżek serwera</span></div><div class="hcd-table-wrap"><table class="hcd-table"><thead><tr><th>Motyw</th><th>Szablon</th><th>@version motywu</th><th>@version WooCommerce</th><th>Porównanie</th></tr></thead><tbody id="hcd-theme-overrides"><tr><td colspan="5">Brak danych</td></tr></tbody></table></div></div>
                        <div class="hcd-card"><div class="hcd-card-heading"><h2>Hooki WooCommerce Checkout</h2><span>Właściciele callbacków według pochodzenia pliku PHP</span></div><div class="hcd-table-wrap"><table class="hcd-table"><thead><tr><th>Hook</th><th>Callbacki</th><th>Wtyczki</th></tr></thead><tbody id="hcd-hook-owners"><tr><td colspan="3">Brak danych</td></tr></tbody></table></div></div>
                    </section>
                    <section class="hcd-page" data-hcd-page="logs" hidden>
                        <div class="hcd-section-head"><div><div class="hcd-eyebrow">LOG SIGNALS · ZIP 4/5</div><h1>Logi techniczne</h1><p>Wykrywanie zagregowanych sygnałów błędów bez zapisywania treści logów, adresów ani sekretów.</p></div><button type="button" id="hcd-run-logs" class="hcd-btn hcd-btn-main">Analizuj logi</button></div>
                        <div class="hcd-card hcd-card-muted"><strong>Ochrona prywatności</strong><p>Odczyt wyłącznie lokalnych plików logów WooCommerce: maksymalnie 8 najnowszych plików i po 64 KiB z końca. Do raportu trafiają jedynie liczniki kategorii, bez treści wpisów. Logi przechowywane w bazie danych, zewnętrzne oraz logi JS przeglądarki nie są analizowane.</p></div>
                        <div class="hcd-mini-stats"><div><span>ODCZYTANE PLIKI</span><strong id="hcd-log-files">—</strong></div><div><span>KRYTYCZNE SYGNAŁY</span><strong id="hcd-log-critical">—</strong></div><div><span>ERROR / WARNING</span><strong id="hcd-log-error">—</strong></div></div>
                        <div id="hcd-logs-findings" class="hcd-checks"><div class="hcd-empty">Uruchom skan, aby sprawdzić dostępne lokalne logi.</div></div>
                    </section>
                    <section class="hcd-page" data-hcd-page="repairs" hidden>
                        <div class="hcd-section-head"><div><div class="hcd-eyebrow">REPAIR GUIDANCE · ZIP 5/5</div><h1>Plan napraw i raporty</h1><p>Priorytetyzacja ustaleń, działania weryfikacyjne i korelacje bez zgadywania przyczyn.</p></div><button type="button" class="hcd-btn hcd-btn-main" id="hcd-run-repairs">Aktualizuj plan</button></div>
                        <div class="hcd-card hcd-card-muted"><strong>Priorytety nie są oceną bezpieczeństwa ani gwarancją awarii</strong><p><b>P1</b> — krytyczna i potwierdzona konfiguracja wymagająca działania; <b>P2</b> — inne potwierdzone problemy konfiguracyjne; <b>P3</b> — sygnały wymagające weryfikacji. Nie naprawiamy nic automatycznie. Najpierw kopia zapasowa i staging.</p></div>
                        <div class="hcd-mini-stats hcd-repair-stats"><div><span>P1 · PILNE</span><strong id="hcd-repair-p1">—</strong></div><div><span>P2 · KONFIGURACJA</span><strong id="hcd-repair-p2">—</strong></div><div><span>P3 · SPRAWDŹ</span><strong id="hcd-repair-p3">—</strong></div></div>
                        <div class="hcd-actions hcd-report-actions"><a class="hcd-btn" href="<?php echo esc_url( wp_nonce_url( self::action_url( 'hcd_export_report' ), 'hcd_export_report' ) ); ?>">Pełny raport JSON</a><a class="hcd-btn" href="<?php echo esc_url( wp_nonce_url( self::action_url( 'hcd_export_plan' ) . '&format=csv', 'hcd_export_plan' ) ); ?>">Plan CSV</a><a class="hcd-btn" href="<?php echo esc_url( wp_nonce_url( self::action_url( 'hcd_export_plan' ) . '&format=html', 'hcd_export_plan' ) ); ?>">Raport HTML do druku</a></div>
                        <div class="hcd-card"><div class="hcd-card-heading"><h2>Kolejność działań</h2><span id="hcd-repair-count">Wykonaj skan, aby utworzyć plan</span></div><div id="hcd-repair-list" class="hcd-repair-list"><div class="hcd-empty">Nie ma planu. Uruchom diagnostykę.</div></div></div>
                        <div class="hcd-card"><div class="hcd-card-heading"><h2>Współwystępujące ustalenia</h2><span>Bez automatycznego przypisywania przyczyny</span></div><div id="hcd-correlations" class="hcd-repair-list"><div class="hcd-empty">Nie ma danych korelacyjnych.</div></div></div>
                    </section>
                    <section class="hcd-page" data-hcd-page="readiness" hidden>
                        <div class="hcd-section-head"><div><div class="hcd-eyebrow">CHECKOUT READINESS · v1.5.0</div><h1>Gotowość i zmiany</h1><p>Test konfiguracji oraz porównanie dwóch kolejnych raportów po aktualizacjach lub poprawkach.</p></div><button type="button" id="hcd-run-readiness" class="hcd-btn hcd-btn-main">Nowy skan i porównanie</button></div>
                        <div class="hcd-card hcd-card-muted"><strong>Jak czytać porównanie?</strong><p>Wyniki dotyczą tylko skanera. Zniknięcie ostrzeżenia oznacza poprawę wykrytej konfiguracji, a nie dowiedzioną naprawę całego procesu płatności. Nowy test po aktualizacji wtyczki nie musi oznaczać nowej awarii. Skany nie tworzą zamówień i nie kontaktują się z operatorami płatności.</p></div>
                        <div class="hcd-mini-stats hcd-regression-stats"><div><span>NOWE OSTRZEŻENIA / WYKRYCIA</span><strong id="hcd-reg-new">—</strong></div><div><span>USTĄPIŁY W SKANIE</span><strong id="hcd-reg-cleared">—</strong></div><div><span>POGORSZENIE STATUSU</span><strong id="hcd-reg-worse">—</strong></div></div>
                        <div class="hcd-card"><div class="hcd-card-heading"><h2>Porównanie skanów</h2><span id="hcd-reg-period">Zrób dwa skany, aby uzyskać porównanie</span></div><p id="hcd-reg-summary" class="hcd-reg-note" role="status">Brak danych porównawczych.</p>
                            <div class="hcd-report-actions"><a class="hcd-btn" href="<?php echo esc_url( wp_nonce_url( self::action_url( 'hcd_export_comparison' ), 'hcd_export_comparison' ) ); ?>">Eksport porównania CSV</a></div>
                            <div class="hcd-table-wrap"><table class="hcd-table"><thead><tr><th>Zmiana</th><th>Moduł</th><th>Ustalenie</th><th>Poprzednio</th><th>Teraz</th></tr></thead><tbody id="hcd-reg-changes"><tr><td colspan="5">Brak porównania</td></tr></tbody></table></div>
                        </div>
                        <div class="hcd-card"><div class="hcd-card-heading"><h2>Kontrole gotowości WooCommerce</h2><span>Metadane lokalne: baza, endpointy, cron, debug</span></div><div id="hcd-readiness-findings" class="hcd-checks"><div class="hcd-empty">Uruchom skan, aby sprawdzić gotowość.</div></div></div>
                    </section>
                    <section class="hcd-page" data-hcd-page="compatibility" hidden>
                        <div class="hcd-section-head"><div><div class="hcd-eyebrow">COMPATIBILITY & RELEASE · v1.16.0</div><h1>Zgodność i wydanie</h1><p>Matryca API środowiska oraz checklist testów przed publikacją Open Source.</p></div><button type="button" id="hcd-run-compatibility" class="hcd-btn hcd-btn-main">Sprawdź zgodność</button></div>
                        <div class="hcd-card hcd-card-muted"><strong>Co znaczą wyniki?</strong><p>Potwierdzamy wyłącznie lokalne wersje i obecność funkcji. Sama dostępność API nie gwarantuje sprawności płatności, kompatybilności motywu, działania webhooków ani zgodności z Checkout Block. Brak API może oznaczać różnicę wersji lub etap inicjalizacji.</p></div>
                        <div class="hcd-mini-stats"><div><span>WORDPRESS</span><strong id="hcd-compat-wp">—</strong></div><div><span>WOOCOMMERCE</span><strong id="hcd-compat-wc">—</strong></div><div><span>PHP</span><strong id="hcd-compat-php">—</strong></div></div>
                        <div id="hcd-compat-findings" class="hcd-checks"><div class="hcd-empty">Uruchom skan, aby sprawdzić interfejsy API.</div></div>
                        <div class="hcd-card" id="hcd-release-gate">
                            <div class="hcd-card-heading"><h2>Bramka wydania · ZIP 17</h2><span>Weryfikacja przed publikacją, nie certyfikat zgodności</span></div>
                            <p id="hcd-release-state" role="status" aria-live="polite">Uruchom skan, aby odczytać bramkę wydania.</p>
                            <div class="hcd-mini-stats">
                                <div><span>AKTUALNIE POTWIERDZONE</span><strong id="hcd-release-passed">—</strong></div>
                                <div><span>NIEZALICZONE</span><strong id="hcd-release-failed">—</strong></div>
                                <div><span>DO REWALIDACJI</span><strong id="hcd-release-stale">—</strong></div>
                            </div>
                            <div id="hcd-release-reasons" class="hcd-checks"><div class="hcd-empty">Wyniki pojawią się po skanie.</div></div>
                            <p>Każda bramka płatności wymaga osobnego testu na stagingu i w sandboxie. Nawet kompletna checklista nie zatwierdza automatycznie publikacji.</p>
                            <p><a class="hcd-btn" href="<?php echo esc_url( wp_nonce_url( self::action_url( 'hcd_export_release_snapshot' ), 'hcd_export_release_snapshot' ) ); ?>">Pobierz anonimowy pakiet wsparcia (JSON)</a></p>
                            <p>Pakiet zawiera wyłącznie wersje platformy, liczby ustaleń i statusy checklisty. Przed publikacją zgłoszenia przejrzyj plik. Nie zawiera adresu witryny, identyfikatorów klientów, treści logów ani ścieżek serwera.</p>
                        </div>
                        <div class="hcd-card"><div class="hcd-card-heading"><h2>Lista testów przed publikacją</h2><span>Do wykonania ręcznie na stagingu</span></div>
                            <ol class="hcd-release-list"><li>Utwórz kopię testową i kopię zapasową; odłącz prawdziwe płatności.</li>
                            <li>Uruchom checkout klasyczny jako gość i zalogowany klient (sandbox operatora).</li>
                            <li>Uruchom Checkout Block, dodatkowe pola i płatności cyfrowe oraz fizyczne (sandbox).</li>
                            <li>Sprawdź HPOS oraz statusy zamówień i kolejkę Action Scheduler.</li>
                            <li>Przetestuj zgodność z aktywnymi wtyczkami checkoutu, dostawy i motywem.</li>
                            <li>Sprawdź aktualizację z v1.9.0, raport JSON/CSV/HTML, uprawnienia i wyłączenie obserwatora na produkcji.</li>
                            <li>Przejrzyj logi PHP, konsolę JS i wyniki skanów, usuń regresje przed publikacją.</li></ol>
                            <p>Ta lista nie jest automatycznie oznaczana jako zaliczona. Potwierdzenie testu wymaga rzeczywistego wykonania scenariusza.</p>
                        </div>
                    </section>
                    <section class="hcd-page" data-hcd-page="integration" hidden>
                        <div class="hcd-section-head"><div><div class="hcd-eyebrow">INTEGRATION LAB · v1.12.0</div><h1>Testy integracyjne</h1><p>Ręczna lista rzeczywiście wykonanych testów na kopii sklepu. Nie zastępuje automatycznej diagnostyki.</p></div><div class="hcd-actions"><button type="button" id="hcd-run-integration" class="hcd-btn">Odśwież raport</button><a class="hcd-btn" href="<?php echo esc_url( wp_nonce_url( self::action_url( 'hcd_export_integration_tests' ), 'hcd_export_integration_tests' ) ); ?>">Eksport JSON</a></div></div>
                        <?php if ( isset( $_GET['integration_saved'] ) ) : ?><div class="hcd-notice hcd-success">Zapisano ręczne wyniki testów. Wykonaj nowy skan, aby umieścić je w raporcie diagnostycznym.</div><?php endif; ?>
                        <div class="hcd-card hcd-card-muted"><strong>Środowisko testowe i bezpieczeństwo</strong><p>Oznaczenie „zaliczony” jest deklaracją osoby testującej, nie wynikiem symulacji. Wyniki starsze niż 30 dni, z poprzedniej wersji lub po zmianie wykrytej konfiguracji otrzymują status wymagający ponownego testu. Testy płatności wykonuj wyłącznie w trybie sandbox na stagingu. Checkout Doctor sam nie tworzy zamówień, nie obciąża kart i nie automatyzuje operatorów. Nie wpisuj danych klientów ani kluczy płatności — formularz zapisuje wyłącznie wybrane statusy.</p></div>
                        <div class="hcd-mini-stats hcd-int-stats"><div><span>ZALICZONE RĘCZNIE</span><strong id="hcd-int-passed"><?php echo (int) $integration['summary']['passed']; ?>/7</strong></div><div><span>ZGŁOSZONE PROBLEMY</span><strong id="hcd-int-failed"><?php echo (int) $integration['summary']['failed']; ?></strong></div><div><span>NIEPRZETESTOWANE / ZABLOKOWANE</span><strong id="hcd-int-pending"><?php echo (int) ( $integration['summary']['untested'] + $integration['summary']['blocked'] ); ?></strong></div><div><span>AKTUALNE RĘCZNE POTWIERDZENIA</span><strong><?php echo (int) $integration['current_manual_passed']; ?>/7</strong></div></div>
                        <div class="hcd-card hcd-card-muted"><strong>Weryfikacja aktualności</strong><p>Aktualny wynik ręczny: <?php echo (int) $integration['verification_summary']['current_manual']; ?> · Zmienione środowisko: <?php echo (int) $integration['verification_summary']['environment_changed']; ?> · Starsze niż 30 dni: <?php echo (int) $integration['verification_summary']['expired']; ?> · Archiwalne bez potwierdzenia: <?php echo (int) $integration['verification_summary']['legacy_unverified']; ?>. Zmiana wersji WordPress/WooCommerce/PHP, motywu lub znanej wersji aktywnej wtyczki powoduje wymóg ponownego sprawdzenia. Wykonaj test przed zaznaczeniem potwierdzenia.</p></div>
                        <form method="post" action="<?php echo esc_url( self::action_url( 'hcd_save_integration_tests' ) ); ?>" class="hcd-card hcd-integration-form">
                            <?php wp_nonce_field( 'hcd_save_integration_tests' ); ?>
                            <div class="hcd-card-heading"><h2>Scenariusze do sprawdzenia na stagingu</h2><span>7 kroków · statusy zapisywane lokalnie</span></div>
                            <?php foreach ( HCD_Integration_Tests::scenarios() as $scenario_id => $scenario ) :
                                $entry = $integration['entries'][ $scenario_id ]; ?>
                                <div class="hcd-test-step"><div><strong><?php echo esc_html( $scenario['title'] ); ?></strong><p><?php echo esc_html( $scenario['instruction'] ); ?></p><small><?php echo $entry['updated_utc'] ? esc_html( 'Ręcznie oznaczono UTC: ' . $entry['updated_utc'] ) : 'Dotąd niepotwierdzony'; ?> · <?php
                                    $verification_labels = array( 'current_manual' => 'Potwierdzenie aktualne', 'environment_changed' => 'Środowisko zmienione — ponów test', 'expired' => 'Minęło 30 dni — ponów test', 'legacy_unverified' => 'Historyczny wynik — ponów test', 'not_tested' => 'Brak testu' );
                                    echo esc_html( $verification_labels[ $entry['verification'] ] ); ?></small></div>
                                    <label><span class="screen-reader-text"><?php echo esc_html( $scenario['title'] ); ?> — status</span><select name="hcd_scenario[<?php echo esc_attr( $scenario_id ); ?>]">
                                        <option value="untested" <?php selected( $entry['status'], 'untested' ); ?>>Nie testowano</option>
                                        <option value="passed" <?php selected( $entry['status'], 'passed' ); ?>>Zaliczony</option>
                                        <option value="failed" <?php selected( $entry['status'], 'failed' ); ?>>Problem</option>
                                        <option value="blocked" <?php selected( $entry['status'], 'blocked' ); ?>>Zablokowany</option>
                                    </select></label>
                                    <label class="hcd-reconfirm"><input type="checkbox" name="hcd_reconfirm[<?php echo esc_attr( $scenario_id ); ?>]" value="1" /> Potwierdź ponownie po rzeczywistym teście</label>
                                </div>
                            <?php endforeach; ?>
                            <p>Sam zapis formularza NIE odnawia daty ani potwierdzenia. Zaznacz potwierdzenie osobno po ponownym wykonaniu testu.</p>
                            <button type="submit" class="hcd-btn hcd-btn-main">Zapisz wyniki 7 testów</button>
                        </form>
                        <div id="hcd-integration-findings" class="hcd-checks"><div class="hcd-empty">Wynik checklisty pojawi się po uruchomieniu diagnostyki.</div></div>
                    </section>
                    <section class="hcd-page" data-hcd-page="block-compat" hidden>
                        <div class="hcd-section-head"><div><div class="hcd-eyebrow">BLOCK COMPATIBILITY · v1.7.0</div><h1>Zgodność Checkout Block</h1><p>Inwentaryzacja rejestru integracji płatności i definicji dodatkowych pól WooCommerce.</p></div><button type="button" id="hcd-run-block-compat" class="hcd-btn hcd-btn-main">Testuj integracje blokowe</button></div>
                        <div class="hcd-card hcd-card-muted"><strong>Ważne ograniczenie diagnostyki</strong><p>Analizujemy wyłącznie lokalne definicje i rejestry po stronie PHP. Sam brak identycznej nazwy w rejestrze nie oznacza, że bramka nie obsługuje Checkout Block. Widoczność płatności zależy od rejestracji JavaScript, koszyka, kraju, waluty i warunków operatora. Nie czytamy wartości dodatkowych pól klientów, kluczy API ani danych zamówień. Nie uruchamiamy płatności i nie wysyłamy żądań zewnętrznych.</p></div>
                        <div class="hcd-mini-stats"><div><span>WŁĄCZONE BRAMKI</span><strong id="hcd-block-gateways">—</strong></div><div><span>INTEGRACJE PHP</span><strong id="hcd-block-integrations">—</strong></div><div><span>DODATKOWE POLA</span><strong id="hcd-block-fields">—</strong></div></div>
                        <div class="hcd-card"><div class="hcd-card-heading"><h2>Wyniki kompatybilności</h2><span>Wskazówki do testu, a nie werdykt o awarii</span></div><div id="hcd-block-findings" class="hcd-checks"><div class="hcd-empty">Uruchom diagnostykę, aby odczytać integracje.</div></div></div>
                        <div class="hcd-card"><div class="hcd-card-heading"><h2>Definicje dodatkowych pól</h2><span>Najwyżej 30 identyfikatorów, bez treści formularzy</span></div><div class="hcd-table-wrap"><table class="hcd-table"><thead><tr><th>Identyfikator</th><th>Obszar</th><th>Typ</th><th>Wymagane</th></tr></thead><tbody id="hcd-block-field-rows"><tr><td colspan="4">Brak danych</td></tr></tbody></table></div></div>
                        <div class="hcd-card hcd-card-muted"><strong>Kontrola ręczna po skanie</strong><p>Na stagingu otwórz checkout jako gość i zalogowany kupujący, sprawdź widoczność bramek i pól dla różnych koszyków oraz przetestuj płatności wyłącznie w sandboxie operatora. Ten test pasywny nie może potwierdzić poprawności procesu zapłaty.</p></div>
                    </section>
                    <section class="hcd-page" data-hcd-page="scenarios" hidden>
                        <div class="hcd-section-head"><div><div class="hcd-eyebrow">STATIC SCENARIO AUDIT · v1.8.0</div><h1>Scenariusze koszyka</h1><p>Warunki dostępności pobrania i darmowej wysyłki — wyłącznie na podstawie konfiguracji.</p></div><button type="button" id="hcd-run-scenarios" class="hcd-btn hcd-btn-main">Analizuj scenariusze</button></div>
                        <div class="hcd-card hcd-card-muted"><strong>Zakres diagnozy — ważne</strong><p>Wtyczka NIE buduje sztucznego koszyka, nie liczy stawek, nie wywołuje is_available() ani JavaScript canMakePayment(). To analiza ustawień. Wnioski o widoczności bramek dla klienta wymagają testu na stagingu. W trakcie skanowania nie modyfikujemy sesji kupującego ani nie uruchamiamy płatności.</p></div>
                        <div class="hcd-mini-stats"><div><span>WŁĄCZONE BRAMKI</span><strong id="hcd-scen-gateway-count">—</strong></div><div><span>WARIANTY COD</span><strong id="hcd-scen-case-count">—</strong></div><div><span>REGUŁY DARMOWEJ DOSTAWY</span><strong id="hcd-scen-ship-count">—</strong></div></div>
                        <div class="hcd-card"><div class="hcd-card-heading"><h2>Wskazówki dla scenariuszy</h2><span>To nie wyniki płatności ani symulacja checkoutu</span></div><div class="hcd-table-wrap"><table class="hcd-table"><thead><tr><th>Wariant koszyka</th><th>Bramka</th><th>Ocena reguły</th><th>Uzasadnienie</th></tr></thead><tbody id="hcd-scenario-rows"><tr><td colspan="4">Uruchom analizę.</td></tr></tbody></table></div></div>
                        <div class="hcd-card"><div class="hcd-card-heading"><h2>Reguły darmowej wysyłki</h2><span>Strefa i instancja bez adresów klientów</span></div><div class="hcd-table-wrap"><table class="hcd-table"><thead><tr><th>Strefa</th><th>Instancja</th><th>Warunek</th><th>Ocena</th></tr></thead><tbody id="hcd-scenario-shipping-rows"><tr><td colspan="4">Uruchom analizę.</td></tr></tbody></table></div></div>
                        <div id="hcd-scenario-findings" class="hcd-checks"><div class="hcd-empty">Brak raportu scenariuszy.</div></div>
                        <div class="hcd-card hcd-card-muted"><strong>Plan ręcznej weryfikacji</strong><p>Na stagingu sprawdź osobno: produkt cyfrowy, produkt z dostawą standardową, odbiór osobisty, darmową dostawę z kuponem oraz z progiem kwotowym. Potem przetestuj Checkout Block i klasyczny checkout. Płatności wyłącznie w trybie sandbox operatora.</p></div>
                    </section>
                    <section class="hcd-page" data-hcd-page="visibility" hidden>
                        <div class="hcd-section-head"><div><div class="hcd-eyebrow">PAYMENT VISIBILITY LAB · v1.9.0</div><h1>Widoczność metod płatności</h1><p>Kontrolowana obserwacja standardowych kontrolek płatności na stronie checkoutu — tylko w środowisku testowym.</p></div><button type="button" id="hcd-run-visibility" class="hcd-btn hcd-btn-main">Odśwież wynik testu</button></div>
                        <div class="hcd-card hcd-card-muted"><strong>Bezpieczeństwo i ograniczenia</strong><p>Test jest domyślnie wyłączony i działa wyłącznie po oznaczeniu WordPress jako local, development lub staging oraz po zalogowaniu administratora. Nie wywołuje funkcji canMakePayment, nie przechwytuje wartości formularzy, nie testuje operatora płatności ani nie tworzy zamówień. Rozpoznaje tylko standardowe kontrolki HTML — własne bramki i przyciski express mogą nie zostać policzone.</p></div>
                        <div class="hcd-mini-stats"><div><span>PRÓBKI STAGING</span><strong id="hcd-vis-samples">—</strong></div><div><span>OSTATNIA LICZBA OPCJI</span><strong id="hcd-vis-methods">—</strong></div><div><span>TRYB CHECKOUT</span><strong id="hcd-vis-mode">—</strong></div></div>
                        <div class="hcd-card"><div class="hcd-card-heading"><h2>Jak wykonać test?</h2><span>Bez składania zamówienia</span></div><p>1. Na kopii testowej ustaw <code>WP_ENVIRONMENT_TYPE</code> na <code>staging</code> (lub development/local). 2. Włącz obserwator w Ustawieniach. 3. Dodaj testowy produkt do własnego koszyka. 4. Zalogowany jako administrator otwórz stronę checkoutu na stagingu i poczekaj ok. 5 sekund. 5. Wróć tutaj i kliknij „Odśwież wynik testu”. Test jako gość wykonaj osobno, bez włączonego obserwatora.</p></div>
                        <div id="hcd-visibility-findings" class="hcd-checks"><div class="hcd-empty">Brak wyniku. Uruchom diagnostykę.</div></div>
                        <div class="hcd-card"><div class="hcd-card-heading"><h2>Ostatnie pomiary</h2><span>Maksymalnie 12 próbek, 7 dni</span></div><div class="hcd-table-wrap"><table class="hcd-table"><thead><tr><th>UTC</th><th>Checkout</th><th>Koszyk</th><th>Rozpoznane opcje</th></tr></thead><tbody id="hcd-vis-rows"><tr><td colspan="4">Brak pomiarów.</td></tr></tbody></table></div></div>
                        <form method="post" action="<?php echo esc_url( self::action_url( 'hcd_clear_payment_visibility' ) ); ?>" onsubmit="return confirm('Usunąć próbki widoczności płatności?');">
                            <?php wp_nonce_field( 'hcd_clear_payment_visibility' ); ?><button type="submit" class="hcd-btn hcd-btn-danger">Wyczyść pomiary</button>
                        </form>
                    </section>
                    <section class="hcd-page" data-hcd-page="store-api" hidden>
                        <div class="hcd-section-head"><div><div class="hcd-eyebrow">STORE API RESPONSE LAB · v1.13.0</div><h1>Błędy Store API</h1><p>Anonimowe kody HTTP z własnego testu na stagingu — bez odczytywania danych zamówienia.</p></div><button type="button" id="hcd-run-store-api" class="hcd-btn hcd-btn-main">Odśwież diagnostykę</button></div>
                        <div class="hcd-card hcd-card-muted"><strong>Jak działa obserwacja?</strong><p>Domyślnie wyłączona, tylko local/development/staging i zalogowany administrator lub menedżer WooCommerce. Rejestruje wyłącznie obszar (cart/checkout), metodę HTTP, status 400–599 i czas UTC. Nie pobiera URL, treści formularzy, nagłówków, odpowiedzi ani identyfikatorów zamówienia. Nie wykonuje żadnego własnego żądania do Store API. Nie obejmuje wywołań XMLHttpRequest (XHR), wyłącznie fetch.</p></div>
                        <div class="hcd-mini-stats"><div><span>PRÓBKI</span><strong id="hcd-store-count">—</strong></div><div><span>ODPOWIEDZI 4XX</span><strong id="hcd-store-4xx">—</strong></div><div><span>ODPOWIEDZI 5XX</span><strong id="hcd-store-5xx">—</strong></div></div>
                        <div class="hcd-card"><h2>Test na stagingu</h2><p>1. Na kopii sklepu ustaw WP_ENVIRONMENT_TYPE na staging. 2. Włącz obserwator w Ustawieniach. 3. Zalogowany jako administrator otwórz stronę checkoutu z produktem testowym. 4. Wykonuj wyłącznie bezpieczne kroki; jeśli testujesz składanie zamówienia, użyj wyłącznie sandboxa operatora. 5. Wróć do panelu i kliknij „Odśwież diagnostykę”. Kody HTTP 4xx mogą pochodzić z normalnej walidacji — sam status nie jest dowodem awarii.</p></div>
                        <div id="hcd-store-findings" class="hcd-checks"><div class="hcd-empty">Uruchom diagnostykę, aby zobaczyć wyniki.</div></div>
                        <div class="hcd-card"><div class="hcd-card-heading"><h2>Ostatnie błędne odpowiedzi</h2><span>Limit 24 zdarzeń, automatyczne wygaśnięcie do 7 dni</span></div><div class="hcd-table-wrap"><table class="hcd-table"><thead><tr><th>UTC</th><th>Endpoint</th><th>Metoda</th><th>Kod HTTP</th></tr></thead><tbody id="hcd-store-rows"><tr><td colspan="4">Brak próbek.</td></tr></tbody></table></div></div>
                        <form method="post" action="<?php echo esc_url( self::action_url( 'hcd_clear_store_api_observations' ) ); ?>" onsubmit="return confirm('Usunąć wszystkie obserwacje Store API?');">
                            <?php wp_nonce_field( 'hcd_clear_store_api_observations' ); ?><button type="submit" class="hcd-btn hcd-btn-danger">Wyczyść obserwacje</button>
                        </form>
                    </section>
                    <section class="hcd-page" data-hcd-page="correlation" hidden>
                        <div class="hcd-section-head"><div><div class="hcd-eyebrow">EVIDENCE CORRELATION · v1.14.0</div><h1>Korelacja dowodów</h1><p>Przekrojowe zestawienie błędów Store API, lokalnych logów i potwierdzeń testera — bez dostępu do danych klientów.</p></div><button type="button" id="hcd-run-correlation" class="hcd-btn hcd-btn-main">Zaktualizuj analizę</button></div>
                        <div class="hcd-card hcd-card-muted"><strong>Ważne ograniczenie</strong><p>Wyniki obejmują różne przedziały czasu: próbki Store API (do 7 dni), końcowe fragmenty lokalnych logów (bez porównywania dat) oraz ręczne scenariusze. Równoczesne wystąpienie sygnałów nie oznacza tego samego żądania ani wspólnej przyczyny. Skala wyniku jest informacyjna, a nie oceną bezpieczeństwa lub gotowości do sprzedaży.</p></div>
                        <div class="hcd-mini-stats"><div><span>ŹRÓDŁA Z SYGNAŁAMI</span><strong id="hcd-corr-sources">—</strong></div><div><span>BŁĘDY STORE API</span><strong id="hcd-corr-http">—</strong></div><div><span>SYGNAŁY W LOGACH</span><strong id="hcd-corr-logs">—</strong></div><div><span>TESTY DO PONOWIENIA</span><strong id="hcd-corr-retest">—</strong></div></div>
                        <div id="hcd-correlation-findings" class="hcd-checks"><div class="hcd-empty">Uruchom diagnostykę, aby zestawić dowody.</div></div>
                        <div class="hcd-card"><h2>Bezpieczny tok weryfikacji na stagingu</h2><ol id="hcd-correlation-steps"><li>Przeprowadź test checkoutu z bramką sandbox.</li><li>Porównaj kod HTTP i godzinę UTC z prywatnymi logami WooCommerce.</li><li>Zweryfikuj wynik ręcznej checklisty.</li></ol><p>Raport JSON i HTML obejmuje tylko zbiorcze wyniki. Nie wysyłaj logów z danymi klientów w publicznych zgłoszeniach.</p></div>
                    </section>
                    <section class="hcd-page" data-hcd-page="browser" hidden>
                        <div class="hcd-section-head"><div><div class="hcd-eyebrow">BROWSER CHECKOUT PROBE · v1.6.0</div><h1>Przeglądarka JS</h1><p>Obserwacje realnego renderowania checkoutu w sesji własnego testu administratora.</p></div><button type="button" id="hcd-run-browser" class="hcd-btn hcd-btn-main">Odśwież diagnostykę</button></div>
                        <div class="hcd-card hcd-card-muted"><strong>Tryb bezpiecznego testowania</strong><p>Obserwator jest domyślnie wyłączony. Po włączeniu w Ustawieniach działa wyłącznie dla zalogowanego administratora lub menedżera WooCommerce na stronie checkoutu. Zlicza tylko kategorie błędów JS, obecność standardowego formularza oraz czasy zasobów Store API. Nie zapisuje treści błędów, adresów URL, danych klientów, pól formularzy ani numerów zamówień. Pojedynczy zasób Store API może być odczytem, nie próbą płatności. Próbki automatycznie wygasają po maksymalnie 7 dniach.</p></div>
                        <div class="hcd-mini-stats"><div><span>PRÓBKI</span><strong id="hcd-browser-samples">—</strong></div><div><span>SYGNAŁY JS</span><strong id="hcd-browser-errors">—</strong></div><div><span>WOLNE ZASOBY STORE API</span><strong id="hcd-browser-slow">—</strong></div></div>
                        <div class="hcd-card"><h2>Jak przetestować?</h2><p>1. Włącz „Test przeglądarkowy” w Ustawieniach. 2. Zalogowany jako administrator przejdź na stronę zamówienia na stagingu (najlepiej z produktem testowym w koszyku). 3. Odczekaj 5 sekund. 4. Wróć tutaj i kliknij „Odśwież diagnostykę”.</p></div>
                        <div id="hcd-browser-findings" class="hcd-checks"><div class="hcd-empty">Brak raportu. Uruchom diagnostykę.</div></div>
                        <form method="post" action="<?php echo esc_url( self::action_url( 'hcd_clear_browser' ) ); ?>" onsubmit="return confirm('Usunąć wszystkie próbki przeglądarkowe?');">
                            <?php wp_nonce_field( 'hcd_clear_browser' ); ?><button class="hcd-btn hcd-btn-danger" type="submit">Wyczyść próbki JS</button>
                        </form>
                    </section>
                    <section class="hcd-page" data-hcd-page="events" hidden>
                        <div class="hcd-section-head"><div><div class="hcd-eyebrow">EVENT LEDGER</div><h1>Dziennik zdarzeń</h1><p>Anonimowe metadane checkout, bez adresów IP, danych klientów, treści formularzy i numerów zamówień.</p></div><a class="hcd-btn" href="<?php echo esc_url( wp_nonce_url( self::action_url( 'hcd_export_events' ), 'hcd_export_events' ) ); ?>">Eksport CSV</a></div>
                        <div class="hcd-card"><div class="hcd-card-heading"><h2>Ostatnie zdarzenia</h2><span>maks. 50 rekordów na ekranie</span></div><div class="hcd-table-wrap"><table class="hcd-table"><thead><tr><th>Czas UTC</th><th>Zdarzenie</th><th>Źródło</th><th>Kod techniczny</th></tr></thead><tbody id="hcd-events-rows"><tr><td colspan="4">Brak zdarzeń</td></tr></tbody></table></div></div>
                        <div class="hcd-card hcd-card-muted"><strong>Uwaga dotycząca interpretacji</strong><p>Walidacja formularza nie musi oznaczać błędu programu. Utworzenie zamówienia nie jest potwierdzeniem płatności. Odrzucona płatność nie przesądza o usterce bramki. Dziennik ma wspierać analizę przyczyn.</p></div>
                        <form method="post" action="<?php echo esc_url( self::action_url( 'hcd_clear_events' ) ); ?>" onsubmit="return confirm('Na pewno trwale wyczyścić dziennik zdarzeń?');">
                            <?php wp_nonce_field( 'hcd_clear_events' ); ?><button class="hcd-btn hcd-btn-danger" type="submit">Usuń wszystkie zdarzenia</button>
                        </form>
                    </section>
                    <section class="hcd-page" data-hcd-page="settings" hidden>
                        <div class="hcd-section-head"><div><div class="hcd-eyebrow">PRIVACY & CONTROL</div><h1>Ustawienia</h1><p>Monitoring jest dobrowolny. Skan konfiguracji działa także bez niego.</p></div></div>
                        <form method="post" class="hcd-card hcd-settings" action="<?php echo esc_url( self::action_url( 'hcd_save_settings' ) ); ?>">
                            <?php wp_nonce_field( 'hcd_settings' ); ?>
                            <h2>Monitoring checkout</h2><p>Przechwytuje tylko typ zdarzenia, zanonimizowany kod techniczny i czas. Brak adresów IP, adresów e-mail, identyfikatorów zamówień oraz danych kart.</p>
                            <label class="hcd-switch"><input type="checkbox" name="hcd_monitoring_enabled" value="yes" <?php checked( $monitoring ); ?>><span>Włącz rejestrowanie nowych zdarzeń WooCommerce</span></label>
                            <h2>Test przeglądarkowy (tylko administrator)</h2><p>Wyłącznie kategorie zdarzeń i koszyki czasowe zasobów; bez surowych błędów JS oraz danych kupujących. Domyślnie wyłączony. Wyłączenie usuwa zapisane próbki.</p>
                            <label class="hcd-switch"><input type="checkbox" name="hcd_browser_observer_enabled" value="yes" <?php checked( HCD_Browser_Probe::enabled() ); ?>><span>Włącz obserwator w moim zalogowanym checkout (zalecany staging)</span></label>
                            <h2>Widoczność płatności — tylko staging</h2><p>Opcjonalny test wyłącznie dla administratora. Nie uruchamia płatności ani funkcji dostępności bramek, zapisuje tylko liczbę standardowych kontrolek. Gdy środowisko ma typ production, opcja jest wymuszenie wyłączona.</p>
                            <label class="hcd-switch"><input type="checkbox" name="hcd_payment_visibility_enabled" value="yes" <?php checked( HCD_Payment_Visibility::enabled() ); ?> <?php disabled( ! HCD_Payment_Visibility::staging() ); ?>><span>Włącz obserwator widoczności metod płatności na stagingu</span></label>
                            <?php if ( ! HCD_Payment_Visibility::staging() ) : ?><p><strong>Blokada bezpieczeństwa:</strong> WordPress zgłasza typ środowiska production. Ustaw <code>WP_ENVIRONMENT_TYPE</code> na <code>staging</code> w kopii testowej. Nie zmieniaj typu środowiska produkcyjnego.</p><?php endif; ?>
                            <h2>Błędy Store API — tylko staging</h2><p>Własny test administratora. Zapis wyłącznie statusów HTTP 4xx/5xx w istniejących wywołaniach fetch. Wyłączony na produkcji i domyślnie nieaktywny. Po wyłączeniu próbki są usuwane.</p>
                            <label class="hcd-switch"><input type="checkbox" name="hcd_store_api_observer_enabled" value="yes" <?php checked( HCD_Store_API_Observer::enabled() ); ?> <?php disabled( ! HCD_Store_API_Observer::staging() ); ?>><span>Włącz obserwację odpowiedzi Store API na stagingu</span></label>
                            <label for="hcd-retention" class="hcd-select-label">Automatyczne przechowywanie zdarzeń</label>
                            <select id="hcd-retention" name="hcd_retention_days"><option value="7" <?php selected( $days, 7 ); ?>>7 dni</option><option value="14" <?php selected( $days, 14 ); ?>>14 dni</option><option value="30" <?php selected( $days, 30 ); ?>>30 dni</option></select>
                            <div class="hcd-divider"></div><button type="submit" class="hcd-btn hcd-btn-main">Zapisz ustawienia</button>
                        </form>
                        <div class="hcd-card"><h2>Zakres wersji 1.11.0 · ZIP 12</h2><p>Wtyczka nie inicjuje transakcji, nie automatyzuje przechodzenia przez prawdziwą kasę, nie potwierdza poprawności kluczy API bramek i nie sprawdza dostawcy płatności zewnętrznym żądaniem. Nie próbuje też przypisywać każdej nieudanej płatności do błędu programistycznego.</p><p>Moduły płatności i wysyłki przeprowadzają tylko lokalny odczyt konfiguracji. Konflikty są wskazówkami do testu na stagingu, a agregaty logów nie dowodzą, że błędy dotyczą checkoutu. Plan napraw nie wykonuje zmian i nie przypisuje automatycznie przyczyn. Skan nie odczytuje treści logów do raportu.</p></div>
                    </section>
                    <footer class="hcd-footer">HETERODYNA TEAM SOFTWARE <span>·</span> CHECKOUT DOCTOR v<?php echo esc_html( HCD_VERSION ); ?> <span>·</span> OPEN SOURCE / GPL-2.0-or-later</footer>
                </main>
            </div>
        </div>
        <?php
    }
}
