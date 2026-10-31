# Písma pro PDF faktur

Písma dokladu (TTF). PDF je použije, jakmile existují; chybějící písmo nahradí `dejavusans`
(umí češtinu) a do logu se jednou za den zapíše varování.

| Soubor | Použití | Licence |
|---|---|---|
| `DMSans-Regular/Bold/Italic/BoldItalic.ttf` | text dokladu | SIL OFL 1.1 (`OFL.txt`) |
| `DMMono-Regular.ttf` | čísla účtů, IBAN, VS | SIL OFL 1.1 (`OFL.txt`) |
| `CabinetGrotesk-Bold.ttf` | číslo faktury a celková částka | dodá Adam; do té doby se použije DM Sans |

DM Sans a DM Mono pocházejí z npm balíčků `@expo-google-fonts/dm-sans` a `@expo-google-fonts/dm-mono`.
Testy na přítomnosti Cabinet Grotesk nezávisí.
