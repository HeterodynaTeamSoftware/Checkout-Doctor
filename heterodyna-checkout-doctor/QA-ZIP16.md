# Heterodyna Checkout Doctor v1.16.0 — kontrola jakości (ZIP 16)

Data: 2026-11-01. Środowisko testów: PHP 8.4 CLI, Node.js 22 (poza WordPress).

## Zakres
- Kontrola składni wszystkich plików PHP (`php -l`) i JavaScript (`node --check`).
- Testy PHP CLI i JS z katalogu `tests/`, w tym nowe scenariusze „Bramki wydania”.
- Ochrona uprawnień/nonce dla istniejącego endpointu AJAX oraz dodatkowe zabezpieczenie przeciw równoczesnym skanom (best effort).
- Bramka wydania rozróżnia: przeszkody (`blocked`), testy niewykonane/nieaktualne (`requires_tests`), komplet ręcznych potwierdzeń z koniecznym przeglądem (`manual_complete_pending_review`).
- Żaden status nie jest automatyczną zgodą na produkcję.
- Paczka ZIP ma dokładnie jeden katalog główny: `heterodyna-checkout-doctor/`, bez historii i kopii zapasowych.

## Ograniczenia
- Testy CLI wykorzystują makiety WordPress/WooCommerce; to **nie** jest test integracyjny na rzeczywistym sklepie.
- Brak przeprowadzonego zamówienia na sandbox bramki, ręcznych kontroli dostępności Checkout Block, HPOS, wariantów motywów i różnych wersji WooCommerce.
- Blokada równoczesnych żądań przez transients jest mechanizmem best effort, nie atomową blokadą rozproszoną na każdy backend cache.
- Nie publikuj wtyczki jako zweryfikowanej produkcyjnie bez checklisty staging i przeglądu.

## Sposób odtworzenia
```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
find . -name '*.js' -print0 | xargs -0 -n1 node --check
for t in tests/test-*.php; do php "$t" || exit 1; done
for t in tests/test-*.js; do node "$t" || exit 1; done
sha256sum -c SHA256SUMS.txt
```
