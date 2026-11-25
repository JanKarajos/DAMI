<?php

const LOGIN_MAX_ATTEMPTS = 8;
const LOGIN_WINDOW_SEC = 900;

function login_throttled($area)
{
    $s = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND area = ? AND created_at > ?');
    $s->execute([client_ip(), $area, time() - LOGIN_WINDOW_SEC]);
    return (int) $s->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
}

function login_failed($area)
{
    try {
        db()->prepare('INSERT INTO login_attempts (ip, area, created_at) VALUES (?, ?, ?)')
            ->execute([client_ip(), $area, time()]);
        db()->prepare('DELETE FROM login_attempts WHERE created_at < ?')->execute([time() - 86400]);
    } catch (PDOException $ex) {
        error_log('[DAMI] ' . $ex->getMessage());   // napr. databáza iba na čítanie – prihlásenie nesmie spadnúť
    }
}

function login_clear($area)
{
    try {
        db()->prepare('DELETE FROM login_attempts WHERE ip = ? AND area = ?')->execute([client_ip(), $area]);
    } catch (PDOException $ex) {
        error_log('[DAMI] ' . $ex->getMessage());
    }
}

/* ---------- Admin ---------- */

function admin_attempt_login($username, $password)
{
    $s = db()->prepare('SELECT * FROM admins WHERE username = ?');
    $s->execute([$username]);
    $a = $s->fetch();
    if ($a && password_verify($password, $a['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $a['id'];
        return true;
    }
    // Terapeut s právami administrátora sa prihlasuje svojím e-mailom a heslom.
    $s = db()->prepare('SELECT * FROM therapists WHERE email = ? AND active = 1 AND is_admin = 1');
    $s->execute([mb_strtolower(trim($username))]);
    $t = $s->fetch();
    if ($t && password_verify($password, $t['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['therapist_id'] = (int) $t['id'];
        return true;
    }
    return false;
}

function current_admin()
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    if (!empty($_SESSION['admin_id'])) {
        $s = db()->prepare('SELECT * FROM admins WHERE id = ?');
        $s->execute([$_SESSION['admin_id']]);
        $cache = $s->fetch() ?: null;
    }
    // Prihlásený terapeut so zaškrtnutou voľbou „Administrátor“
    if (!$cache && ($t = current_therapist()) && (int) $t['is_admin'] === 1) {
        $cache = [
            'id'                   => 0,
            'username'             => $t['email'],
            'password_hash'        => $t['password_hash'],
            'must_change_password' => $t['must_change_password'],
            'therapist_id'         => (int) $t['id'],
        ];
    }
    return $cache;
}

/** Je administrátor zároveň terapeut (prihlásený cez svoj terapeutický účet)? */
function admin_is_therapist($a)
{
    return !empty($a['therapist_id']);
}

function require_admin($allowMustChange = false)
{
    $a = current_admin();
    if (!$a) {
        redirect('admin/prihlasenie.php');
    }
    if (!$allowMustChange && (int) $a['must_change_password'] === 1) {
        flash('info', 'Z bezpečnostných dôvodov si pred pokračovaním zmeňte predvolené heslo.');
        redirect(admin_is_therapist($a) ? 'terapeut/profil.php' : 'admin/heslo.php');
    }
    return $a;
}

/* ---------- Terapeut ---------- */

function therapist_attempt_login($email, $password)
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

function current_therapist()
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

function require_therapist($allowMustChange = false)
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

function validate_new_password($new, $confirm)
{
    if (mb_strlen($new) < 8) {
        return 'Nové heslo musí mať aspoň 8 znakov.';
    }
    if ($new !== $confirm) {
        return 'Heslá sa nezhodujú.';
    }
    return null;
}
