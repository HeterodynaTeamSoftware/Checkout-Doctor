<p align="center">
  <img src="heterodyna-checkout-doctor/assets/img/heterodyna-logo.png" width="90" alt="Heterodyna Team Software">
</p>

<h1 align="center">Heterodyna Checkout Doctor</h1>

<p align="center">
  <strong>Diagnostyka koszyka, zamówień, płatności i dostawy w WooCommerce.</strong><br>
  Darmowa wtyczka open source dla administratorów i twórców sklepów WordPress.
</p>

<p align="center">
  <a href="https://github.com/HeterodynaTeamSoftware/Checkout-Doctor/releases"><strong>📦 POBIERZ WTYCZKĘ (RELEASES)</strong></a>
  &nbsp;·&nbsp;
  <a href="https://heterodynateamsoftware.pl/projekty/checkout-doctor/">Strona projektu</a>
  &nbsp;·&nbsp;
  <a href="https://github.com/HeterodynaTeamSoftware/Checkout-Doctor/issues">Zgłoś problem</a>
</p>

---

## O projekcie

**Checkout Doctor** pomaga znaleźć przyczyny problemów z finalizacją zamówienia w WooCommerce. W jednym panelu zbiera wyniki kontroli konfiguracji, sygnały o możliwych konfliktach oraz wskazówki naprawcze. Standardowy skan nie wykonuje prawdziwych płatności ani nie wprowadza automatycznych zmian w sklepie.

### Co potrafi?

- **Koszyk i checkout** — kontrola stron zakupowych, klasycznego checkoutu i wybranych elementów Checkout Block, Store API oraz HPOS.
- **Płatności i dostawy** — przegląd bramek płatniczych, metod wysyłki, stref i wybranych reguł konfiguracji.
- **Wykrywanie sygnałów konfliktów** — analiza wybranych hooków, nadpisań szablonów WooCommerce oraz lokalnych logów.
- **Diagnostyka przeglądarki** — opcjonalne obserwatory JavaScript i Store API przeznaczone do testów na środowisku developerskim lub stagingowym.
- **Wskazówki i raportowanie** — uporządkowany plan weryfikacji, historia skanów, porównania wyników oraz eksport raportów.
- **Kontrola gotowości** — lista ręcznych scenariuszy do sprawdzenia przed wdrożeniem zmian w sklepie.

## Instalacja

1. Otwórz **[GitHub Releases](https://github.com/HeterodynaTeamSoftware/Checkout-Doctor/releases)** i pobierz paczkę ZIP wtyczki.
2. W WordPress przejdź do **Wtyczki → Dodaj nową → Wyślij wtyczkę na serwer**.
3. Wybierz ZIP, zainstaluj i aktywuj wtyczkę.
4. Uruchom **WooCommerce → Checkout Doctor** i sprawdź wyniki diagnostyki.

Przed aktualizacją na sklepie produkcyjnym wykonaj kopię zapasową i przetestuj działanie na stagingu.

### Wymagania

| Element | Wymaganie |
| --- | --- |
| WordPress | 6.2 lub nowszy |
| PHP | 7.4 lub nowszy |
| WooCommerce | Aktywny, aby diagnozować sklep |

Nie każda bramka płatnicza, motyw lub konfiguracja WooCommerce została sprawdzona w rzeczywistym środowisku. Wynik skanowania jest wskazówką diagnostyczną, a nie gwarancją prawidłowego przebiegu transakcji.

## Kod źródłowy i rozwój

Kod właściwej wtyczki znajduje się w katalogu [`heterodyna-checkout-doctor/`](heterodyna-checkout-doctor/). Można skopiować **ten katalog** do `wp-content/plugins/` w lokalnej instalacji WordPress.

- [Historia zmian](heterodyna-checkout-doctor/CHANGELOG.md)
- [Uruchamianie testów](heterodyna-checkout-doctor/BUILD-TESTS.md)
- [Zgłaszanie błędów i współpraca](CONTRIBUTING.md)
- [Zasady bezpieczeństwa](SECURITY.md)

Zgłoszenia błędów i propozycje zmian: [GitHub Issues](https://github.com/HeterodynaTeamSoftware/Checkout-Doctor/issues). Nie publikuj danych klientów, tokenów ani prywatnych logów.

## Licencja

Projekt jest udostępniany na licencji **GPL-2.0-or-later** — [plik LICENSE](LICENSE).

**Autor:** [Heterodyna Team Software](https://heterodynateamsoftware.pl/)