<p align="center">
  <img src="heterodyna-checkout-doctor/assets/img/heterodyna-logo.png" alt="Heterodyna Team Software" width="112">
</p>

<h1 align="center">Heterodyna Checkout Doctor</h1>

<p align="center"><strong>Diagnostyka koszyka, checkoutu, płatności i wysyłki w WooCommerce — w jednym panelu WordPress.</strong></p>

<p align="center">
  <img alt="Wersja" src="https://img.shields.io/badge/version-1.16.0-blue">
  <img alt="Licencja GPL-2.0-or-later" src="https://img.shields.io/badge/license-GPL--2.0--or--later-green">
  <img alt="WordPress 6.2+" src="https://img.shields.io/badge/WordPress-6.2%2B-21759b">
  <img alt="PHP 7.4+" src="https://img.shields.io/badge/PHP-7.4%2B-777bb4">
  <img alt="Status: w rozwoju" src="https://img.shields.io/badge/status-w%20rozwoju-orange">
</p>

**Checkout Doctor** to darmowa wtyczka Open Source rozwijana przez **Heterodyna Team Software**. Pomaga administratorom i programistom sklepów WooCommerce ustalać, *dlaczego* proces zakupowy może działać nieprawidłowo — bez wykonywania rzeczywistych płatności i bez automatycznych zmian w zamówieniach.

