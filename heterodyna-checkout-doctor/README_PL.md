# Heterodyna Checkout Doctor v1.16.0 — Kontrola gotowości wydania (ZIP 17)

Pełna wtyczka WordPress/WooCommerce Open Source. Zachowuje moduły i oryginalny branding Heterodyna z poprzednich wydań.

## Nowości w ZIP 17

- **Bezpieczniejsza blokada skanów**: token właściciela oraz atomowy zapis INSERT IGNORE w tabeli opcji WordPress (najlepszy możliwy wysiłek dla środowisk współdzielonych).
- **Anonimowy pakiet wsparcia JSON**: pobierz w zakładce „Zgodność i wydanie”; zawiera wyłącznie wersje i zagregowane liczby, bez surowych logów czy danych klientów.
- Wciąż wymagane ręczne testy sandbox na stagingu przed publikacją.

- Nowa **Bramka wydania** w zakładce „Zgodność i wydanie”. Pokazuje blokady publikacji, scenariusze wymagające powtórzenia oraz aktualne potwierdzenia ręczne.
- Nie nazywa niekompletnych testów sukcesem i nie certyfikuje automatycznie sklepu nawet po 7/7 potwierdzeniach.
- Dodatkowe zabezpieczenie przed równoczesnym uruchomieniem skanu w jednej lub kilku kartach administratora; blokada serwera wygasa po 120 sekundach w razie przerwania PHP (best effort).
- Zachowano wszystkie wcześniejsze funkcje, raporty i oryginalne logo.

## Instalacja i test
1. Zastąp starą wtyczkę pełnym ZIP-em w WordPress → Wtyczki → Dodaj nową.
2. Otwórz WooCommerce → Checkout Doctor → Zgodność i wydanie.
3. Kliknij „Sprawdź zgodność”; sprawdź panel **Bramka wydania**.
4. Uzupełnij „Testy integracyjne” na stagingu i ponów skan. Nie wykonuj prawdziwych płatności — tylko sandbox.

## Zastrzeżenia
- Wykryte zbieżne sygnały **nie są potwierdzoną korelacją czasową ani dowodem przyczyny**.
- Nie są wykonywane żądania do operatorów, realne transakcje, testy zmieniające zamówienia.
- Narzędzia CLI uruchamiają testy z mockami, nie zastępują testów na realnym WooCommerce.

Checklistę publikacji znajdziesz w `RELEASE-GITHUB-CHECKLIST.md`.

Repozytorium wydania: przygotuj na GitHub pliki tego ZIP-a pod pojedynczym katalogiem `heterodyna-checkout-doctor/`, dołącz licencję, `readme.txt`, `CHANGELOG.md` oraz protokół testów stagingowych.


## ZIP 17: Bramka wydania (v1.16.0)

W zakładce **Zgodność i wydanie** pojawia się sekcja „Bramka wydania”. Wskazuje brak WooCommerce, wersje poniżej minimum, testy ręczne oznaczone jako problem oraz brakujące lub przeterminowane potwierdzenia. Trzy stany: `blocked`, `requires_tests`, `manual_complete_pending_review`. Nawet gdy wszystkie siedem scenariuszy jest aktualnie potwierdzonych, nie jest to automatyczne dopuszczenie do publikacji.

Nie wykonuje połączeń HTTP, płatności ani zmian w zamówieniach. Dodatkowo panel blokuje ponowne kliknięcie, a serwer ogranicza równoczesne żądania tego samego użytkownika w innych kartach (best effort).
