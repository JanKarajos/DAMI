<?php
// Kontrola servera pred inštaláciou. Zámerne napísané tak, aby fungovalo aj na starom PHP 5.
header('Content-Type: text/html; charset=utf-8');
$ok = version_compare(PHP_VERSION, '8.1.0', '>=');
$ext = array('pdo_sqlite', 'mbstring', 'openssl', 'gd');
echo '<!doctype html><meta charset="utf-8"><title>Kontrola servera</title><body style="font-family:Arial,sans-serif;max-width:640px;margin:40px auto;padding:0 16px;color:#183247">';
echo '<h1>Kontrola servera pre rezerváciu</h1><ul>';
echo '<li>PHP verzia: <b>' . htmlspecialchars(PHP_VERSION) . '</b> – ' . ($ok ? '<span style="color:green">OK</span>' : '<span style="color:#c00">potrebná je 8.1 alebo novšia</span>') . '</li>';
foreach ($ext as $e) {
    echo '<li>Rozšírenie ' . $e . ': ' . (extension_loaded($e) ? '<span style="color:green">áno</span>' : '<span style="color:#c00">chýba</span>') . '</li>';
}
echo '<li>Server: ' . htmlspecialchars(isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '?') . '</li>';
echo '<li>Zápis do priečinka data: ' . (is_writable(dirname(__FILE__) . '/data') ? '<span style="color:green">áno</span>' : '<span style="color:#c00">nie</span>') . '</li>';
echo '</ul>';
echo $ok ? '<p>Server je v poriadku, pokračujte na <a href="admin/">administráciu</a>.</p>'
         : '<p>V administrácii hostingu prepnite PHP na verziu 8.1 alebo novšiu. Ak to hosting neumožňuje, rezervácia na ňom nepobeží.</p>';
echo '<p style="color:#5f7283;font-size:13px">Po úspešnej inštalácii môžete tento súbor zmazať.</p></body>';
