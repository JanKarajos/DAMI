# OZ DAMI – online rezervácia terapií

Rezervačný systém pre Občianske združenie DAMI (Prešov). Má tri časti:

| Časť | Adresa | Na čo slúži |
|---|---|---|
| **Verejný web** | `/index.php`, `/rezervacia.php`, `/kontakt.php` | Prehľad terapií, diagnostiky a tímu. Rezervácia v 4 krokoch: služba → terapeut → kalendár s voľnými časmi → kontaktné údaje. Služby „iba na dopyt“ (ABA, INPP) majú namiesto kalendára kontaktný formulár. |
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
- **Spôsob objednania:** online kalendár alebo iba kontaktný formulár. Pri formulári (ABA, INPP) klient nevidí kalendár, ale pošle dopyt cez `/kontakt.php`. Dopyt sa uloží do sekcie **Dopyty** v administrácii. E-mail dostane centrum (`contact.email`), kópia `mail.admin_copy` a terapeuti priradení k službe. Klientovi príde automatické potvrdenie.

Formulár má ochranu proti spamu (skryté pole a najviac 5 dopytov za hodinu z jednej IP adresy). Pri aktualizácii databázy sa INPP (verzia 4) a ABA (verzia 5) automaticky prepnú na kontaktný formulár a ADOS-2 na diagnostiku. Online sa teda objednáva Neurofeedback a ADOS-2. Spôsob objednania sa dá kedykoľvek zmeniť v administrácii.

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

## Synology NAS

Sú dve možnosti. **Odporúčame Container Manager (Docker)**, lebo všetko potrebné (PHP 8.3, GD, Apache) je pripravené v `Dockerfile`. Ak váš NAS Container Manager nepodporuje (napr. modely série „j“), použite Web Station.

### A) Container Manager (Docker)

1. V **Centre balíkov** nainštalujte **Container Manager**.
2. Vo **File Station** vytvorte priečinok, napr. `docker/dami`, a nahrajte doň obsah repozitára (ZIP z GitHubu → Code → Download ZIP, rozbaliť).
3. Vytvorte `config.local.php` podľa `config.local.example.php` (minimálne `base_url`, neskôr SMTP).
4. **Container Manager → Projekt → Vytvoriť**: názov `dami`, cesta `/docker/dami`, zdroj „Použiť existujúci docker-compose.yml“ → Ďalej → Hotovo. Prvé zostavenie trvá pár minút.
5. Aplikácia beží na `http://IP-NAS:8080` (port zmeníte v `docker-compose.yml`).

Databáza (`data/`), fotky (`uploads/`) a `config.local.php` zostávajú v priečinku `docker/dami` – zálohujte ho (Hyper Backup). **Aktualizácia:** nahrajte nové súbory (okrem `data/`, `uploads/` a `config.local.php`) a v Container Manager projekt zastavte a znova spustite (pri zmene `Dockerfile` zvoľte „Zostaviť“).

### B) Web Station (bez Dockera)

1. V **Centre balíkov** nainštalujte **Web Station**, **Apache HTTP Server 2.4** a **PHP 8.2** (alebo novšie).
2. **Web Station → Nastavenia skriptovacieho jazyka → PHP → Vytvoriť** profil: PHP 8.2 a zapnite rozšírenia `gd`, `exif`, `mbstring`, `openssl`, `pdo_sqlite`, `sqlite3`.
3. Do zdieľaného priečinka `web` nahrajte aplikáciu, napr. `web/dami`.
4. **Web Station → Webová služba → Vytvoriť → Natívny skriptovací jazyk**: koreň dokumentu `web/dami`, **HTTP back-end server: Apache 2.4** (nie Nginx – iba Apache číta `.htaccess`, ktoré chránia databázu a konfiguráciu), profil PHP z bodu 2.
5. **Webový portál → Vytvoriť**: portál pre túto službu, napr. podľa portu (8080) alebo podľa názvu hostiteľa.
6. Vo File Station → `web/dami/data` a `web/dami/uploads` → Vlastnosti → Povolenie: pridajte používateľa **http** s právom **Čítanie/Zápis** (použiť aj na podpriečinky).

Po spustení overte, že `http://…/data/dami.sqlite` aj `http://…/config.php` vracajú chybu 403 (zakázané).

### Prístup z internetu (HTTPS)

1. **Ovládací panel → Externý prístup → DDNS**: pridajte napr. `ozdami.synology.me` (alebo použite vlastnú doménu, napr. `rezervacie.ozdami.sk` s DNS záznamom na verejnú IP).
2. **Ovládací panel → Zabezpečenie → Certifikát → Pridať → Let's Encrypt** pre túto doménu.
3. **Ovládací panel → Prihlasovací portál → Rozšírené → Reverzné proxy → Vytvoriť**: zdroj `HTTPS`, názov hostiteľa `rezervacie.ozdami.sk`, port 443 → cieľ `HTTP`, `localhost`, port `8080`. Hlavičky `X-Forwarded-For` a `X-Forwarded-Proto` posiela Synology automaticky.
4. Na routeri presmerujte porty **443** (a 80 pre obnovu certifikátu) na IP adresu NAS.
5. V `config.local.php` nastavte `'base_url' => 'https://rezervacie.ozdami.sk'` – použije sa v odkazoch v e-mailoch.

Aplikácia za reverzným proxy správne rozpozná skutočnú IP návštevníka (obmedzenie pokusov o prihlásenie) aj HTTPS (bezpečné cookie) – dôveryhodné proxy sú v nastavení `trusted_proxies`.

### Pripomienky (Plánovač úloh)

**Ovládací panel → Plánovač úloh → Vytvoriť → Plánovaná úloha → Skript definovaný používateľom**, používateľ `root`, každých 15 minút, príkaz:

- Docker: `docker exec -u www-data dami php /var/www/html/cron/pripomienky.php`
- Web Station: `/usr/local/bin/php82 /volume1/web/dami/cron/pripomienky.php` (číslo podľa verzie PHP)

Potom môžete v `config.local.php` nastaviť `'pseudo_cron' => false`. Skript sa pri spustení ako `root` sám prepne na vlastníka priečinka `data/`, aby databáza nezmenila vlastníka.

### Riešenie problémov

**Administrácia → Prehľad → Diagnostika servera** ukáže, či sa dá zapisovať do databázy a priečinkov, aké sú nastavenia e-mailu a HTTPS. Umožní aj poslať testovací e-mail a zobrazí presnú chybu SMTP.

- **Chyba 500 po odoslaní formulára, ale stránky sa zobrazujú:** do databázy sa nedá zapisovať. V Dockeri reštartujte projekt (práva sa pri štarte opravia). Vo Web Station dajte používateľovi `http` právo Čítanie/Zápis na `data` a `uploads`.
- **Prehliadač píše „Nezabezpečené“:** certifikát nepatrí k doméne. V **Ovládací panel → Zabezpečenie → Certifikát** vytvorte Let's Encrypt certifikát pre presnú doménu a v **Nastavenia** ho priraďte k položke reverzného proxy.
- **Podrobnosti chýb** sú v logu kontajnera: **Container Manager → Kontajner → dami → Log**.

### E-maily

Domáca IP adresa NAS nie je vhodná na priame odosielanie pošty – použite SMTP schránku (hosting domény ozdami.sk alebo Gmail s heslom aplikácie), viď časť E-maily a pripomienky.

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
