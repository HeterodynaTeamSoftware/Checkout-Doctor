# Heterodyna Checkout Doctor — przygotowanie publicznego wydania

Wersja: **1.16.0**; licencja: **GPL-2.0-or-later**.

- [x] Pełna instalacyjna paczka, pojedynczy katalog `heterodyna-checkout-doctor/`.
- [x] Wyrównanie wersji nagłówka, stałej PHP, `readme.txt`, CHANGELOG i testów.
- [x] Repozytoryjne README (PL), instrukcje testów CLI i historia zmian.
- [x] Brak surowych próbek Store API, tekstu logów, danych klientów i sekretów w korelacji.
- [ ] Rzeczywista instalacja/aktualizacja z poprzedniego ZIP na WordPress i WooCommerce.
- [ ] Testy integracyjne w siedmiu scenariuszach na stagingu, w tym Checkout Block i HPOS.
- [ ] Test checkoutu w sandboxie co najmniej jednej bramki oraz test gościa.
- [ ] Przegląd uprawnień, eksportów i retencji po migracji WordPress.
- [ ] Utworzenie publicznego repozytorium, tagu `v1.16.0`, publikacja pliku ZIP w GitHub Releases.

## Polityka zgłaszania błędów
W publicznych zgłoszeniach podawaj nazwę testu, wersje WordPress/WooCommerce i kod błędu, **bez** treści żądań, numerów zamówień, tokenów, e-maili czy pełnych logów.

Weryfikacja plików `sha256sum -c SHA256SUMS.txt` działa po wypakowaniu na środowisku z narzędziem `sha256sum`.

- [ ] Sprawdź sekcję „Bramka wydania” — wszystkie 7 scenariuszy ręcznie potwierdzone w aktualnym środowisku; wynik nigdy nie zastępuje przeglądu.

- [x] Eksport zestawu zgłoszeniowego bez surowych logów, URL i PII (unit tests).
- [x] Blokada skanowania z tokenem właściciela (unit tests).
- [ ] Wykonaj oficjalny Plugin Check na bieżącym WP/WooCommerce oraz przegląd bezpieczeństwa przed publikacją w katalogu.
