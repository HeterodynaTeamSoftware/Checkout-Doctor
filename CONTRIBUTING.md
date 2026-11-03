# Współtworzenie Heterodyna Checkout Doctor

Dziękujemy za pomoc w rozwoju projektu!

## Zgłaszanie problemów

Przed dodaniem zgłoszenia sprawdź istniejące Issues. Opisz wersje wtyczki, WordPress, WooCommerce i PHP, kroki odtworzenia, oczekiwane i rzeczywiste zachowanie oraz kategorię problemu. Nie przesyłaj danych klientów, zamówień, adresów, tokenów ani pełnych logów.

## Zmiany w kodzie

1. Utwórz własną gałąź i ogranicz zmiany do konkretnego problemu.
2. Zachowaj nieinwazyjny charakter diagnostyki, uprawnienia WordPress, kontrolę nonce i minimalizację danych.
3. Dodaj lub dostosuj testy CLI, gdy zmiana dotyczy logiki.
4. Uruchom polecenia z `heterodyna-checkout-doctor/BUILD-TESTS.md`.
5. Opisz ograniczenia testów na realnym WooCommerce i, gdy to konieczne, plan ręcznej weryfikacji na stagingu.
6. Wyślij Pull Request z krótkim opisem i scenariuszem testowania.

Testy CLI oparte na mockach nie są potwierdzeniem poprawności prawdziwej transakcji. Nie wykonuj prawdziwych płatności w ramach automatycznych testów wtyczki.

## Licencja

Wkład do projektu jest publikowany na warunkach `GPL-2.0-or-later`.
