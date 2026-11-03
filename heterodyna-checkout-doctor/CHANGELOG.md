## v1.16.0 — 2026-11-01 (ZIP 17 · Lock & Public Support Snapshot)
- Zastąpiono wyścig get/set transient blokadą per użytkownik opartą na atomowym INSERT IGNORE i tokenach właściciela; lock wygasa po 120 sekundach.
- Dodano administracyjny eksport ograniczonego, anonimowego raportu JSON do zgłoszeń (wersje i agregaty, bez raw checks, logów, URL, danych klientów i hashy).
- Dodano trzy testy funkcjonalne oraz dokumentację bezpieczeństwa i wydania.
- Nadal brak pełnych testów bramek sandbox/checkoutu na realnej instalacji staging — brak automatycznej certyfikacji.

## v1.15.0 — 2026-11-01 (ZIP 16 · Release Quality Gate)
- Bramka wydania na ekranie „Zgodność i wydanie”, z jawnymi blokadami oraz stanami weryfikacji 7 ręcznych scenariuszy testowych.
- Status check-listy nie jest automatycznym certyfikatem zgodności WooCommerce; eksport JSON zawiera tylko agregaty, bez danych klientów.
- Ochrona przed równoczesnym uruchomieniem skanu: blokada w aktywnej karcie i krótkotrwały, indywidualny znacznik dla innych kart (best effort).
- Testy regresyjne bramki, w tym brak WooCommerce, zbyt stare wersje PHP/WP i przeterminowane testy.
- Bez zmian schematu bazy i bez migracji danych użytkownika. Wersja nadal wymaga ręcznych testów staging.

## v1.14.0 — 2026-11-01 (ZIP 15 · Evidence Correlation)
- Dodano bezpieczne podsumowanie sygnałów Store API, lokalnych logów WooCommerce i ręcznej checklisty integracyjnej.
- Nowa zakładka Korelacja dowodów, liczniki, procedura weryfikacji oraz nowe wpisy w raporcie.
- Nie dodano żadnego odczytu danych klienta, treści HTTP, ani dodatkowych wywołań Store API.
- Połączone liczniki nie są dowodem wspólnej przyczyny ani związku czasowego.

## v1.13.0 — 2026-11-01 (ZIP 14 · Store API Response Lab)
- Nowa zakładka „Błędy Store API” dla lokalnego, pasywnego monitorowania kodów 4xx/5xx z istniejących wywołań fetch checkoutu i koszyka.
- Opt-in: tylko local/development/staging, autoryzowany użytkownik WooCommerce, kontrola nonce, limit prób, limit 24 anonimowych obserwacji w transient przez 7 dni od ostatniego zapisu.
- Tylko obszar (cart/checkout), metoda GET/POST/PUT/DELETE, kod HTTP i znacznik UTC; brak odczytu treści, URL-i, danych formularza, tokenów, nagłówków i odpowiedzi.
- Pełne raporty JSON i filtry; archiwalny raport w opcji nie utrwala szczegółów zdarzeń, jedynie ich zbiorczą statystykę do czasu wyczyszczenia.
- Możliwość wyczyszczenia obserwacji; wyłączenie funkcji usuwa także archiwalne statystyki tego modułu.
- Testy regresyjne PHP i JavaScript; pełne wcześniejsze pliki, logo i GPL zachowane.
- Brak własnych żądań do Store API. GET /checkout może tworzyć zamówienie robocze, dlatego nie jest wywoływany automatycznie.

## v1.12.0 — 2026-11-01 (ZIP 13 · Integration Evidence Freshness)
- Weryfikacja aktualności ręcznych testów przy zmianie środowiska i po 30 dniach, bez fałszywego automatycznego „PASS”.
- Fingerprint SHA-256 środowiska WooCommerce, WordPress, PHP, motywu i aktywnych rozszerzeń (wersje, jeśli dostępne).
- Potwierdzanie odrębnych scenariuszy jawnie przez checkbox; samo zapisanie nie odnawia daty.
- Migracja niedestrukcyjna: statusy v1.11.0 zachowane, lecz oznaczane jako historyczne, dopóki nie zostaną sprawdzone ponownie.
- Rozszerzenie raportu JSON i czytelności panelu; wcześniejsze funkcje oraz branding bez zmian.

## v1.11.0 — ZIP 12 · Staging Integration Lab & Fault Isolation
- Seven explicit manual integration scenarios, each with fixed status and UTC change time.
- Save/export protected by WooCommerce management capability, WordPress nonce and normalized server-side allowlists.
- Checkout scan includes a snapshot of the manual checklist without claiming automatic verification.
- PHP runtime isolation for payment/shipping and checkout-core modules; safe AJAX error message on a top-level failure.
- Added tab, report category, test coverage and tests; original assets preserved.

