# Písma pro PDF faktur

Sem patří brandová písma dokladu (TTF). PDF je použije, jakmile existují; chybějící písmo
nahradí `dejavusans` (umí češtinu) a do logu se jednou za den zapíše varování.

| Soubor | Použití |
|---|---|
| `DMSans-Regular.ttf` (+ volitelně `DMSans-Bold.ttf`, `DMSans-Italic.ttf`, `DMSans-BoldItalic.ttf`) | text dokladu |
| `DMMono-Regular.ttf` | čísla účtů, IBAN, VS |
| `CabinetGrotesk-Bold.ttf` | číslo faktury a celková částka |

Soubory `*.ttf` se do gitu commitují stejně jako zbytek repozitáře; stačí je sem nahrát.
Testy na písmech nezávisí.
