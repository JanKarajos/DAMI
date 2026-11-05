<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$admin = require_admin(true);   // dostupné aj pred zmenou hesla (napr. keď sa do databázy nedá zapisovať)

$testResult = null;
if (is_post() && post('action') === 'clear_error') {
    csrf_check();
    @unlink(APP_ROOT . '/data/last-error.txt');
    redirect('admin/diagnostika.php');
}
if (is_post() && post('action') === 'clear_limits') {
    csrf_check();
    db()->exec("DELETE FROM login_attempts");
    flash('success', 'Obmedzenia prihlásenia a kontaktného formulára boli vynulované.');
    redirect('admin/diagnostika.php');
}
if (is_post() && post('action') === 'test_mail') {
    csrf_check();
    $to = post('to');
    $GLOBALS['mail_last_error'] = null;
    $started = microtime(true);
    $ok = send_mail($to, 'Testovací e-mail – ' . cfg('site_short'), mail_layout('Testovací e-mail',
        '<p>Ak čítate tento e-mail, odosielanie z rezervačného systému funguje.</p><p>Odoslané: ' . e(date('d. m. Y H:i:s')) . '</p>'));
    $testResult = [$ok, $GLOBALS['mail_last_error'] ?? null, round(microtime(true) - $started, 1)];
}

$data = APP_ROOT . '/data';
$dbFile = (string) cfg('db_path');
$uploads = APP_ROOT . '/' . PHOTO_DIR;
$who = function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? (string) posix_geteuid()) : get_current_user();
$owner = function (string $path): string {
    if (!file_exists($path)) return 'neexistuje';
    $uid = fileowner($path);
    return function_exists('posix_getpwuid') ? (posix_getpwuid($uid)['name'] ?? (string) $uid) : (string) $uid;
};
$dbWritable = true;
try {
    db()->exec("INSERT INTO settings (key, value) VALUES ('diag_write', datetime('now')) ON CONFLICT(key) DO UPDATE SET value = excluded.value");
} catch (Throwable $ex) {
    $dbWritable = false;
}
$host = ($_SERVER['HTTP_HOST'] ?? '');
$baseHost = parse_url((string) cfg('base_url'), PHP_URL_HOST) . (parse_url((string) cfg('base_url'), PHP_URL_PORT) ? ':' . parse_url((string) cfg('base_url'), PHP_URL_PORT) : '');

$checks = [
    ['PHP verzia', PHP_VERSION, version_compare(PHP_VERSION, '8.1', '>=')],
    ['Webový server beží ako', $who, true],
];
foreach (['pdo_sqlite', 'mbstring', 'openssl', 'gd', 'exif'] as $ext) {
    $checks[] = ['Rozšírenie ' . $ext, extension_loaded($ext) ? 'áno' : 'chýba', extension_loaded($ext) || $ext === 'exif'];
}
$checks[] = ['Zápis do priečinka data/', (is_writable($data) ? 'áno' : 'NIE') . ' (vlastník: ' . $owner($data) . ')', is_writable($data)];
$checks[] = ['Zápis do databázy', ($dbWritable ? 'áno' : 'NIE') . ' (vlastník: ' . $owner($dbFile) . ')', $dbWritable];
foreach (['-wal', '-shm'] as $sfx) {
    if (file_exists($dbFile . $sfx)) {
        $checks[] = ['Súbor dami.sqlite' . $sfx, (is_writable($dbFile . $sfx) ? 'zapisovateľný' : 'NIE je zapisovateľný') . ' (vlastník: ' . $owner($dbFile . $sfx) . ')', is_writable($dbFile . $sfx)];
    }
}
$checks[] = ['Zápis do uploads/therapists/', (is_writable($uploads) ? 'áno' : 'NIE') . ' (vlastník: ' . $owner($uploads) . ')', is_writable($uploads)];
$checks[] = ['base_url', (string) cfg('base_url') . ($baseHost !== $host ? ' – web otvárate cez ' . $host : ''), $baseHost === $host];
$checks[] = ['HTTPS', request_is_https() ? 'áno' : 'nie', request_is_https()];
$ip = client_ip();
$remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$viaProxy = !empty($_SERVER['HTTP_X_FORWARDED_FOR']);
$proxyUntrusted = $viaProxy && !ip_is_trusted_proxy($remote);   // proxy posiela X-Forwarded-For, ale nedôverujeme mu
$checks[] = ['Vaša IP podľa aplikácie', $ip . ' (spojenie od ' . $remote . ($viaProxy ? ', X-Forwarded-For: ' . $_SERVER['HTTP_X_FORWARDED_FOR'] : '') . ')'
    . ($proxyUntrusted ? ' – všetci návštevníci majú adresu proxy, preto sa obmedzenia sčítajú za všetkých: pridajte ' . $remote . ' do trusted_proxies v config.local.php' : ''),
    !$proxyUntrusted];
