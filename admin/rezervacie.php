<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

if (is_post() && post('action') === 'cancel') {
    csrf_check();
    $b = booking_find((int) post('booking_id'));
    if ($b && $b['status'] === 'confirmed') {
        cancel_booking($b, 'admin');
        if (post('delete_slot') === '1' && $b['slot_id']) {
            slot_delete((int) $b['therapist_id'], (int) $b['slot_id']);
        }
        flash('success', 'Rezervácia bola zrušená, klient aj terapeut dostali e-mail.');
    }
    redirect('admin/rezervacie.php?' . http_build_query(array_intersect_key($_GET, ['terapeut' => 1, 'minule' => 1])));
}

$showPast = isset($_GET['minule']);
$filterT = (int) ($_GET['terapeut'] ?? 0);
$sql = 'SELECT b.*, t.title, t.title_after, t.first_name, t.last_name, t.email AS therapist_email, t.phone AS therapist_phone FROM bookings b
        JOIN therapists t ON t.id = b.therapist_id WHERE b.starts_at ' . ($showPast ? '<' : '>=') . ' ?';
$params = [date('Y-m-d H:i')];
if ($filterT) {
    $sql .= ' AND b.therapist_id = ?';
    $params[] = $filterT;
}
$sql .= ' ORDER BY b.starts_at ' . ($showPast ? 'DESC LIMIT 300' : 'ASC');
$s = db()->prepare($sql);
$s->execute($params);
$bookings = $s->fetchAll();
$showTherapist = true;

page_header('Rezervácie', 'admin', 'admin/rezervacie.php');
?>
<section class="page-hero"><div class="container"><h1><?= $showPast ? 'Minulé rezervácie' : 'Nadchádzajúce rezervácie' ?></h1></div></section>
<section class="section" style="padding-top:32px">
  <div class="container">
    <form method="get" class="toolbar">
      <div class="form-row"><label>Terapeut</label>
        <select name="terapeut" onchange="this.form.submit()">
          <option value="">Všetci</option>
          <?php foreach (therapists_all(false) as $t): ?>
            <option value="<?= (int) $t['id'] ?>" <?= $filterT === (int) $t['id'] ? 'selected' : '' ?>><?= e(therapist_full_name($t)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($showPast): ?><input type="hidden" name="minule" value="1"><?php endif; ?>
      <a class="btn btn-outline btn-sm" href="?<?= e(http_build_query(array_filter(['terapeut' => $filterT ?: null, 'minule' => $showPast ? null : 1]))) ?>"><?= $showPast ? 'Zobraziť nadchádzajúce' : 'Zobraziť minulé' ?></a>
    </form>
    <?php include dirname(__DIR__) . '/app/partials/bookings_table.php'; ?>
  </div>
</section>
<?php page_footer();
