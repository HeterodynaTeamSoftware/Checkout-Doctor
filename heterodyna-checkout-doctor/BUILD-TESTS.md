# Heterodyna Checkout Doctor v1.16.0 — ZIP 17 / testy lokalne

Z katalogu głównego wtyczki:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
node --check assets/js/admin.js
node --check assets/js/checkout-observer.js
node --check assets/js/payment-visibility.js
node tests/test-browser-runtime.js
node tests/test-visibility-runtime.js
node tests/test-store-observer-runtime.js
for test in tests/test-*.php; do php "$test" || exit 1; done
sha256sum -c SHA256SUMS.txt
```

Testy CLI weryfikują składnię, wybrane funkcje i mocki. Nie są testem integracyjnym na działającym WooCommerce. Wyniki w zakładce Testy integracyjne wypełnia ręcznie operator stagingu.

Nowe testy: `test-integration-freshness.php` i `test-integration-reconfirmation.php`.

Nowe testy ZIP 14: `test-store-observer-core.php`, `test-store-observer-ajax.php`, `test-store-observer-integration.php` oraz `test-store-observer-runtime.js`.

Nowe testy: test-evidence-correlation.php, test-evidence-integration.php, test-evidence-privacy.php, test-evidence-purge.php.

Nowe testy: `test-release-gate.php` i `test-release-integration.php`.

Nowe ZIP 17: `test-scan-lock-atomic.php`, `test-release-snapshot.php`, `test-release-snapshot-integration.php`.
