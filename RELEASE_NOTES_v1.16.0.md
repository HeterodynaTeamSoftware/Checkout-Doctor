# Heterodyna Checkout Doctor v1.16.0

**Wydanie:** v1.16.0 (ZIP 17) · **Licencja:** GPL-2.0-or-later

## Zmiany

- Atomowa blokada równoległych skanów oparta na `INSERT IGNORE`, tokenach właściciela i wygaszaniu nieaktualnych blokad (best effort).
- Nowy eksport ograniczonego pakietu wsparcia JSON, zabezpieczony uprawnieniami administratora i nonce. Dane wyjściowe zawierają wersje oraz agregaty, bez surowych logów, URL i informacji o klientach.
- Dodatkowe testy regresyjne związane z blokadą skanów i eksportem.
- Zachowana bramka gotowości wydania oraz siedem ręcznie potwierdzanych scenariuszy integracyjnych.

## Instalacja

Pobierz załączony do tego wydania ZIP z wtyczką. W WordPress przejdź do **Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer**, zainstaluj i aktywuj. Najpierw wykonaj kopię zapasową oraz test aktualizacji na stagingu.

## Ważne ograniczenia

Testy jednostkowe i CLI nie zastępują integracji na rzeczywistym WooCommerce. Przed produkcyjnym zastosowaniem zweryfikuj Checkout Block, HPOS, checkout gościa, metody dostawy i co najmniej jedną bramkę płatniczą w trybie sandbox. Wtyczka nie wykonuje prawdziwych transakcji ani nie jest certyfikatem zgodności lub bezpieczeństwa.

Więcej informacji: https://heterodynateamsoftware.pl/projekty/checkout-doctor/
