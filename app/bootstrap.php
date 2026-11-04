<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$GLOBALS['config'] = require APP_ROOT . '/config.php';
date_default_timezone_set(cfg('timezone'));
mb_internal_encoding('UTF-8');

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/mailer.php';
require __DIR__ . '/booking.php';
require __DIR__ . '/inquiries.php';
require __DIR__ . '/photos.php';
require __DIR__ . '/layout.php';

if (PHP_SAPI !== 'cli') {
    set_exception_handler('render_fatal_error');
    session_name('dami_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => request_is_https(),
    ]);
    session_start();
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');

    if (cfg('pseudo_cron')) {
        maybe_run_pseudo_cron();
    }
}

function cfg(string $key, $default = null)
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Zrozumiteľná chybová stránka namiesto prázdnej „HTTP ERROR 500“. Detail ide do logu servera. */
function render_fatal_error(Throwable $ex): void
{
    $detail = get_class($ex) . ': ' . $ex->getMessage() . ' in ' . str_replace(APP_ROOT . '/', '', $ex->getFile()) . ':' . $ex->getLine();
    error_log('[DAMI] ' . $detail);
    // Posledná chyba pre Diagnostiku (ak sa dá zapisovať).
    @file_put_contents(APP_ROOT . '/data/last-error.txt', date('Y-m-d H:i:s') . ' ' . ($_SERVER['REQUEST_METHOD'] ?? '') . ' '
        . ($_SERVER['REQUEST_URI'] ?? '') . "\n" . $detail . "\n" . $ex->getTraceAsString() . "\n");
    $msg = $ex->getMessage();
    $hint = '';
    if (preg_match('/readonly database|unable to open database|disk I\/O error/i', $msg)) {
        $hint = 'Server nemá právo zapisovať do databázy (priečinok <code>data/</code> alebo súbory <code>dami.sqlite-wal</code> / <code>-shm</code>). '
            . 'V Dockeri reštartujte projekt (práva sa pri štarte opravia), vo Web Station dajte používateľovi <b>http</b> právo Čítanie/Zápis na priečinky <code>data</code> a <code>uploads</code>.';
    } elseif (stripos($msg, 'database is locked') !== false) {
        $hint = 'Databázu práve používa iný proces (napr. plánovaná úloha). Skúste to znova o chvíľu.';
    } elseif (stripos($msg, 'disk') !== false && stripos($msg, 'full') !== false) {
        $hint = 'Na disku servera nie je voľné miesto.';
    }
    $isAdmin = session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['admin_id']);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="sk"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Chyba servera</title><body style="font-family:-apple-system,BlinkMacSystemFont,Helvetica Neue,Arial,sans-serif;background:#f7fbfe;color:#183247;margin:0;padding:40px 16px">'
        . '<div style="max-width:720px;margin:0 auto;background:#fff;border-radius:24px;padding:32px;box-shadow:0 20px 50px rgba(13,111,176,.1)">'
        . '<h1 style="margin-top:0">Ospravedlňujeme sa, nastala chyba.</h1>'
        . '<p>Vašu požiadavku sa nepodarilo spracovať. Skúste to prosím neskôr alebo nás kontaktujte: '
        . htmlspecialchars((string) cfg('contact.email')) . ', ' . htmlspecialchars((string) cfg('contact.phone')) . '.</p>'
        . ($hint ? '<p style="background:#fdeef5;border-radius:14px;padding:14px 16px"><b>Pre správcu:</b> ' . $hint . '</p>' : '')
        . ($isAdmin ? '<p style="background:#eef6fb;border-radius:14px;padding:14px 16px;font-size:14px;word-break:break-word"><b>Detail chyby (vidí iba prihlásený admin):</b><br><code>'
            . htmlspecialchars($detail) . '</code></p>' : '<p style="font-size:13px;color:#5f7283">Správca: prihláste sa do administrácie a zopakujte akciu – zobrazí sa detail chyby (alebo ho nájdete v Diagnostike).</p>')
        . '<p><a href="javascript:history.back()" style="color:#0D6FB0">← späť</a></p></div></body></html>';
}