## v1.10.0 — 2026-11-01 (ZIP 11 · Compatibility & Release Readiness)
- Nowa zakładka „Zgodność i wydanie” z pasywną matrycą API i wersji WordPress/PHP/WooCommerce.
- Sygnały dostępności rejestrów Blocks / REST / Action Scheduler bez przypisywania fałszywej pewności zgodności.
- Ręczna lista testów przed publikacją z wymogiem stagingu i bramek sandbox.
- Rozszerzenie wyników skanera i raportów JSON oraz nowe testy CLI bez utraty wcześniejszych funkcji.
- Oryginalne logo i Dark UI zachowane, licencja GPL-2.0-or-later.

## v1.9.0 — 2026-11-01 (ZIP 10 · Payment Visibility Lab)
- Staging-only opt-in obserwator renderowanych standardowych opcji płatności w przeglądarce administratora.
- Blokada na produkcji; weryfikacja uprawnień i nonce AJAX, limit prób, 12 próbek / 7 dni, czyszczenie próbek.
- Nowa zakładka panelu, liczniki i tabela obserwacji oraz wyniki i zalecenia w raporcie JSON.
- Bez wykonywania canMakePayment/is_available, rzeczywistych płatności, zmiany koszyka i odczytu pól osobowych.
- Zachowane wszystkie funkcje v1.8.0, poprzednie testy, oryginalny logo i Dark UI.

# Heterodyna Checkout Doctor — historia zmian

## 1.8.0 — ZIP 9 / Scenariusze koszyka
- Nowy moduł HCD_Scenarios: pasywna analiza warunków COD dla produktów wirtualnych, dostawy standardowej i odbioru osobistego.
- Rozpoznanie konfiguracji darmowej wysyłki (kupon, minimalna kwota, warunki łączne/alternatywne).
- Osobna zakładka, liczniki, tabela scenariuszy, rekomendacje i raport JSON.
- Brak wykonywania dynamicznych callbacków, kalkulacji stawek, zmian koszyka, żądań operatorów i płatności.
- Sygnały konfiguracyjne nie są przedstawiane jako wynik rzeczywistej płatności.

## v1.7.0 — 2026-11-01 (ZIP 8 · Checkout Block Compatibility)

- Nowa zakładka Zgodność bloków: lokalna inspekcja rejestru PHP bramek i definicji CheckoutFields.
- Bezpieczne porównanie ID bramek bez rozstrzygania kompatybilności na podstawie samej nazwy.
- Zliczanie pól według lokalizacji/typu, 30 znormalizowanych technicznych identyfikatorów; brak wartości pól i danych kupujących.
- Wyniki w skanerze i eksporcie JSON/HTML/CSV oraz izolacja wyjątków modułu.
- Zachowano wszystkie funkcje v1.6.0 i oryginalne logo Heterodyna Team Software.

## v1.6.0 — 2026-11-01 (wydanie 7 · Browser Checkout Probe)

- Nowy opt-in obserwator checkoutu dla zalogowanego administratora (z wyłączeniem zwykłych klientów).
- Kategoryczne błędy JavaScript/Promise/skryptów i stylów, rozpoznanie formularza Checkout Block / klasycznego.
- Pasywne klasyfikowanie czasów zasobów Store API, bez przechwytywania treści transakcji.
- Zakładka „Przeglądarka JS”, liczniki, wyniki, przycisk czyszczenia próbek; monitoring wyłączony domyślnie.
- Limity odbioru oraz lista dozwolonych wartości; raport i plan napraw oznaczają sygnały, nie przyczyny.
- Zachowane funkcje ZIP 1–6 oraz oryginalny branding i licencja.

## v1.5.0 — 2026-11-01 (wydanie dodatkowe: Readiness & Regression)

- Nowa zakładka „Gotowość i zmiany” w ciemnym panelu Heterodyna, zachowany oryginalny plik logo.
- Cztery pasywne testy: numer wersji bazy/kodu WooCommerce, nazwy endpointów checkout, ustawienie WP-Cron/Action Scheduler, pokazywanie komunikatów PHP.
- Bezpieczne porównanie wyników dwóch kolejnych skanów z zaznaczeniem zmian statusu i różnic po aktualizacji wtyczki.
- CSV z porównaniem (nonce i uprawnienia, ochrona przed formułami). Brak danych klienta, surowych logów, kluczy i automatycznych zmian.
- Aktualizacje planu napraw, dokumentacji, testów regresji i sum SHA-256. Testy izolowane nie są testami na rzeczywistym sklepie.

## v1.4.0 — 2026-11-01 (ZIP 5/5)

