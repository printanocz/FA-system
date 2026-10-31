# Nasazení na splatno.adamfujak.cz

Merge do `main` aplikaci sám sestaví a nahraje na hosting (Gigaserver, bez SSH). Tenhle návod popisuje, co je potřeba připravit **jednou ručně**, jak nasazení probíhá a co dělat, když se něco pokazí.

## Jak to funguje

1. Push do `main` spustí workflow `.github/workflows/deploy.yml`.
2. Pokud chybí některý GitHub secret, workflow vypíše který a skončí úspěchem bez nasazení.
3. Spustí se Pint, PHPStan a Pest. Když cokoli selže, nenasazuje se.
4. Sestaví se produkční `vendor/` (`composer install --no-dev --optimize-autoloader`) a frontend (`npm ci`, `npm run build`).
5. Přes šifrované SFTP/FTP (`lftp`) se nahraje:
   1. `storage/framework/down` (údržbový režim),
   2. kód: `app`, `bootstrap` (bez `bootstrap/cache/*.php`), `config`, `database`, `lang`, `resources`, `routes`, `public` (bez `public/storage`), `artisan`, `composer.json`, `composer.lock`. U těchto složek se smazané soubory mažou i na serveru. `vendor/` se nahrává jen když se změnil `composer.lock`,
   3. `storage/app/deploy.pending` se SHA commitu (značka „nasazení čeká na dokončení“).
6. **Nikdy se nenahrává ani nemaže:** `.env`, `storage/` (kromě dvou značek výše a prázdné kostry složek při prvním nasazení), `node_modules`, `tests`, `.git*`.
7. Cron na serveru každou minutu spustí `schedule:run`, ten `app:post-deploy`. Pokud existuje značka, provede se `optimize:clear` (zahodí starou cache konfigurace a tras), `migrate --force`, `db:seed --class=InitialSetupSeeder --force` (je idempotentní), `optimize`, `view:cache`, `up`. Pak se SHA zapíše do `storage/app/deployed-version` a značka se smaže. Průběh a čas jsou v `storage/logs/deploy.log`.
8. Pipeline volá `/_health` každých 15 s (nejdéle `DEPLOY_WAIT_MINUTES`, výchozí 5 minut), dokud verze není ta nová a aplikace není mimo údržbu. Jinak běh selže.

Selže-li migrace, aplikace **zůstane v údržbě** a značka zůstane, takže se další minutu zkusí znovu (po opravě stačí nahrát opravu přes nový merge, nebo problém odstranit na serveru).

## Jednorázová příprava (Adam)

### 1. Subdoména a PHP

- Založ subdoménu `splatno.adamfujak.cz` s **document rootem na `…/splatno/public`** (složka `splatno` je kořen aplikace, `public` její podsložka).
- Zapni HTTPS (certifikát) a PHP **8.4**.
- Ověř PHP rozšíření `gd`, `intl`, `mbstring`, `zip`, `pdo_mysql`, `openssl`, `fileinfo` a jestli jsou povolené `exec` a `proc_open`.
- Složky `storage/` a `bootstrap/cache/` musí být zapisovatelné pro PHP (nahrává se prázdná kostra, práva zkontroluj po prvním nasazení).

### 2. Databáze

Založ MySQL/MariaDB databázi a uživatele s právy jen k ní. Poznamenej si název, uživatele a heslo (do `.env`, nikdy do repa ani chatu).

### 3. Tokeny a APP_KEY

Vygeneruj čtyři hodnoty. **HEALTH_TOKEN, CRON_TOKEN a SETUP_TOKEN musí mít každý nejméně 40 znaků** a musí být různé.

V Git Bash nebo na macOS/Linuxu:

```
openssl rand -base64 32          # APP_KEY: výsledek vlož jako APP_KEY=base64:<výsledek>
openssl rand -hex 32             # HEALTH_TOKEN (64 znaků)
openssl rand -hex 32             # CRON_TOKEN
openssl rand -hex 32             # SETUP_TOKEN
```

