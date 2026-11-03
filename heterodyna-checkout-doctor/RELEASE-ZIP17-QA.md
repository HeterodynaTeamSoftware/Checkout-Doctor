# ZIP 17 — v1.16.0 · Final QA / Support Snapshot

## Rzeczywiste zmiany

- `HCD_Scan_Lock` zastępuje sekwencję `get_transient` + `set_transient` atomowym `INSERT IGNORE`, ma blokadę per administrator, automatyczne odzyskiwanie przeterminowanej blokady po 120 s i token właściciela.
- Nowy eksport JSON w „Zgodność i wydanie” przez `admin-post.php`, z autoryzacją i nonce. Eksport nie zawiera adresów URL, nazw aktywnych wtyczek, ścieżek, logów ani danych klientów. Może ujawniać wersje platformy, więc przed publikacją należy obejrzeć plik.
- Nowe testy `test-scan-lock-atomic.php`, `test-release-snapshot.php`, `test-release-snapshot-integration.php`.
- Nie ma automatycznego zatwierdzania publikacji: aktywne testy HPOS/Block i bramek w sandboxie nadal wymagają wykonania na stagingu.

## Kontrola przed GitHub / WordPress.org

- Weryfikacja `php -l`, Node.js, pełnych testów CLI i sum manifestu.
- Test instalacji/aktualizacji na rzeczywistym WP/WooCommerce oraz wszystkich siedmiu scenariuszy ręcznych nadal **NIEPOTWIERDZONY**.
- Dla WordPress.org uruchom oficjalne narzędzia Plugin Check oraz sprawdź wymagania katalogu. Samo archiwum ZIP nie oznacza akceptacji przez WordPress.org.
