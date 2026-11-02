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
require __DIR__ . '/photos.php';
require __DIR__ . '/layout.php';

if (PHP_SAPI !== 'cli') {
    session_name('dami_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
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