**[🌐 Strona projektu](https://heterodynateamsoftware.pl/projekty/checkout-doctor/)** · **[📦 Wydania](https://github.com/HeterodynaTeamSoftware/Checkout-Doctor/releases)** · **[🐛 Zgłoś problem](https://github.com/HeterodynaTeamSoftware/Checkout-Doctor/issues)**

> **Status v1.16.0:** kod i testy lokalne są dostępne, ale pełne testy integracyjne na rzeczywistym WordPress/WooCommerce (w tym Checkout Block, HPOS i bramka płatnicza w trybie sandbox) nadal wymagają ręcznego potwierdzenia. Wynik skanu nie stanowi certyfikatu bezpieczeństwa ani gwarancji poprawnej realizacji zamówień.

## 🔎 Możliwości

| Obszar | Co sprawdza lub ułatwia |
| --- | --- |
| Koszyk i checkout | Strony koszyka i finalizacji, klasyczne shortcode, Checkout Block, podstawowa konfiguracja HTTPS oraz wybrane zależności Store API i HPOS |
| Płatności | Aktywne bramki, wybrane konfiguracje COD (za pobraniem) i sygnały dotyczące dostępności metod płatności |
| Wysyłka | Strefy dostaw, konfiguracja metod i reguły darmowej wysyłki |
| Konflikty | Wybrane hooki WooCommerce, właściciele callbacków i nadpisania szablonów motywu wraz z porównaniem wersji |
| JavaScript i Store API | Opcjonalne, ograniczone obserwatory błędów przeglądarki i odpowiedzi HTTP 4xx/5xx na stagingu |
| Logi i korelacja | Zbiorcze sygnały lokalnych logów WooCommerce i zestawienie ustaleń z różnych modułów |
| Plan napraw | Priorytety P1–P3, opis obserwacji, zalecane czynności i ponowna weryfikacja |
| Historia i raporty | Porównywanie skanów oraz eksport JSON, CSV i czytelnego raportu HTML |
| Gotowość do wydania | Lista siedmiu ręcznych scenariuszy integracyjnych, kontrola aktualności potwierdzeń oraz bramka wydania |

**Ważne:** brak błędu w statycznej konfiguracji nie oznacza, że płatność lub wysyłka zadziała w konkretnym koszyku. Współdzielony hook czy występujące równocześnie ostrzeżenia nie dowodzą konfliktu ani jednej przyczyny awarii.

## 💻 Wymagania

- WordPress **6.2 lub nowszy**;
- PHP **7.4 lub nowszy**;
- WooCommerce zainstalowany i aktywny, aby wykonać diagnostykę sklepu;
- uprawnienia administratora/sklepu odpowiednie do uruchamiania narzędzi.

Kompatybilność z konkretną wersją WooCommerce, motywem oraz bramkami płatniczymi należy sprawdzić na własnym środowisku testowym. Obsługa wybranych API nie jest dowodem pełnej zgodności integracji.

## 🚀 Instalacja

### Z paczki ZIP (zalecane użytkownikom sklepu)

1. Pobierz paczkę instalacyjną ZIP z [GitHub Releases](https://github.com/HeterodynaTeamSoftware/Checkout-Doctor/releases), gdy zostanie opublikowana.
2. W panelu WordPress przejdź do **Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer**.
3. Wybierz ZIP, zainstaluj i aktywuj wtyczkę. Przy aktualizacji istniejącej instalacji wykonaj wcześniej kopię zapasową.
4. Otwórz **WooCommerce → Checkout Doctor** i uruchom diagnostykę.
5. Zapoznaj się z wynikiem, planem napraw oraz zakładką **Zgodność i wydanie**.

### Z kodu źródłowego

W tym repozytorium właściwa wtyczka znajduje się w katalogu [`heterodyna-checkout-doctor/`](heterodyna-checkout-doctor/). Skopiuj **ten katalog** do `wp-content/plugins/`, a następnie aktywuj wtyczkę w WordPress. Nie kopiuj całego repozytorium jako jednego katalogu wtyczki — jego pliki główne służą dokumentacji projektu.

```text
Checkout-Doctor/
├── README.md
├── LICENSE
├── SECURITY.md
├── CONTRIBUTING.md
└── heterodyna-checkout-doctor/
    ├── heterodyna-checkout-doctor.php
    ├── includes/                 # moduły diagnostyczne PHP
    ├── assets/                   # interfejs CSS/JS oraz grafiki
    ├── tests/                    # testy lokalne PHP i JavaScript
    ├── readme.txt                # metadane wtyczki WordPress
    ├── CHANGELOG.md
    ├── BUILD-TESTS.md
    └── ...                       # dokumentacja wydania i manifest SHA-256
```

## 🔐 Prywatność i bezpieczne testowanie

- Standardowa diagnostyka analizuje konfigurację lokalnie; **nie wykonuje rzeczywistych płatności, nie tworzy zamówień i nie wyłącza automatycznie innych wtyczek**.
- Obserwatory przeglądarki, dostępności metod płatności i Store API są opcjonalne; zaawansowane obserwacje są przeznaczone do autoryzowanych testów na środowisku **local/development/staging**.
- Próbki techniczne są ograniczane co do typu, liczby i okresu przechowywania. Nie należy umieszczać w publicznych Issues danych klientów, tokenów, pełnych logów ani żądań checkoutu.
- Moduł korelacji pokazuje zbieżne sygnały, **nie dowodzi związku przyczynowego**.
- Raport wsparcia JSON (v1.16.0) zawiera ograniczone agregaty i informacje o wersjach. **Zawsze sprawdź plik przed jego upublicznieniem.**

Więcej: [SECURITY.md](SECURITY.md).

## 🧪 Testy dla programistów

W katalogu `heterodyna-checkout-doctor/` potrzebne są narzędzia CLI `php`, `node` oraz `sha256sum` (dla manifestu):

```bash
cd heterodyna-checkout-doctor
find . -name '*.php' -print0 | xargs -0 -n1 php -l
find . -name '*.js' -print0 | xargs -0 -n1 node --check
for test in tests/test-*.php; do php "$test" || exit 1; done
for test in tests/test-*.js; do node "$test" || exit 1; done
sha256sum -c SHA256SUMS.txt
```

Testy CLI wykorzystują również makiety środowiska. **Nie zastępują** instalacji WordPress i ręcznych testów checkoutu, płatności sandbox, Checkout Block i HPOS na stagingu. Przed wdrożeniem zobacz [`RELEASE-GITHUB-CHECKLIST.md`](heterodyna-checkout-doctor/RELEASE-GITHUB-CHECKLIST.md) i [`BUILD-TESTS.md`](heterodyna-checkout-doctor/BUILD-TESTS.md).

## 🆕 Co nowego w v1.16.0

- Bezpieczniejsza blokada równoległych skanów per użytkownik: atomowy zapis `INSERT IGNORE`, token właściciela oraz wygaszanie nieaktualnej blokady.
- Eksport kontrolowanego pakietu wsparcia JSON z informacjami o wersjach i zagregowanymi licznikami, bez surowych logów i treści zamówień.
- Dodatkowe testy regresyjne blokady skanów, eksportu i integracji.
- Zachowana bramka wydania i mechanizm ręcznych potwierdzeń wymagających ponownej weryfikacji po zmianach środowiska.

Pełna historia: [`CHANGELOG.md`](heterodyna-checkout-doctor/CHANGELOG.md).

## 🤝 Współpraca i zgłaszanie błędów

Zgłoszenia i pomysły publikuj w [GitHub Issues](https://github.com/HeterodynaTeamSoftware/Checkout-Doctor/issues). Podaj wersję Checkout Doctor, WordPress, WooCommerce, PHP, nazwę modułu, kroki odtworzenia i komunikat techniczny **bez danych wrażliwych**. Wskazówki do zmian kodu są w [CONTRIBUTING.md](CONTRIBUTING.md).

## 📄 Licencja i autor

**GPL-2.0-or-later**, zgodnie z nagłówkiem wtyczki i dołączonym tekstem [LICENSE](LICENSE).

© Heterodyna Team Software · [heterodynateamsoftware.pl](https://heterodynateamsoftware.pl/)

---

*EN: Heterodyna Checkout Doctor is an open-source WooCommerce diagnostic plugin for checkout, payment/shipping configuration, conflict signals, Store API, logs and repair guidance. Standard diagnostics are non-transactional. Browser observers are opt-in and staging-oriented. Production compatibility must be validated manually.*
