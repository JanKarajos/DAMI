<?php
require __DIR__ . '/app/bootstrap.php';

$services = therapies_all();
$byId = array_column($services, null, 'id');
$selected = (int) ($_POST['therapy_id'] ?? $_GET['terapia'] ?? 0);
if (!isset($byId[$selected])) {
    $selected = 0;
}

$errors = [];
$sent = isset($_GET['odoslane']);
if (is_post()) {
    csrf_check();
    $d = [
        'client_name'  => mb_substr(post('client_name'), 0, 120),
        'client_email' => mb_substr(post('client_email'), 0, 160),
        'client_phone' => mb_substr(post('client_phone'), 0, 40),
        'child_name'   => mb_substr(post('child_name'), 0, 120),
        'child_age'    => mb_substr(post('child_age'), 0, 40),
        'message'      => mb_substr(post('message'), 0, 3000),
    ];
    if (post('website') !== '') {                 // honeypot – tváriť sa ako úspech
        redirect('kontakt.php?odoslane=1');
    }
    if (mb_strlen($d['client_name']) < 3) $errors[] = 'Vyplňte meno a priezvisko.';
    if (!filter_var($d['client_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Zadajte platný e-mail.';
    if ($d['client_phone'] !== '' && !preg_match('/^[0-9 +()\/-]{6,}$/', $d['client_phone'])) $errors[] = 'Zadajte platné telefónne číslo.';
    if (mb_strlen(trim($d['message'])) < 5) $errors[] = 'Napíšte nám prosím krátku správu.';
    if (post('gdpr') === '') $errors[] = 'Je potrebný súhlas so spracovaním osobných údajov.';
    if (!$errors && inquiry_throttled()) $errors[] = 'Odoslali ste príliš veľa správ. Skúste to prosím neskôr alebo nám zavolajte.';
    if (!$errors) {
        create_inquiry($byId[$selected] ?? null, $d);
        redirect('kontakt.php?odoslane=1');
    }
}
$current = $byId[$selected] ?? null;
$groups = therapies_grouped($services);

page_header('Kontaktný formulár', 'public', 'kontakt.php');
?>
<section class="page-hero">
  <div class="container">
    <h1><?= $current ? e($current['name']) : 'Kontaktný formulár' ?></h1>
    <p class="muted mt-0"><?= $current && !therapy_is_online($current)
        ? 'Na túto službu sa neobjednáva cez online kalendár. Napíšte nám a ozveme sa vám s ďalším postupom.'
        : 'Máte otázku k terapiám, diagnostike alebo k objednaniu? Napíšte nám.' ?></p>
  </div>
</section>

<section class="section" style="padding-top:32px">
  <div class="container">
  <?php if ($sent): ?>
    <div class="card" style="max-width:720px">
      <h2>Ďakujeme, dopyt sme prijali.</h2>
      <p>Na váš e-mail sme poslali potvrdenie. Ozveme sa vám čo najskôr.</p>
      <p><a class="btn" href="<?= e(url('index.php')) ?>">Späť na úvod</a></p>
    </div>
  <?php else: ?>
    <div class="booking-layout">
      <form method="post" class="card" action="<?= e(url('kontakt.php')) ?>">
        <?= csrf_field() ?>
        <?php if ($errors): ?><div class="alert alert-error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
        <div class="form-row">
          <label for="therapy_id">Služba</label>
          <select id="therapy_id" name="therapy_id">
            <option value="0">Všeobecná otázka</option>
            <?php foreach ($groups as $kind => $list): if (!$list) continue; ?>
              <optgroup label="<?= e($kind === 'diagnostic' ? 'Diagnostika' : 'Terapie') ?>">
                <?php foreach ($list as $t): ?>
                  <option value="<?= (int) $t['id'] ?>" <?= $selected === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-grid">
          <div class="form-row"><label class="required" for="client_name">Meno a priezvisko</label>
            <input type="text" id="client_name" name="client_name" required maxlength="120" autocomplete="name" value="<?= e(post('client_name')) ?>"></div>
          <div class="form-row"><label class="required" for="client_email">E-mail</label>
            <input type="email" id="client_email" name="client_email" required maxlength="160" autocomplete="email" value="<?= e(post('client_email')) ?>"></div>
          <div class="form-row"><label for="client_phone">Telefón</label>
            <input type="tel" id="client_phone" name="client_phone" maxlength="40" autocomplete="tel" value="<?= e(post('client_phone')) ?>"></div>
          <div class="form-row"><label for="child_name">Meno dieťaťa</label>
            <input type="text" id="child_name" name="child_name" maxlength="120" value="<?= e(post('child_name')) ?>"></div>
          <div class="form-row"><label for="child_age">Vek dieťaťa</label>
            <input type="text" id="child_age" name="child_age" maxlength="40" placeholder="napr. 5 rokov" value="<?= e(post('child_age')) ?>"></div>
        </div>
        <div class="form-row"><label class="required" for="message">Správa</label>
          <textarea id="message" name="message" required maxlength="3000" rows="6" placeholder="S čím vám môžeme pomôcť?"><?= e(post('message')) ?></textarea></div>
        <div class="form-row" style="position:absolute;left:-9999px" aria-hidden="true">
          <label for="website">Nevypĺňajte</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>
        <div class="form-row">
          <label class="check"><input type="checkbox" name="gdpr" value="1" required <?= post('gdpr') ? 'checked' : '' ?>> Súhlasím so spracovaním osobných údajov za účelom vybavenia dopytu.</label>
        </div>
        <button class="btn btn-accent">Odoslať dopyt</button>
      </form>
      <div>
        <?php if ($current): ?>
          <div class="summary">
            <div><strong><?= e($current['name']) ?></strong></div>
            <?php if ($current['description']): ?><p class="small" style="margin:8px 0 0"><?= nl2br(e($current['description'])) ?></p><?php endif; ?>
          </div>
        <?php endif; ?>
        <div class="contact-card" style="margin-top:16px"><div class="label">E-mail</div><a href="mailto:<?= e(cfg('contact.email')) ?>"><?= e(cfg('contact.email')) ?></a></div>
        <?php if (cfg('contact.phone')): ?>
          <div class="contact-card" style="margin-top:16px"><div class="label">Telefón</div><a href="tel:<?= e(preg_replace('/\s+/', '', (string) cfg('contact.phone'))) ?>"><?= e(cfg('contact.phone')) ?></a></div>
        <?php endif; ?>
        <p class="small muted" style="margin-top:16px">Po odoslaní vám príde potvrdenie e-mailom.</p>
      </div>
    </div>
  <?php endif; ?>
  </div>
</section>
<?php page_footer();