$s = db()->prepare("SELECT area, COUNT(*) AS n FROM login_attempts WHERE created_at > ? GROUP BY area");
$s->execute([time() - 3600]);
$limits = $s->fetchAll(PDO::FETCH_KEY_PAIR);
$checks[] = ['Záznamy obmedzení za poslednú hodinu', $limits ? implode(', ', array_map(fn($k, $v) => "$k: $v", array_keys($limits), $limits)) : 'žiadne', true];
$localFile = APP_ROOT . '/config.local.php';
$localKeys = [];
$localOk = false;
if (is_file($localFile)) {
    $local = @include $localFile;
    $localOk = is_array($local);
    $flat = function (array $a, string $prefix = '') use (&$flat, &$localKeys): void {
        foreach ($a as $k => $v) {
            is_array($v) && $v && !array_is_list($v) ? $flat($v, $prefix . $k . '.') : $localKeys[] = $prefix . $k;
        }
    };
    if ($localOk) {
        $flat($local);
    }
}
$checks[] = ['config.local.php', !is_file($localFile) ? 'neexistuje – použijú sa predvolené nastavenia (e-maily sa neodosielajú)'
    : ($localOk ? 'načítaný, nastavuje: ' . implode(', ', $localKeys) : 'existuje, ale nevracia pole – skontrolujte, že začína <?php a obsahuje return [ ... ];'),
    $localOk && in_array('mail.transport', $localKeys, true)];
$transport = (string) cfg('mail.transport');
$checks[] = ['Odosielanie e-mailov', match ($transport) {
    'smtp' => 'SMTP ' . cfg('mail.smtp.host') . ':' . cfg('mail.smtp.port') . ' (' . (cfg('mail.smtp.encryption') ?: 'bez šifrovania') . '), používateľ ' . (cfg('mail.smtp.username') ?: '—'),
    'mail' => 'PHP mail()',
    default => 'iba zápis do data/mail.log (testovací režim)',
}, $transport === 'smtp' || $transport === 'mail'];
$user = (string) cfg('mail.smtp.username');
$fromOk = !($transport === 'smtp' && str_contains($user, '@') && strcasecmp($user, (string) cfg('mail.from_email')) !== 0);
$checks[] = ['Odosielateľ', cfg('mail.from_name') . ' <' . cfg('mail.from_email') . '>'
    . ($fromOk ? '' : ' – líši sa od SMTP účtu ' . $user . ' (Gmail a väčšina serverov vyžaduje rovnakú adresu, nastavte mail.from_email)'), $fromOk];
$lastCron = (int) setting_get('pseudo_cron_last', '0');
$checks[] = ['Pripomienky (pseudo-cron)', cfg('pseudo_cron') ? ('zapnutý, naposledy ' . ($lastCron ? date('d. m. Y H:i', $lastCron) : 'nikdy')) : 'vypnutý (očakáva sa Plánovač úloh)', true];

