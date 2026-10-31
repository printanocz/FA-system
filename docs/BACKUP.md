# Zálohy a obnova

Denně v **02:30** (scheduler) vznikne záloha, která obsahuje:

- SQL dump celé databáze (`database.sql`),
- archivní PDF vystavených faktur (`storage/app/private`, v záloze jako `storage/private/…`),
- `MANIFEST.json` (čas, počet souborů).

Vše je zabalené do ZIP a **zašifrované** (libsodium, `crypto_secretstream_xchacha20poly1305`, klíč `BACKUP_KEY`)
do souboru `splatno-RRRRMMDD-HHMMSS.zip.enc`. Soubor se uloží do `storage/app/backups/` a, když je nastavené off-site
úložiště, také do něj (Backblaze B2, S3-kompatibilní).

**Retence:** poslední záloha každého z posledních 14 dní + poslední záloha každého měsíce po dobu 10 let
(archivace dokladů). Ostatní se po každé záloze mažou (lokálně i off-site). Soubory s jiným jménem se nikdy nemažou.

## Nastavení (jednorázově)

1. Vygenerujte klíč a vložte ho do `.env` jako `BACKUP_KEY`:

   ```
   php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
   ```

   **Klíč si uložte i mimo server** (správce hesel, papír v trezoru). Bez něj se zálohy nedají otevřít a server s klíčem
   může být to, co se ztratí.

2. Off-site úložiště (doporučeno): v Backblaze B2 nastavte přesně toto a doplňte hodnoty do `.env`:

   - **Bucket: Private** (nikdy Public).
   - **Lifecycle Settings: „Keep only the last version of the file“.** Jinak smazané (vyřazené) zálohy zůstanou v B2
     jako skryté verze a dál zabírají místo i za ně platíte.
   - **Application Key omezený jen na tento bucket** (Allow access to Bucket(s) → vybrat ho), ne Master Application Key.
     Stačí čtení a zápis, včetně mazání souborů (potřebuje ho promazávání starých záloh).

   ```
   BACKUP_S3_KEY=<keyID>
   BACKUP_S3_SECRET=<applicationKey>
   BACKUP_S3_BUCKET=<název bucketu>
   BACKUP_S3_ENDPOINT=https://s3.<region>.backblazeb2.com
   BACKUP_S3_REGION=<region, např. eu-central-003>
   ```

   Bez těchto hodnot zůstává jen lokální kopie a v Nastavení › Záloha a export dat se ukáže varování.

3. Scheduler musí běžet (cron každou minutu `php artisan schedule:run`, nebo záložní URL `/_cron/{token}`).

4. Ověřte: Nastavení › Záloha a export dat › **Zálohovat teď**, nebo `php artisan app:backup:run`.

## Hlídání

- Když poslední úspěšná záloha je starší než **48 hodin** (nebo žádná nebyla), v aplikaci se nahoře ukáže červený banner.
- `/_health` vrací `backup_age_hours` (stáří poslední úspěšné zálohy v hodinách, `null` když žádná není). Doporučení: alarm,
  když je hodnota větší než 48.

## Obnova krok za krokem

1. **Stáhněte zálohu.** Lokální kopie je v `storage/app/backups/` (FTP), off-site v bucketu B2 (web B2 → Browse Files → Download).
2. **Dešifrujte.** Na stroji, kde je aplikace a v `.env` správný `BACKUP_KEY`:

   ```
   php artisan app:backup:decrypt splatno-20261031-023000.zip.enc obnova.zip
   ```

   Špatný klíč, poškozený nebo useknutý soubor příkaz odmítne s chybou (nic neuvěří napůl).

3. **Rozbalte** `obnova.zip`. Uvnitř je `database.sql`, složka `storage/private/` a `MANIFEST.json`.
4. **Databáze.** V phpMyAdminu vyberte databázi aplikace (při obnově na prázdný server ji nejdřív vytvořte, kódování
   `utf8mb4_unicode_ci`) → **Import** → `database.sql`. Pro velké soubory import rozdělte nebo použijte `mysql < database.sql`, má-li hosting SSH.
5. **Soubory.** Obsah složky `storage/private/` nahrajte (FTP) do `storage/app/private/` aplikace (zachovejte podsložky `documents/RRRR/`).
6. **Závěr.** `.env` obnovte z vlastní kopie (záloha `.env` neobsahuje, držte si ho zvlášť). Spusťte `php artisan migrate --force`
   (doplní případné novější migrace), `php artisan optimize:clear`, přihlaste se a zkontrolujte poslední fakturu a její PDF.

## Export dat (bez šifrování)

Nastavení › Záloha a export dat › **Exportovat data** stáhne ZIP s PDF vystavených dokladů (do složek podle typu:
`faktury-pdf/`, `nabidky-pdf/` …) a CSV (UTF-8, oddělovač středník):

- `doklady.csv` – všechny typy dokladů, sloupec `typ` (faktura, nabídka, zálohová faktura, dobropis),
- `polozky.csv`, `uhrady.csv` – navázané na doklad sloupci `typ` + `cislo`,
- `kontakty.csv`, `cenik.csv`.

Textové hodnoty začínající `=`, `+`, `-`, `@` nebo tabulátorem mají na začátku apostrof, aby je Excel nevyhodnotil jako vzorec;
čísla a data se nemění. Vytvoří se při stažení a nikde se neukládá.
Export **není záloha** (neobsahuje nastavení ani se nedá jednoduše vrátit do aplikace) a je nešifrovaný: neukládejte ho na sdílená místa.

## Pracovní složka

Záloha i export se skládají v `storage/app/backup-work/` (práva 0700, mimo disky `local` i `backups`, v `.gitignore`), ne v `/tmp`:
na sdíleném hostingu může `open_basedir` `/tmp` blokovat a nešifrovaný dump tam nemá co dělat. Po dokončení (i po chybě)
se dočasné soubory smažou; zbylé po pádu procesu lze bezpečně smazat ručně.

## Spouštění přes `/_cron/{token}`

Když scheduler běží přes URL, endpoint nastaví `ignore_user_abort(true)` a `set_time_limit(0)`, takže záloha doběhne,
i když volající služba spojení přeruší nebo jí vyprší timeout.

## Databáze bez `mysqldump`

Když hosting zakazuje `exec`/`proc_open` nebo `mysqldump` chybí, použije se čistě PHP dumper (`druidfi/mysqldump-php`),
výsledek je stejný SQL soubor. Dump je vždy v `utf8mb4` a před každým `CREATE TABLE` obsahuje `DROP TABLE IF EXISTS`. Cestu k `mysqldump` mimo `PATH` lze zadat v `BACKUP_MYSQLDUMP_PATH`.
