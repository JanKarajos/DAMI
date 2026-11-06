<?php
// Odoslanie pripomienok blížiacich sa termínov.
// Spúšťajte cez cron napr. každých 15 minút:
//   */15 * * * * php /cesta/k/aplikacii/cron/pripomienky.php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
// Ak beží ako root (napr. Plánovač úloh na Synology), prepneme sa na vlastníka priečinka data/,
// aby databáza nezmenila vlastníka a webový server do nej mohol ďalej zapisovať.
if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
    $owner = @fileowner(dirname(__DIR__) . '/data');
    $group = @filegroup(dirname(__DIR__) . '/data');
    if ($owner) {
        posix_setgid((int) $group);
        posix_setuid((int) $owner);
    }
}
require dirname(__DIR__) . '/app/bootstrap.php';
$n = send_due_reminders();
echo date('Y-m-d H:i:s') . " – odoslaných pripomienok: $n\n";
