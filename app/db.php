<?php
declare(strict_types=1);

const PLACEHOLDER_EMAIL_DOMAIN = 'doplnte-email.invalid';

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $path = cfg('db_path');
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($version < 1) {
        migrate_v1($pdo);
    }
    if ($version < 2) {
        // v2: fotka terapeuta (relatívna cesta k súboru v uploads/)
        $pdo->exec("ALTER TABLE therapists ADD COLUMN photo TEXT NOT NULL DEFAULT ''");
        $pdo->exec('PRAGMA user_version = 2');
    }
    if ($version < 3) {
        // v3: titul za menom + tím z www.ozdami.sk
        $pdo->exec("ALTER TABLE therapists ADD COLUMN title_after TEXT NOT NULL DEFAULT ''");
        seed_team($pdo);
        $pdo->exec('PRAGMA user_version = 3');
    }
    if ($version < 4) {
        migrate_v4($pdo);
    }
}

/**
 * v4: typ služby (terapia / diagnostika), spôsob objednania (online kalendár / kontaktný formulár)
 * a tabuľka dopytov z kontaktného formulára.
 */
function migrate_v4(PDO $pdo): void
{
    $pdo->exec("ALTER TABLE therapies ADD COLUMN kind TEXT NOT NULL DEFAULT 'therapy'");          // therapy | diagnostic
    $pdo->exec("ALTER TABLE therapies ADD COLUMN booking_mode TEXT NOT NULL DEFAULT 'online'");   // online | inquiry
    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS inquiries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        therapy_id INTEGER NULL REFERENCES therapies(id) ON DELETE SET NULL,
        therapy_name TEXT NOT NULL DEFAULT '',
        client_name TEXT NOT NULL,
        client_email TEXT NOT NULL,
        client_phone TEXT NOT NULL DEFAULT '',
        child_name TEXT NOT NULL DEFAULT '',
        child_age TEXT NOT NULL DEFAULT '',
        message TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'new',   -- new | done
        created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    );
    CREATE INDEX IF NOT EXISTS idx_inquiries_created ON inquiries(created_at);
    SQL);
    // INPP – iba kontaktný formulár, ADOS-2 – diagnostika
    $pdo->exec("UPDATE therapies SET booking_mode = 'inquiry' WHERE name LIKE 'INPP%'");
    $pdo->exec("UPDATE therapies SET kind = 'diagnostic' WHERE name LIKE '%ADOS%'");
    $pdo->exec('PRAGMA user_version = 4');
}

/** Pridá tím OZ DAMI (len tých, ktorí ešte v databáze nie sú). */
function seed_team(PDO $pdo): void
{
    $team = require APP_ROOT . '/app/seed/team.php';
    $exists = $pdo->prepare('SELECT 1 FROM therapists WHERE first_name = ? AND last_name = ?');
    $therapy = $pdo->prepare('SELECT id FROM therapies WHERE name = ?');
    $ins = $pdo->prepare(
        'INSERT INTO therapists (title, first_name, last_name, title_after, email, phone, description, position,
            password_hash, must_change_password, active, sort_order)
         VALUES (?, ?, ?, ?, ?, \'\', ?, ?, ?, 1, 1, ?)'
    );
    $link = $pdo->prepare('INSERT OR IGNORE INTO therapist_therapy (therapist_id, therapy_id) VALUES (?, ?)');
    $photo = $pdo->prepare('UPDATE therapists SET photo = ? WHERE id = ?');

    foreach ($team as $i => $m) {
        $exists->execute([$m['first_name'], $m['last_name']]);
        if ($exists->fetchColumn()) {
            continue;
        }
        // E-mail zatiaľ neznámy – zástupná adresa (.invalid sa nikdy nedoručí), heslo náhodné a nikde nezobrazené.
        $ins->execute([
            $m['title'], $m['first_name'], $m['last_name'], $m['title_after'],
            $m['slug'] . '@' . PLACEHOLDER_EMAIL_DOMAIN, $m['description'], $m['position'],
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), ($i + 1) * 10,
        ]);
        $id = (int) $pdo->lastInsertId();
        foreach ($m['therapies'] as $name) {
            $therapy->execute([$name]);
            if ($tid = $therapy->fetchColumn()) {
                $link->execute([$id, $tid]);
            }
        }
        $src = APP_ROOT . '/app/seed/team/' . $m['slug'] . '.jpg';
        if (is_file($src) && function_exists('photo_store_image')) {
            [$rel] = photo_store_image($src, $id);
            if ($rel) {
                $photo->execute([$rel, $id]);
            }
        }
    }
}