V PowerShellu (Windows):

```powershell
# APP_KEY
$b = New-Object byte[] 32; [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($b); 'base64:' + [Convert]::ToBase64String($b)

# token (spusť třikrát)
$b = New-Object byte[] 32; [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($b); -join ($b | ForEach-Object { $_.ToString('x2') })
```

### 4. `.env` na serveru

Zkopíruj `.env.production.example` jako `.env` do **kořene aplikace** (`…/splatno/.env`, vedle složky `public`, ne do ní) a doplň:

- `APP_KEY`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`,
- `MAIL_*` podle SMTP Gigaserveru,
- `HEALTH_TOKEN` (stejná hodnota půjde do GitHub secretu),
- `CRON_TOKEN` jen pokud hosting umí cron pouze přes URL,
- `SETUP_TOKEN` pro založení prvního uživatele (po použití smazat).

`.env` se nahrává ručně (např. přes správce souborů hostingu), pipeline se ho nikdy nedotkne. `APP_KEY` po prvním nasazení neměň.

### 5. Cron

Jedna z variant, podle toho, co hosting umí.

**A) Příkazový cron (preferovaná), každou minutu:**

```
* * * * * /usr/bin/php8.4 /cesta/k/splatno/artisan schedule:run >> /dev/null 2>&1
```

Přesnou cestu k PHP a k aplikaci najdeš v administraci hostingu.

**B) Cron jen přes URL** (kdyby hosting neuměl příkazy): nastav v `.env` `CRON_TOKEN` a v administraci cron na adresu

```
https://splatno.adamfujak.cz/_cron/<CRON_TOKEN>
```

s intervalem **jedna minuta** (nejkratší, který hosting dovolí). Adresa funguje i během údržby a bez přihlášení, jen s tajným tokenem. Při delším intervalu se nasazení dokončí až po něm.

Pokud hosting dovolí jen delší interval (např. 10 minut), nastav v GitHubu repo proměnnou `DEPLOY_WAIT_MINUTES` (viz níže) alespoň na tento interval plus rezervu, jinak healthcheck v pipeline selže, i když se nasazení později dokončí.

### 6. GitHub secrets

V repu: **Settings → Secrets and variables → Actions → New repository secret**.

| Secret | Hodnota |
|---|---|
| `FTP_HOST` | adresa serveru, případně s portem (`host:port`) |
| `FTP_USER` | přihlašovací jméno |
| `FTP_PASSWORD` | heslo (nikdy do chatu ani repa) |
| `FTP_PATH` | cesta ke **kořeni aplikace** na serveru, např. `/splatno` (složka, ve které je `public/`) |
| `FTP_PROTOCOL` | `sftp`, nebo `ftp` (= FTP přes TLS) |
| `HEALTH_TOKEN` | stejná hodnota jako v `.env` na serveru |
| `SFTP_HOST_KEY` | *volitelné, jen SFTP*: řádek `known_hosts` serveru, např. výsledek `ssh-keyscan -t ed25519 <host>` |
| `FTP_CERT_SHA1` | *volitelné, jen FTP*: SHA-1 otisk certifikátu serveru, pokud hosting nepoužívá certifikát od veřejné autority (chyba „The certificate issuer is unknown“). Otisk je v logu té chyby, např. `2E:8C:C9:…` |

Nešifrované FTP pipeline nikdy nepoužije: `ftp` znamená vynucené TLS a ověřený certifikát. Bez `SFTP_HOST_KEY` se klíč SFTP serveru přijme při každém spojení (workflow na to upozorní); s ním se ověřuje.

S `FTP_CERT_SHA1` pipeline certifikát stáhne ze serveru a použije ho jen tehdy, když jeho otisk přesně sedí. Při jiném otisku (např. po obnově certifikátu hostingem) nasazení skončí chybou a otisk se musí v secretu aktualizovat. Ověření se tím nevypíná, jen se připne na konkrétní server.

Volitelně lze v **Settings → Secrets and variables → Actions → Variables** nastavit:

| Proměnná | Význam |
|---|---|
| `APP_URL` | adresa aplikace, pokud není `https://splatno.adamfujak.cz` (musí být `https://`) |
| `DEPLOY_WAIT_MINUTES` | jak dlouho pipeline čeká na dokončení nasazení cronem; výchozí 5, nejvýše 30 (kontrola každých 15 s) |