page_header('Diagnostika', 'admin', 'admin/diagnostika.php');
?>
<section class="page-hero"><div class="container">
  <h1>Diagnostika servera</h1>
  <p>Kontrola nastavení na serveri (NAS) a skúška odosielania e-mailov.</p>
</div></section>
<section class="section" style="padding-top:32px">
<div class="container dash-grid" style="grid-template-columns:minmax(0,1.5fr) minmax(0,1fr)">
  <div class="card">
    <h3>Stav</h3>
    <div class="table-wrap"><table class="table">
      <?php foreach ($checks as [$label, $value, $ok]): ?>
        <tr><th><?= e($label) ?></th><td><?= e((string) $value) ?></td>
          <td><span class="status <?= $ok ? 'status-free' : 'status-booked' ?>"><?= $ok ? 'OK' : 'Pozor' ?></span></td></tr>
      <?php endforeach; ?>
    </table></div>
    <?php $hist = @file($data . '/mail-history.log'); if ($hist): ?>
      <h3 style="margin-top:24px">Posledné e-maily</h3>
      <pre style="white-space:pre-wrap;word-break:break-word;font-size:12px;background:#f3f8fc;border-radius:14px;padding:14px;max-height:320px;overflow:auto"><?= e(implode('', array_reverse(array_slice($hist, -20)))) ?></pre>
    <?php endif; ?>
    <?php $lastErr = @file_get_contents($data . '/last-error.txt'); if ($lastErr): ?>
      <h3 style="margin-top:24px">Posledná chyba servera</h3>
      <form method="post" style="margin-bottom:8px"><?= csrf_field() ?><input type="hidden" name="action" value="clear_error"><button class="btn btn-outline btn-sm">Vymazať záznam</button></form>
      <pre style="white-space:pre-wrap;word-break:break-word;font-size:12px;background:#f3f8fc;border-radius:14px;padding:14px;max-height:320px;overflow:auto"><?= e($lastErr) ?></pre>
    <?php endif; ?>
    <?php if (!$dbWritable || !is_writable($data)): ?>
      <div class="alert alert-error" style="margin-top:16px">Do databázy sa nedá zapisovať – rezervácie a dopyty skončia chybou 500.
        V Dockeri reštartujte projekt v Container Manager (práva sa pri štarte opravia). Vo Web Station pridajte používateľovi <b>http</b> právo Čítanie/Zápis na priečinky <code>data</code> a <code>uploads</code>.</div>
    <?php endif; ?>
  </div>
  <div>
    <div class="card">
      <h3>Testovací e-mail</h3>
      <?php if ($testResult): [$ok, $err, $sec] = $testResult; ?>
        <div class="alert <?= $ok ? 'alert-success' : 'alert-error' ?>">
          <?= $ok ? ($transport === 'smtp' || $transport === 'mail' ? 'E-mail bol odoslaný (' . $sec . ' s). Skontrolujte schránku aj spam.' : 'E-mail bol iba zapísaný do data/mail.log – odosielanie nie je zapnuté (mail.transport).')
                  : 'Odoslanie zlyhalo (' . $sec . ' s): ' . e((string) $err) ?>
        </div>
      <?php endif; ?>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="test_mail">
        <div class="form-row"><label>Poslať na</label><input type="email" name="to" required value="<?= e(post('to', (string) cfg('contact.email'))) ?>"></div>
        <button class="btn">Odoslať testovací e-mail</button>
      </form>
    </div>
    <div class="card" style="margin-top:20px">
      <h3>Obmedzenia proti spamu</h3>
      <p class="small muted">Kontaktný formulár prijme z jednej IP adresy najviac <?= (int) cfg('inquiry_limit_per_hour', 10) ?> dopytov za hodinu, prihlásenie 8 chybných pokusov za 15 minút. Keď ste prihlásený ako admin, formulár obmedzený nie je.</p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="clear_limits">
        <button class="btn btn-outline">Vynulovať obmedzenia</button></form>
    </div>
  </div>
</div>
</section>
<?php page_footer();