function migrate_v1(PDO $pdo): void
{

    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        must_change_password INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    );

    CREATE TABLE IF NOT EXISTS therapists (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL DEFAULT '',
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE COLLATE NOCASE,
        phone TEXT NOT NULL DEFAULT '',
        description TEXT NOT NULL DEFAULT '',
        position TEXT NOT NULL DEFAULT '',
        password_hash TEXT NOT NULL,
        must_change_password INTEGER NOT NULL DEFAULT 1,
        active INTEGER NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    );

    CREATE TABLE IF NOT EXISTS therapies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '',
        duration_min INTEGER NOT NULL DEFAULT 60,
        price TEXT NOT NULL DEFAULT '',
        active INTEGER NOT NULL DEFAULT 1,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    );

    CREATE TABLE IF NOT EXISTS therapist_therapy (
        therapist_id INTEGER NOT NULL REFERENCES therapists(id) ON DELETE CASCADE,
        therapy_id INTEGER NOT NULL REFERENCES therapies(id) ON DELETE CASCADE,
        PRIMARY KEY (therapist_id, therapy_id)
    );

    -- Voľné časy, ktoré si zadáva terapeut. therapy_id NULL = slot pre ktorúkoľvek jeho terapiu.
    CREATE TABLE IF NOT EXISTS slots (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        therapist_id INTEGER NOT NULL REFERENCES therapists(id) ON DELETE CASCADE,
        therapy_id INTEGER NULL REFERENCES therapies(id) ON DELETE SET NULL,
        starts_at TEXT NOT NULL,   -- 'Y-m-d H:i'
        ends_at TEXT NOT NULL,
        note TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
        UNIQUE (therapist_id, starts_at)
    );
    CREATE INDEX IF NOT EXISTS idx_slots_start ON slots(starts_at);

    CREATE TABLE IF NOT EXISTS bookings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slot_id INTEGER NULL REFERENCES slots(id) ON DELETE SET NULL,
        therapist_id INTEGER NOT NULL REFERENCES therapists(id) ON DELETE CASCADE,
        therapy_id INTEGER NULL REFERENCES therapies(id) ON DELETE SET NULL,
        therapy_name TEXT NOT NULL,
        starts_at TEXT NOT NULL,
        ends_at TEXT NOT NULL,
        client_name TEXT NOT NULL,
        client_email TEXT NOT NULL,
        client_phone TEXT NOT NULL DEFAULT '',
        child_name TEXT NOT NULL DEFAULT '',
        note TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'confirmed',  -- confirmed | cancelled
        cancelled_by TEXT NULL,
        token TEXT NOT NULL UNIQUE,
        reminder_sent_at TEXT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    );
    CREATE INDEX IF NOT EXISTS idx_bookings_start ON bookings(starts_at);
    -- Jeden slot môže mať najviac jednu aktívnu rezerváciu.
    CREATE UNIQUE INDEX IF NOT EXISTS idx_bookings_active_slot
        ON bookings(slot_id) WHERE status = 'confirmed' AND slot_id IS NOT NULL;

    CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip TEXT NOT NULL,
        area TEXT NOT NULL,
        created_at INTEGER NOT NULL
    );
    SQL);

    // Úvodné dáta: admin + terapie uvedené na ozdami.sk
    $stmt = $pdo->prepare('INSERT INTO admins (username, password_hash, must_change_password) VALUES (?, ?, 1)');
    $stmt->execute([cfg('admin_default_user'), password_hash(cfg('admin_default_pass'), PASSWORD_DEFAULT)]);

    // Služby podľa www.ozdami.sk
    $t = $pdo->prepare('INSERT INTO therapies (name, description, duration_min, sort_order) VALUES (?, ?, ?, ?)');
    $seed = [
        ['Aplikovaná behaviorálna terapia (ABA)',
         'Vedecký prístup k učeniu a správaniu. Ciele vyberáme podľa toho, čo konkrétne dieťa potrebuje vo svojom každodennom živote – komunikácia, porozumenie reči, sebaobsluha a samostatnosť, hra a sociálne zručnosti, pozornosť a spolupráca, predškolské a školské zručnosti.', 60],
        ['INPP terapia neuromotorickej nezrelosti',
         'Programy vychádzajúce z metodiky INPP – Institute for Neuro-Physiological Psychology. Na začiatku odborné posúdenie, na jeho základe individuálny pohybový program (hrubá a jemná motorika, rovnováha a koordinácia, senzoricko-motorická integrácia, pozornosť a pripravenosť na učenie).', 60],
        ['Neurofeedback',
         'Tréning založený na biologickej spätnej väzbe mozgovej aktivity snímanej pomocou EEG. Podporný tréning pozornosti a koncentrácie, sebaregulácie, zvládania záťaže, kvality spánku, učenia a kognitívneho fungovania.', 45],
        ['Diagnostika ADOS-2',
         'Autism Diagnostic Observation Schedule – Second Edition: štandardizovaná observačná metodika na odborné posúdenie prejavov súvisiacich s poruchou autistického spektra (PAS). Vhodná pre deti, dospievajúcich aj dospelých.', 90],
    ];
    foreach ($seed as $i => [$name, $desc, $dur]) {
        $t->execute([$name, $desc, $dur, $i + 1]);
    }

    $pdo->exec('PRAGMA user_version = 1');
}

function setting_get(string $key, ?string $default = null): ?string
{
    $s = db()->prepare('SELECT value FROM settings WHERE key = ?');
    $s->execute([$key]);
    $v = $s->fetchColumn();
    return $v === false ? $default : (string) $v;
}

function setting_set(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')
        ->execute([$key, $value]);
}
