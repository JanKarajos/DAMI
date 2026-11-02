# OZ DAMI – online rezervácia terapií

Rezervačný systém pre Občianske združenie DAMI (Prešov). Má tri časti:

| Časť | Adresa | Na čo slúži |
|---|---|---|
| **Verejný web** | `/index.php`, `/rezervacia.php` | Prehľad terapií a tímu. Rezervácia v 4 krokoch: terapia → terapeut → kalendár s voľnými časmi → kontaktné údaje. |
| **Portál terapeuta** | `/terapeut/` | Prihlásenie údajmi od admina. Vypisovanie termínov (jednotlivo aj hromadne), prehľad a rušenie rezervácií, zmena hesla. |
| **Administrácia** | `/admin/` | Správa terapeutov a terapií, priradenie terapeut × terapia, heslá terapeutov, prehľad všetkých rezervácií. |

## Požiadavky

- PHP 8.1+ s rozšíreniami `pdo_sqlite` a `mbstring` (bežný slovenský webhosting, napr. Websupport)
- Nepotrebuje MySQL ani Composer – dáta sú v SQLite súbore `data/dami.sqlite`, ktorý sa vytvorí sám.

## Inštalácia

1. Nahrajte súbory na hosting (napr. do subdomény `rezervacie.ozdami.sk`).
2. Priečinok `data/` musí byť zapisovateľný pre PHP. Priečinky `data/`, `app/` a `cron/` sú chránené cez `.htaccess`. Ak hosting nepoužíva Apache, zablokujte prístup k nim v konfigurácii servera.
3. Skopírujte `config.local.example.php` ako `config.local.php`. Nastavte `base_url` a odosielanie e-mailov (SMTP).
4. Otvorte `/admin/` a prihláste sa ako **admin / admin123**. Systém vás hneď vyzve zmeniť heslo aj meno.
5. V administrácii vytvorte terapeutov a priraďte im terapie. Prihlasovacie údaje sa terapeutovi zobrazia a voliteľne odošlú e-mailom.

Lokálne spustenie:

```bash
php -S localhost:8000
```

## E-maily a pripomienky

- **Po rezervácii** dostane klient potvrdenie s odkazom na detail a zrušenie a s tlačidlom „Pridať do kalendára“. Terapeut dostane upozornenie, voliteľne aj kópia na `mail.admin_copy`.
- **Pripomienka** sa klientovi odošle `reminder_hours` (predvolene 24) hodín pred termínom.
- **Pri zrušení** (klientom, terapeutom alebo adminom) dostanú e-mail obe strany.
- `mail.transport = 'log'` (predvolené) e-maily neodosiela, iba ich zapisuje do `data/mail.log`. Je to určené na testovanie. V produkcii nastavte `'smtp'`.

Pripomienky odosiela cron:

```
*/15 * * * * php /cesta/k/aplikacii/cron/pripomienky.php
```

Ak hosting cron nemá, nechajte `pseudo_cron => true`. Kontrola sa potom spustí pri návšteve webu, najviac raz za 10 minút.

## Nastavenia (`config.php` / `config.local.php`)

| Kľúč | Predvolené | Význam |
|---|---|---|
| `min_booking_notice_hours` | 12 | Najskorší čas pred termínom, kedy sa ešte dá rezervovať. |
| `min_cancel_notice_hours` | 24 | Do kedy môže klient zrušiť termín online. |
| `booking_horizon_days` | 120 | Na koľko dní dopredu sa zobrazujú voľné termíny. |
| `contact.*` | adresa a e-mail OZ | Zobrazuje sa v hlavičke, päte a v e-mailoch. |

## Dizajn

Farby a písma sú v CSS premenných na začiatku `assets/style.css` (`--primary`, `--accent`, `--font-head`, …). Úpravou týchto premenných a súboru `assets/logo.svg` zladíte vzhľad s webom ozdami.sk.

## Bezpečnosť

- Heslá sú uložené ako hash (`password_hash`).
- Všetky formuláre majú CSRF token a databázové dotazy používajú prepared statements.
- Neúspešné prihlásenia sa obmedzujú (8 pokusov za 15 minút).
- Rezervačný formulár má honeypot proti spamu.
- Termín sa nedá rezervovať dvakrát. Bráni tomu transakcia aj unikátny index v databáze.
