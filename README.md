# OZ DAMI – online rezervácia terapií

Rezervačný systém pre Občianske združenie DAMI (Prešov). Má tri časti:

| Časť | Adresa | Na čo slúži |
|---|---|---|
| **Verejný web** | `/index.php`, `/rezervacia.php`, `/kontakt.php` | Prehľad terapií, diagnostiky a tímu. Rezervácia v 4 krokoch: služba → terapeut → kalendár s voľnými časmi → kontaktné údaje. Služby „iba na dopyt“ (INPP) majú namiesto kalendára kontaktný formulár. |
| **Portál terapeuta** | `/terapeut/` | Prihlásenie údajmi od admina. Vypisovanie termínov (jednotlivo aj hromadne), prehľad a rušenie rezervácií, úprava telefónu, popisu a fotky, zmena hesla. |
| **Administrácia** | `/admin/` | Správa terapeutov, terapií a diagnostiky, dopyty z kontaktného formulára, priradenie terapeut × terapia, heslá terapeutov, prehľad všetkých rezervácií. |

## Požiadavky

- PHP 8.1+ s rozšíreniami `pdo_sqlite` a `mbstring` (bežný slovenský webhosting, napr. Websupport)
- Nepotrebuje MySQL ani Composer – dáta sú v SQLite súbore `data/dami.sqlite`, ktorý sa vytvorí sám.

## Inštalácia

1. Nahrajte súbory na hosting (napr. do subdomény `rezervacie.ozdami.sk`).
2. Priečinky `data/` a `uploads/therapists/` musia byť zapisovateľné pre PHP. Priečinky `data/`, `app/` a `cron/` sú chránené cez `.htaccess` a v `uploads/` sa nespúšťajú skripty. Ak hosting nepoužíva Apache, nastavte to isté v konfigurácii servera.
3. Skopírujte `config.local.example.php` ako `config.local.php`. Nastavte `base_url` a odosielanie e-mailov (SMTP).
4. Otvorte `/admin/` a prihláste sa ako **admin / admin123**. Systém vás hneď vyzve zmeniť heslo aj meno.
5. V administrácii vytvorte terapeutov a priraďte im terapie. Prihlasovacie údaje sa terapeutovi zobrazia a voliteľne odošlú e-mailom.

Lokálne spustenie:

```bash
php -d upload_max_filesize=10M -d post_max_size=12M -S localhost:8000
```

## Tím z ozdami.sk

Pri prvom spustení, alebo pri aktualizácii databázy na verziu 3, sa automaticky pridá 8 ľudí zo sekcie Personál na ozdami.sk. Pridajú sa s titulmi, pozíciou, popisom, fotkami (`app/seed/team/`) a priradenými terapiami.

E-maily terapeutov na webe nie sú, preto majú zástupnú adresu `…@doplnte-email.invalid`. Na zástupnú adresu sa nič neposiela a v administrácii je označená „doplňte e-mail“. Admin doplní skutočný e-mail a cez „Nastaviť nové heslo“ pošle terapeutovi prihlasovacie údaje.

## Terapie, diagnostika a kontaktný formulár

Každá služba má v administrácii (Služby) dve nastavenia:

- **Typ:** terapia alebo diagnostika. Diagnostika (ADOS-2) sa na webe aj v rezervácii zobrazuje v samostatnej sekcii.
- **Spôsob objednania:** online kalendár alebo iba kontaktný formulár. Pri formulári (INPP) klient nevidí kalendár, ale pošle dopyt cez `/kontakt.php`. Dopyt sa uloží do sekcie **Dopyty** v administrácii. E-mail dostane centrum (`contact.email`), kópia `mail.admin_copy` a terapeuti priradení k službe. Klientovi príde automatické potvrdenie.

Formulár má ochranu proti spamu (skryté pole a najviac 5 dopytov za hodinu z jednej IP adresy). Pri aktualizácii databázy na verziu 4 sa INPP automaticky prepne na kontaktný formulár a ADOS-2 na diagnostiku.

## Fotky terapeutov

- Fotku nahráva terapeut v portáli (Profil) alebo admin pri úprave terapeuta. Povolené sú formáty JPG, PNG a WEBP do 8 MB.
- Fotka sa oreže na štvorec 600×600 px a prekóduje do JPG. Pri prekódovaní sa odstránia aj EXIF a GPS údaje. Potrebné je rozšírenie PHP `gd`.
- Súbory sa ukladajú do `uploads/therapists/`. Limit veľkosti nahrávania zvyšuje súbor `.user.ini`.

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
| `contact.*` | prevádzka Čajkovského 17, e-mail, telefón | Zobrazuje sa v hlavičke, päte a v e-mailoch. |

## Dizajn

Vzhľad je prevzatý z www.ozdami.sk:
- paleta (#0D6FB0 → #27A2DA, akcenty #D81B81 / #8A5B9E), systémové písmo,
- plávajúca „pill“ navigácia, gradientové tlačidlá, oblé karty, gradientová päta,
- logo `assets/logo.png` a favicon `assets/favicon.png`.

Všetky farby sú v CSS premenných na začiatku `assets/style.css`.

## Bezpečnosť

- Heslá sú uložené ako hash (`password_hash`).
- Všetky formuláre majú CSRF token a databázové dotazy používajú prepared statements.
- Neúspešné prihlásenia sa obmedzujú (8 pokusov za 15 minút).
- Rezervačný formulár má honeypot proti spamu.
- Termín sa nedá rezervovať dvakrát. Bráni tomu transakcia aj unikátny index v databáze.
