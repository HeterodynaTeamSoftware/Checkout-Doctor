=== Heterodyna Checkout Doctor ===
Contributors: heterodynateamsoftware
Tags: woocommerce, checkout, diagnostics, conflicts, logs, hpos
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.16.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Open-source, read-only WooCommerce diagnostics for checkout, payment settings, shipping zones, Store API registry and HPOS.

== Description ==
Version 1.10.0 added a read-only runtime compatibility inventory and a manually executed staging release checklist. It compares supported minimum WordPress/PHP versions and inventories basic WooCommerce PHP functions, REST and Blocks integrations, and Action Scheduler. Availability of an API does NOT prove integration compatibility; no payments or order changes occur.

Heterodyna Checkout Doctor is a locally executed WooCommerce diagnostics plugin featuring original Heterodyna Team Software branding and a dark admin UI. It checks assigned checkout and cart pages, HTTPS URL scheme, classic and block-based checkout structure, registered Store API routes, and HPOS settings.

Version 1.2.0 added dedicated Payments and Shipping panels, enabled payment gateway identifiers, COD shipping restrictions, additional and default shipping zones, counts of enabled methods, free shipping coupon inconsistencies, target country configuration and shipping-calculator status.

Version 1.3.0 adds theme override version comparisons, hook callback owner inventories and bounded WC file-log signal classification. No log text or tokens are stored in reports. Sharing a hook is not evidence of a conflict. Client-side JavaScript checks require an optional administrator-only browser probe.

Results distinguish verified configuration facts from signals requiring further confirmation. Each finding contains a technical observation, interpretation and recommended next steps.

The plugin never initiates a real payment, creates orders, verifies provider credentials, attempts external callbacks or reads customer information. Log content is only processed in memory to derive finite severity counters; it is never included in the report. Its opt-in, limited event monitoring stores only fixed event categories and UTC times. The report and event export are admin-only.

Version 1.4.0 adds a remediation plan and offline HTML/CSV report exports; recommendations never change shop settings automatically. Correlations are co-occurrences, not root-cause claims.

Version 1.5.0 adds a local readiness tab (database/code version comparison, checkout endpoint collision, WP-Cron and PHP display flags), comparisons of the last two scans and a spreadsheet-safe comparison CSV export. An added check after plugin upgrade is not classified as a newly caused store defect; disappearing warnings do not prove a payment issue is resolved.

Version 1.7.0 adds a read-only Checkout Block compatibility tab: payment integration registry, enabled gateway ID comparison (heuristic only), and additional checkout field definitions by location/type without reading values. Missing ID matches do not prove gateway incompatibility.

Version 1.6.0 adds an optional, administrator-only browser probe on the checkout page: standard form rendering, categorical JavaScript error signals, and coarse latency buckets for Store API resources. It is off by default; no shoppers are monitored, no raw errors or request/response contents are saved. Up to 24 samples are retained in a seven-day transient (the expiry refreshes when new samples arrive). Results are diagnostic signals, not proof of payment failure.

Version 1.9.0 adds an optional, staging-only payment-method visibility probe. Only authenticated administrators/managers on local/development/staging can collect bounded standard payment radio/option counts (no form values). Production use is blocked. No payment or checkout submission occurs. Observe your own staging checkout session, then refresh the Visibility tab. Results are indicative only; custom/express gateway interfaces may be missed.

Version 1.11.0 adds an explicit 7-step manual staging integration ledger: fixed status values and UTC timestamps only, no free-text or personal data. Reports include a snapshot of declared manual coverage, not proof of automated compatibility. Scanner faults in selected modules are contained.

Version 1.12.0 ties explicit manual staging attestations to a privacy-preserving hashed environment snapshot (WordPress, WooCommerce, PHP, theme and active plugin versions when available). Previous results remain visible but require retesting after an environment change, or after 30 days. Saving an unchanged form does not silently re-attest; administrators must explicitly confirm each tested scenario again. No automated payment tests are performed.

== Installation ==
1. Upload ZIP via Plugins > Add New > Upload Plugin.
2. Activate, or confirm replacement of a previous Checkout Doctor release.
3. Open WooCommerce > Checkout Doctor.
4. Run a scan from the dashboard or Payments / Shipping / Conflicts / Technical Logs tabs.
5. Optionally enable coarse event monitoring in Settings.
6. To test browser diagnostics, enable the separate browser observer and visit checkout *as a logged-in store administrator* on staging. Return to Browser JS and refresh diagnostic results.

