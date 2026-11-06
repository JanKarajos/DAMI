<?php
declare(strict_types=1);

const LOGIN_MAX_ATTEMPTS = 8;
const LOGIN_WINDOW_SEC = 900;

function login_throttled(string $area): bool
{
    $s = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND area = ? AND created_at > ?');
    $s->execute([client_ip(), $area, time() - LOGIN_WINDOW_SEC]);
    return (int) $s->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
}

function login_failed(string $area): void
{
    try {
        db()->prepare('INSERT INTO login_attempts (ip, area, created_at) VALUES (?, ?, ?)')
            ->execute([client_ip(), $area, time()]);
        db()->prepare('DELETE FROM login_attempts WHERE created_at < ?')->execute([time() - 86400]);
    } catch (PDOException $ex) {
        error_log('[DAMI] ' . $ex->getMessage());   // napr. databáza iba na čítanie – prihlásenie nesmie spadnúť
    }
}

function login_clear(string $area): void
{
    try {
        db()->prepare('DELETE FROM login_attempts WHERE ip = ? AND area = ?')->execute([client_ip(), $area]);
    } catch (PDOException $ex) {
        error_log('[DAMI] ' . $ex->getMessage());
    }
}

/* ---------- Admin ---------- */

function admin_attempt_login(string $username, string $password): bool
{
    $s = db()->prepare('SELECT * FROM admins WHERE username = ?');
    $s->execute([$username]);
    $a = $s->fetch();
    if (!$a || !password_verify($password, $a['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $a['id'];
    return true;
}

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    static $cache = null;
    if ($cache === null) {
        $s = db()->prepare('SELECT * FROM admins WHERE id = ?');
        $s->execute([$_SESSION['admin_id']]);
        $cache = $s->fetch() ?: null;
    }
    return $cache;
}

function require_admin(bool $allowMustChange = false): array
{
    $a = current_admin();
    if (!$a) {
        redirect('admin/prihlasenie.php');
    }
    if (!$allowMustChange && (int) $a['must_change_password'] === 1) {
        flash('info', 'Z bezpečnostných dôvodov si pred pokračovaním zmeňte predvolené heslo.');
        redirect('admin/heslo.php');
    }
    return $a;
}

/* ---------- Terapeut ---------- */

function therapist_attempt_login(string $email, string $password): bool
{
    $s = db()->prepare('SELECT * FROM therapists WHERE email = ? AND active = 1');
    $s->execute([$email]);
    $t = $s->fetch();
    if (!$t || !password_verify($password, $t['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['therapist_id'] = (int) $t['id'];
    return true;
}

function current_therapist(): ?array
{
    if (empty($_SESSION['therapist_id'])) {
        return null;
    }
    static $cache = null;
    if ($cache === null) {
        $s = db()->prepare('SELECT * FROM therapists WHERE id = ? AND active = 1');
        $s->execute([$_SESSION['therapist_id']]);
        $cache = $s->fetch() ?: null;
        if (!$cache) {
            unset($_SESSION['therapist_id']);
        }
    }
    return $cache;
}

function require_therapist(bool $allowMustChange = false): array
{
    $t = current_therapist();
    if (!$t) {
        redirect('terapeut/prihlasenie.php');
    }
    if (!$allowMustChange && (int) $t['must_change_password'] === 1) {
        flash('info', 'Pri prvom prihlásení si prosím nastavte vlastné heslo.');
        redirect('terapeut/profil.php');
    }
    return $t;
}

function validate_new_password(string $new, string $confirm): ?string
{
    if (mb_strlen($new) < 8) {
        return 'Nové heslo musí mať aspoň 8 znakov.';
    }
    if ($new !== $confirm) {
        return 'Heslá sa nezhodujú.';
    }
    return null;
}
