<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

$therapists = therapists_all(false);
$therapies = therapies_all(false);

// Uloženie matice priradení terapeut × terapia
if (is_post() && post('action') === 'matrix') {
    csrf_check();
    $validT = array_column($therapists, 'id');
    $validTh = array_column($therapies, 'id');
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM therapist_therapy');
    $ins = $pdo->prepare('INSERT INTO therapist_therapy (therapist_id, therapy_id) VALUES (?, ?)');
    foreach ((array) ($_POST['m'] ?? []) as $tid => $ths) {
        if (!in_array((int) $tid, array_map('intval', $validT), true)) continue;
        foreach ((array) $ths as $thid => $_) {
            if (in_array((int) $thid, array_map('intval', $validTh), true)) {
                $ins->execute([(int) $tid, (int) $thid]);
            }
        }
    }
    $pdo->commit();
    flash('success', 'Priradenie terapií bolo uložené.');
    redirect('admin/index.php');
}

$now = date('Y-m-d H:i');
$q = fn(string $sql, array $p = []) => (function () use ($sql, $p) { $s = db()->prepare($sql); $s->execute($p); return $s->fetchColumn(); })();
$stats = [
    'upcoming' => $q("SELECT COUNT(*) FROM bookings WHERE status='confirmed' AND starts_at >= ?", [$now]),
    'free'     => $q("SELECT COUNT(*) FROM slots s WHERE s.starts_at >= ? AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.slot_id=s.id AND b.status='confirmed')", [$now]),
    'week'     => $q("SELECT COUNT(*) FROM bookings WHERE created_at >= ?", [date('Y-m-d H:i:s', strtotime('-7 days'))]),
];
$assigned = [];
foreach (db()->query('SELECT therapist_id, therapy_id FROM therapist_therapy') as $r) {
    $assigned[$r['therapist_id']][$r['therapy_id']] = true;
}
$s = db()->prepare("SELECT b.*, t.title, t.title_after, t.first_name, t.last_name FROM bookings b JOIN therapists t ON t.id=b.therapist_id
                    WHERE b.status='confirmed' AND b.starts_at >= ? ORDER BY b.starts_at LIMIT 8");
$s->execute([$now]);
$next = $s->fetchAll();

page_header('Prehľad', 'admin', 'admin/index.php');
?>
<section class="page-hero"><div class="container"><h1>Prehľad</h1></div></section>
<section class="section" style="padding-top:32px">
<div class="container">
  <div class="stats">
    <div class="stat"><b><?= (int) $stats['upcoming'] ?></b>nadchádzajúcich rezervácií</div>
    <div class="stat"><b><?= (int) $stats['free'] ?></b>voľných termínov</div>
    <div class="stat"><b><?= (int) $stats['week'] ?></b>nových rezervácií za 7 dní</div>
  </div>

  <div class="card">
    <h3>Kto vykonáva ktorú terapiu</h3>
    <?php if (!$therapists || !$therapies): ?>
      <p class="muted">Najprv vytvorte <a href="<?= e(url('admin/terapeuti.php?novy=1')) ?>">terapeutov</a> a <a href="<?= e(url('admin/terapie.php?nova=1')) ?>">terapie</a>.</p>
    <?php else: ?>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="matrix">
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Terapeut</th><?php foreach ($therapies as $th): ?><th class="center"><?= e($th['name']) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
          <?php foreach ($therapists as $t): ?>
            <tr>
              <td><a href="<?= e(url('admin/terapeuti.php?upravit=' . $t['id'])) ?>"><?= e(therapist_full_name($t)) ?></a><?= $t['active'] ? '' : ' <span class="small muted">(neaktívny)</span>' ?></td>
              <?php foreach ($therapies as $th): ?>
                <td class="center"><input type="checkbox" name="m[<?= (int) $t['id'] ?>][<?= (int) $th['id'] ?>]" value="1" <?= isset($assigned[$t['id']][$th['id']]) ? 'checked' : '' ?> aria-label="<?= e(therapist_full_name($t) . ' – ' . $th['name']) ?>"></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p style="margin-top:14px"><button class="btn">Uložiť priradenie</button></p>
    </form>
    <?php endif; ?>
  </div>

  <h2 style="margin-top:40px">Najbližšie rezervácie</h2>
  <?php if (!$next): ?><div class="card muted center">Žiadne nadchádzajúce rezervácie.</div><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Termín</th><th>Terapia</th><th>Terapeut</th><th>Klient</th></tr></thead>
    <?php foreach ($next as $b): ?>
      <tr><td><?= e(fmt_datetime($b['starts_at'])) ?></td><td><?= e($b['therapy_name']) ?></td><td><?= e(therapist_full_name($b)) ?></td><td><?= e($b['client_name']) ?></td></tr>
    <?php endforeach; ?>
  </table></div>
  <p><a href="<?= e(url('admin/rezervacie.php')) ?>">Všetky rezervácie →</a></p>
  <?php endif; ?>
</div>
</section>
<?php page_footer();
