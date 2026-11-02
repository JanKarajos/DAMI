<?php
/** @var array $bookings  @var bool $showTherapist (voliteľné) */
$showTherapist = $showTherapist ?? false;
if (!$bookings): ?>
  <div class="card center muted">Žiadne rezervácie.</div>
<?php else: ?>
<div class="table-wrap">
  <table class="table">
    <thead><tr>
      <th>Termín</th><th>Terapia</th><?php if ($showTherapist): ?><th>Terapeut</th><?php endif; ?>
      <th>Klient</th><th>Kontakt</th><th>Poznámka</th><th>Stav</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($bookings as $b): $future = $b['starts_at'] >= date('Y-m-d H:i'); ?>
      <tr>
        <td><strong><?= e(fmt_date($b['starts_at'])) ?></strong><br><?= fmt_time($b['starts_at']) ?>–<?= fmt_time($b['ends_at']) ?></td>
        <td><?= e($b['therapy_name']) ?></td>
        <?php if ($showTherapist): ?><td><?= e(therapist_full_name($b)) ?></td><?php endif; ?>
        <td><?= e($b['client_name']) ?><?php if ($b['child_name']): ?><div class="small muted">dieťa: <?= e($b['child_name']) ?></div><?php endif; ?></td>
        <td class="small"><a href="mailto:<?= e($b['client_email']) ?>"><?= e($b['client_email']) ?></a><br><a href="tel:<?= e($b['client_phone']) ?>"><?= e($b['client_phone']) ?></a></td>
        <td class="small"><?= nl2br(e($b['note'])) ?></td>
        <td>
          <?php if ($b['status'] === 'cancelled'): ?>
            <span class="status status-cancelled">Zrušená</span>
          <?php elseif (!$future): ?>
            <span class="status status-past">Prebehla</span>
          <?php else: ?>
            <span class="status status-free">Potvrdená</span>
            <?php if ($b['reminder_sent_at']): ?><div class="small muted">pripomienka odoslaná</div><?php endif; ?>
          <?php endif; ?>
        </td>
        <td class="actions">
          <?php if ($b['status'] === 'confirmed' && $future): ?>
            <form method="post" onsubmit="return confirm('Zrušiť rezerváciu? Klient dostane e-mail o zrušení.')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="cancel">
              <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
              <label class="check small" style="justify-content:flex-end"><input type="checkbox" name="delete_slot" value="1"> aj zmazať termín</label>
              <button class="btn btn-danger btn-sm">Zrušiť</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif;