== Frequently Asked Questions ==
= Does it send test payments or contact payment operators? =
No. Only local configuration is inspected; no external HTTP calls are made.
= Does an enabled gateway mean it works? =
No. Gateway status only confirms its enabled setting.
= Why is the default shipping zone empty? =
The store might intentionally refuse deliveries outside its configured regions; this is informational.
= What data is captured? =
By default the event ledger is off. After opting in, only fixed event classes and UTC timestamps are stored, without PII or orders data.

Version 1.8.0 adds read-only cart scenario guidance: enabled payment gateway inventory, COD virtual goods / standard shipping / pickup configuration hints and free-shipping coupon/minimum rules. It never creates carts or orders, calls dynamic payment availability callbacks, calculates shipping, or reads customer addresses. The static observations do NOT prove whether a gateway will appear for a shopper.

Version 1.13.0 adds an opt-in staging-only browser observer for HTTP 4xx/5xx responses from existing Store API cart/checkout fetch calls made during the administrator's own test. The observer never reads request or response bodies, URLs, headers, payment/customer data or tokens, and never issues requests to WooCommerce Store API itself. Samples expire after seven days with a 24-item limit; the last-scan archive stores aggregate counts only, not individual samples. A 4xx response can be ordinary validation and does not prove gateway failure.

== Changelog ==
= 1.16.0 =
* ZIP 17: Race-resistant per-user scan lock via INSERT IGNORE instead of get/set transient; explicit owner token and stale cleanup.
* New nonce-protected, allowlisted support snapshot JSON (aggregate counts and platform versions only) in the release tab. No raw logs, URLs, plugin inventory, order data or request bodies.
* Added security guidance and unit tests. Manual staging/HPOS/Block/payment sandbox verification is still required.

= 1.15.0 =
* ZIP 16: Bramkę wydania z podsumowaniem niepotwierdzonych scenariuszy, ochronę przed podwójnym uruchomieniem skanu w panelu i w innych kartach (best effort) oraz testy regresyjne.

= 1.14.0 =
* Added evidence correlation view: aggregates Store API HTTP error counts, local WooCommerce log severity counts, and manually attested integration statuses.
* Added a private, step-by-step staging verification guide and report section; no new log, order, customer, or request body collection.
* Co-occurring signals do NOT imply a common root cause, matched timestamp, or successful/failed payment.

= 1.13.0 =
* Added Store API HTTP error observation lab, staging-only opt-in, bounded private samples, strict server validation and clean-up.
* Added browser tests and PHP regression tests; no Store API calls are initiated by the diagnostic module.

= 1.12.0 =
* Added freshness and environment-change checks for manual integration attestations; explicit per-scenario re-confirmation control, no implicit renewal.
* Preserved historical entries with an unverified label. Extended JSON report with verification status and counts.
* No collection of customer data, payment tokens or real order content.

= 1.10.0 =
* Added compatibility and release tab, bounded runtime capabilities audit, manual staging checklist and additional CLI tests.
* Existing modules and original branding retained; automated assertions do not certify gateway or HPOS compatibility.
= 1.9.0 =
* Added staging-only opt-in visibility probe for standard payment options, anonymous counts and bounded retention.

= 1.8.0 =
* Added "Scenariusze koszyka" panel, bounded read-only COD and free-shipping configuration checks, safe static scenario table and regression tests.
* Kept the entire previous feature set and original Heterodyna branding.

= 1.7.0 =
* Added Checkout Block compatibility checks and field registry inventory without personal data.
* Added read-only evidence and UI tab.

= 1.6.0 =
* Opt-in browser checkout probe (administrator only), fixed categorical JS/runtime/resource signals, browser test panel and reset action. Default off; previous modules intact.

= 1.5.0 =
* New readiness panel and local configuration diagnostics, comparison of last two scans, new-check classification after upgrades and secure regression CSV export. Previous modules retained; 22 CLI tests.

= 1.4.0 =
* New Repair Plan screen with P1/P2/P3 priorities, evidence, suggested action and verification step. Cautious co-occurrence correlations, complete JSON report, spreadsheet-safe remediation CSV and static, escaped HTML printable plan. All previous modules retained; 18 CLI tests.

= 1.3.0 =
* Theme template @version comparison, nine checkout hooks with callback owners, bounded WC file log severity counters; Conflicts and Technical Logs tabs; 14 CLI tests.
= 1.2.0 =
* Payments/Shipping inspector, new panels, gateway/COD facts, shipping zones including zone 0, coupon and countries diagnostics, isolated error handling, 11 CLI tests.
= 1.1.0 =
* Checkout Core module: classic, blocks, Store API registry, HPOS and page diagnostics.
= 1.0.0 =
* Initial read-only WooCommerce diagnostics, dark branded UI, anonymized opt-in monitoring and JSON/CSV export.
