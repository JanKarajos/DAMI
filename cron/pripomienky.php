<?php
// Odoslanie pripomienok blížiacich sa termínov.
// Spúšťajte cez cron napr. každých 15 minút:
//   */15 * * * * php /cesta/k/aplikacii/cron/pripomienky.php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
$n = send_due_reminders();
echo date('Y-m-d H:i:s') . " – odoslaných pripomienok: $n\n";
