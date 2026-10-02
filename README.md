# Harmonogram online

Generator harmonogramów zajęć w PDF (układ poziomy i pionowy) – PHP/HTML/CSS/JS, port programu `harmonogram.py`.

- zakładki: Harmonogram poziomy, Harmonogram pionowy, Ustawienia
- święta stałe i ruchome (Wielkanoc, Boże Ciało…) liczone automatycznie, ferie wpisywane ręcznie
- ustawienia zapisywane na serwerze (`data/ustawienia.php`), zapis chroniony hasłem
- PDF generuje TCPDF (dołączony w `tcpdf/`)

## Instalacja
1. Wgraj katalog na serwer z PHP ≥ 8 (rozszerzenia mbstring, zlib).
2. `cp config.example.php config.php` i wstaw hash hasła.
3. Katalog `data/` musi być zapisywalny dla serwera WWW.
