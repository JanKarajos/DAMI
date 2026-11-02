<?php
require __DIR__ . '/app/bootstrap.php';

$token = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$b = preg_match('/^[a-f0-9]{40}$/', $token) ? booking_find_by_token($token) : null;
if (!$b) {
    http_response_code(404);
    page_header('Rezervácia nenájdená');
    echo '<div class="container section"><div class="alert alert-error">Rezervácia neexistuje alebo odkaz nie je platný.</div>'
        . '<a class="btn" href="' . e(url('rezervacia.php')) . '">Nová rezervácia</a></div>';
    page_footer();
    exit;
}

if (is_post() && post('action') === 'cancel') {
    csrf_check();
    if (can_client_cancel($b)) {
        cancel_booking($b, 'client');
        flash('success', 'Rezervácia bola zrušená. Potvrdenie sme vám poslali e-mailom.');
    } else {
        flash('error', 'Túto rezerváciu už nie je možné zrušiť online. Kontaktujte prosím terapeuta.');
    }
    redirect('rezervacia-detail.php?t=' . $token);
}

$isNew = isset($_GET['nova']) && $b['status'] === 'confirmed';
$past = strtotime($b['starts_at']) < time();
page_header('Detail rezervácie');
?>
<section class="page-hero">
  <div class="container">
    <h1><?= $isNew ? '🎉 Ďakujeme, termín je rezervovaný!' : 'Detail rezervácie' ?></h1>
    <?php if ($isNew): ?><p class="mt-0 muted">Potvrdenie sme odoslali na <strong><?= e($b['client_email']) ?></strong>. Pred termínom vám pošleme pripomienku.</p><?php endif; ?>
  </div>
</section>
<section class="section" style="padding-top:32px">
  <div class="container" style="max-width:760px">
    <div class="card">
      <p>Stav:
        <?php if ($b['status'] === 'cancelled'): ?><span class="status status-cancelled">Zrušená</span>
        <?php elseif ($past): ?><span class="status status-past">Prebehla</span>
        <?php else: ?><span class="status status-free">Potvrdená</span><?php endif; ?>
      </p>
      <?= booking_details_html($b) ?>
      <?php if ($b['status'] === 'confirmed' && !$past): ?>
        <p>
          <a class="btn" href="<?= e(google_calendar_link($b)) ?>" target="_blank" rel="noopener">Pridať do Google kalendára</a>
        </p>
        <?php if (can_client_cancel($b)): ?>
          <form method="post" onsubmit="return confirm('Naozaj chcete zrušiť túto rezerváciu?')">
            <?= csrf_field() ?>
            <input type="hidden" name="t" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="cancel">
            <button class="btn btn-danger btn-sm">Zrušiť rezerváciu</button>
          </form>
        <?php else: ?>
          <p class="small muted">Rezerváciu je možné zrušiť online najneskôr <?= (int) cfg('min_cancel_notice_hours') ?> hodín pred termínom. Teraz kontaktujte prosím priamo terapeuta: <?= e($b['therapist_email']) ?><?= $b['therapist_phone'] ? ', ' . e($b['therapist_phone']) : '' ?>.</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <p><a href="<?= e(url('rezervacia.php')) ?>">+ Rezervovať ďalší termín</a></p>
  </div>
</section>
<?php page_footer();
