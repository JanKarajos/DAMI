<?php

function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Relatívna cesta k rootu aplikácie (funguje aj v podpriečinku). */
function url($path = '')
{
    static $base = null;
    if ($base === null) {
        $base = '';
        $app = str_replace('\\', '/', realpath(APP_ROOT) ?: APP_ROOT);
        // 1) podľa spusteného skriptu – spoľahlivé aj na IIS (Windows), kde DOCUMENT_ROOT býva prázdny
        $script = isset($_SERVER['SCRIPT_FILENAME']) ? str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME']) ?: $_SERVER['SCRIPT_FILENAME']) : '';
        $name = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
        $found = false;
        if ($script !== '' && $name !== '' && stripos($script, $app . '/') === 0) {
            $rel = substr($script, strlen($app));                       // napr. /admin/index.php
            if (strcasecmp(substr($name, -strlen($rel)), $rel) === 0) {
                $base = substr($name, 0, strlen($name) - strlen($rel));
                $found = true;
            }
        }
        // 2) podľa DOCUMENT_ROOT
        if (!$found) {
            $docRoot = str_replace('\\', '/', realpath(isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '') ?: '');
            if ($docRoot !== '' && stripos($app, $docRoot) === 0) {
                $base = substr($app, strlen($docRoot));
            }
        }
        $base = rtrim($base, '/');
    }
    return $base . '/' . ltrim($path, '/');
}

/** Absolútna URL pre e-maily. */
function abs_url($path = '')
{
    return rtrim((string) cfg('base_url'), '/') . '/' . ltrim($path, '/');
}

function redirect($path)
{
    header('Location: ' . (preg_match('~^https?://~', $path) ? $path : url($path)));
    exit;
}

function flash($type, $msg)
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flashes()
{
    $f = isset($_SESSION['flash']) ? $_SESSION['flash'] : [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check()
{
    if (empty($_POST) && (int) (isset($_SERVER['CONTENT_LENGTH']) ? $_SERVER['CONTENT_LENGTH'] : 0) > 0) {
        http_response_code(413);
        exit('Odoslané dáta sú príliš veľké (pravdepodobne fotka). Vráťte sa späť a vyberte menší súbor.');
    }
    $sent = isset($_POST['_csrf']) ? $_POST['_csrf'] : (isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Platnosť formulára vypršala. Vráťte sa späť a obnovte stránku.');
    }
}

function is_post()
{
    return (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET') === 'POST';
}

function post($key, $default = '')
{
    $v = isset($_POST[$key]) ? $_POST[$key] : $default;
    return is_string($v) ? trim($v) : $default;
}

function json_out($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function therapist_full_name(array $t)
{
    $name = trim(($t['title'] ? $t['title'] . ' ' : '') . $t['first_name'] . ' ' . $t['last_name']);
    return !empty($t['title_after']) ? $name . ', ' . $t['title_after'] : $name;
}

const SK_DAYS = ['nedeľa', 'pondelok', 'utorok', 'streda', 'štvrtok', 'piatok', 'sobota'];
const SK_DAYS_SHORT = ['Ne', 'Po', 'Ut', 'St', 'Št', 'Pi', 'So'];
const SK_MONTHS = ['', 'január', 'február', 'marec', 'apríl', 'máj', 'jún', 'júl', 'august', 'september', 'október', 'november', 'december'];

/** napr. "streda 4. 11. 2026 o 14:00" */
function fmt_datetime($dt)
{
    $d = new DateTimeImmutable($dt);
    return SK_DAYS[(int) $d->format('w')] . ' ' . $d->format('j. n. Y') . ' o ' . $d->format('H:i');
}

function fmt_date($dt)
{
    $d = new DateTimeImmutable($dt);
    return SK_DAYS_SHORT[(int) $d->format('w')] . ' ' . $d->format('j. n. Y');
}

function fmt_time($dt)
{
    return (new DateTimeImmutable($dt))->format('H:i');
}

function random_password($len = 10)
{
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

/** IP adresa návštevníka. Za reverzným proxy (Synology, Docker) sa berie z X-Forwarded-For. */
function client_ip()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'cli';
    if (!ip_is_trusted_proxy($ip) || empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $ip;
    }
    // Sprava doľava: prvá adresa, ktorá nie je naše proxy, je skutočný klient.
    $chain = array_reverse(array_map('trim', explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])));
    foreach ($chain as $hop) {
        if (!filter_var($hop, FILTER_VALIDATE_IP)) {
            break;
        }
        if (!ip_is_trusted_proxy($hop)) {
            return $hop;
        }
        $ip = $hop;
    }
    return $ip;
}

/** Je požiadavka cez HTTPS (priamo alebo cez dôveryhodné proxy)? */
function request_is_https()
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return ip_is_trusted_proxy(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '')
        && strtolower((string) (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : '')) === 'https';
}

function ip_is_trusted_proxy($ip)
{
    foreach ((array) cfg('trusted_proxies', []) as $range) {
        if (ip_in_range($ip, (string) $range)) {
            return true;
        }
    }
    return false;
}

/** '10.0.0.5' v '10.0.0.0/8' alebo presná zhoda. Funguje pre IPv4 aj IPv6. */
function ip_in_range($ip, $range)
{
    list($net, $bits) = array_pad(explode('/', $range, 2), 2, null);
    $ipBin = @inet_pton($ip);
    $netBin = @inet_pton($net);
    if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
        return false;
    }
    $bits = $bits === null ? strlen($ipBin) * 8 : (int) $bits;
    $bytes = intdiv($bits, 8);
    if (substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
        return false;
    }
    $rest = $bits % 8;
    if ($rest === 0) {
        return true;
    }
    $mask = (0xFF << (8 - $rest)) & 0xFF;
    return (ord($ipBin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask);
}