- Nowa zakładka Plan napraw (P1/P2/P3): dowody, akcje, kroki weryfikacyjne, staging i kopia zapasowa.
- Nieinwazyjna korelacja współwystępowania ustaleń bez przypisywania przyczynowości, bez arbitralnego „wyniku bezpieczeństwa”.
- Eksport JSON całego skanu, CSV z ochroną przed wstrzykiwaniem formuł i samowystarczalny raport HTML z escapowaniem, CSP i bez skryptów.
- Wsteczna obsługa archiwalnych skanów, zachowane logo i 9 wcześniejszych zakładek; 18 testów CLI oraz kontrola składni.
- Roadmap ZIP 5/5 wykonany; rzeczywiste testy WP/WooCommerce i integracyjne nadal pozostają przed użyciem produkcyjnym.

# Historia wydań — Heterodyna Checkout Doctor

## v1.3.0 — 2026-11-01 (ZIP 4/5)

- `HCD_Conflicts_Logs`: bezpieczny odczyt nagłówków @version nadpisań szablonów checkout/cart i porównanie do bieżących plików WooCommerce.
- Inwentaryzacja dziewięciu hooków checkout: liczba callbacków i właściciele kodu PHP według ścieżki wtyczki; wykrywanie współdzielonych hooków bez nazywania ich automatycznie awarią.
- Ograniczona analiza lokalnych logów plikowych WooCommerce: maks. osiem plików po 64 KiB, tylko liczniki krytyczne/error/notice; bez ujawniania surowych wiadomości, nazw plików, tokenów i danych kupujących.
- Zakładki **Konflikty** i **Logi techniczne**, tabela szablonów, tabela hooków, liczniki i filtry.
- Zachowano wszystkie elementy ZIP 1–3; trzy nowe testy jednostkowe (łącznie 14), w tym zabezpieczenia prywatności i symlinków.
- Brak żądań zewnętrznych, transakcji, wykonywania callbacków, wyłączania innych wtyczek; brak automatycznej diagnostyki błędów JavaScript w przeglądarce.

## v1.2.0 — 2026-11-01 (ZIP 3/5)

- Moduł `HCD_Payments_Shipping`: pasywna diagnostyka bramek WooCommerce, włączonych metod, ograniczeń pobrania COD i jawne granice analizy callbacków.
- Moduł wysyłki: dodatkowe strefy oraz domyślna strefa ID 0, włączone/nieaktywne metody i ostrzeżenia o pustych strefach, regułach kuponów i krajach docelowych.
- Dodano zakładki **Płatności** i **Wysyłka**, statystyki, przyciski diagnostyki i osobne wyniki modułów.
- Dane w raportach nie zawierają sekretów konfiguracji, treści wyjątków, danych klientów, logów ani payloadów płatności.
- Wszystkie funkcje v1.0.0–v1.1.0 pozostają, zaktualizowano testy CLI do 11 scenariuszy i dokumentację.

## v1.1.0 — 2026-11-01 (ZIP 2/5)

- Dodano silnik statycznej diagnostyki Core Checkout (`class-hcd-checkout-core.php`): klasyczny shortcode, Checkout Block, koszyk, kolizje stron, ochrona hasłem, regulamin, ustawienia kont, rejestracja bloków i tras Store API oraz tryb HPOS.
- Dodano dedykowaną zakładkę „Silnik Checkout”, kafelki stanu silnika i filtrowanie raportu według modułu.
- Zachowano eksport JSON, dziennik zdarzeń CSV, brak transakcji i ochronę prywatności.
- Uściślono okno raportowania zamówień Failed: 7 x 24 godziny zamiast daty obciętej do dnia.
- Rozbudowano zestaw testów CLI do 8 scenariuszy i przeprowadzono lint PHP/JS.


## v1.0.0 · pierwszy pakiet z pięciu zaplanowanych

- **Nowy interfejs klasy premium**: ciemny Dashboard, diagnostyka, dziennik, ustawienia, logo Heterodyna, oznaczenie OPEN SOURCE.
- **Diagnostyka read-only**: WooCommerce i Checkout Page, Woo Checkout Block / shortcode, cart, HTTPS, bramki, wysyłka, Failed 7 dni, stan monitoringu.
- **Dowody i interpretacje**: fakt vs sygnał, zalecenia, brak nieuprawnionych twierdzeń o podatności lub awarii.
- **Monitoring opcjonalny**: Classic Checkout, Checkout Block Store API, status Failed, zdarzenia order created/payment complete.
- **Prywatność**: brak danych osób i płatności, ogólne kategorie błędów, minimalna telemetria.
- **Eksport JSON / CSV**, usuwanie i retencja 7/14/30 dni, harmonogram czyszczenia.
- **Integracja HPOS** przez oficjalną deklarację zgodności.
- **Testy**: PHP lint, JS syntax, 4 izolowane testy regresji — bez możliwości testu na rzeczywistym sklepie w środowisku przygotowania.

## Kolejne wydania

- **ZIP 5/5** — Repair Guidance & Reports: korelacja ustaleń, precyzyjne raporty, finalne dopracowanie interfejsu.

Plan etapowy. Wydania przyszłe nie są częścią bieżącej paczki.