### 7. První nasazení

1. Ujisti se, že je nahraný `.env` a nastavený cron.
2. Spusť workflow **Deploy** ručně (Actions → Deploy → Run workflow na `main`) nebo mergni do `main`.
3. První nahrání `vendor/` jsou tisíce souborů a trvá dlouho (desítky minut, workflow mu dává hodinu). Další nasazení `vendor/` přeskakují.
4. Po minutě až dvou cron dokončí nasazení a healthcheck v pipeline zezelená.

### 8. První uživatel

Aplikace nemá registraci. Uživatele založ přes jednorázovou stránku:

1. V `.env` na serveru musí být `SETUP_TOKEN` (nejméně 40 znaků).
2. Otevři `https://splatno.adamfujak.cz/setup?token=<SETUP_TOKEN>`.
3. Vyplň jméno, e-mail a heslo (nejméně 12 znaků) a vytvoř účet.
4. Stránka po vytvoření účtu navždy skončí 404 (existuje jen pokud v databázi není žádný uživatel).
5. **Smaž `SETUP_TOKEN` z `.env`** a pak spusť Deploy ručně (viz „Změna `.env`“ níže), jinak zůstane token v uložené cache konfigurace.
6. Přihlas se a zapni dvoufázové ověření.

## Změna `.env` (i smazání `SETUP_TOKEN`)

`.env` se mění ručně na serveru a aplikace běží s uloženou cache konfigurace, takže **po každé úpravě `.env` (i po smazání `SETUP_TOKEN`) spusť Deploy ručně**: Actions → Deploy → Run workflow na `main`. Nový commit není potřeba. Pipeline nahraje značku `deploy.pending`, `app:post-deploy` zahodí a znovu vytvoří cache a změna se projeví.

## Healthcheck

`GET /_health` s hlavičkou `X-Health-Token: <HEALTH_TOKEN>` vrací JSON:

```json
{"app": "ok", "db": "ok", "version": "<SHA commitu>", "maintenance": false}
```

Bez tokenu nebo se špatným tokenem odpoví 404. Žádné další údaje nevrací. Funguje i v údržbovém režimu.

## Návrat na předchozí verzi

Návrat = **revert commitu v `main`** a nové nasazení (revert commit prochází stejnými testy a pipeline). Migrace jsou jen dopředné. Pokud nová verze už změnila databázi, revert kódu ji nevrátí; rollback databáze řeší až úkol T-13 (zálohy), do té doby se chyby v migracích opravují další migrací.

## Řešení potíží

- **Workflow skončil zeleně, ale nic se nenasadilo:** chybí secrets, v logu běhu je jejich seznam.
- **Selhala kontrola Pint/PHPStan/Pest:** nasazení se nespustilo, oprav v novém PR.
- **Selhal upload:** aplikace může zůstat v údržbě. Znovu spusť workflow (Run workflow); nahrávají se jen změněné soubory.
- **Healthcheck nepotvrdil verzi:** podívej se do `storage/logs/deploy.log` na serveru. Častá příčina je chybějící `.env`, špatné DB údaje nebo nenastavený cron. Po opravě stačí počkat na cron (značka `deploy.pending` zůstává, takže se nasazení dokončí samo).
- **Stránka je pořád v údržbě a značka `storage/app/deploy.pending` neexistuje:** nasazení skončilo, ale `up` neproběhl. Smaž soubor `storage/framework/down` ručně.
- **Chyba 500 po nasazení:** nastav dočasně `LOG_LEVEL=debug` v `.env` a podívej se do `storage/logs/laravel.log` (potom vrať na `warning`).
