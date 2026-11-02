<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_therapist();
$myId = (int) $me['id'];

$myTherapies = therapies_by_therapist()[$myId] ?? [];
$defaultDuration = $myTherapies ? (int) $myTherapies[0]['duration_min'] : 60;
$myTherapyIds = array_map(fn($t) => (int) $t['id'], $myTherapies);

$validDate = fn(string $d) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
$validTime = fn(string $t) => (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t);

if (is_post()) {
    csrf_check();
    $action = post('action');
    $therapyId = (int) post('therapy_id') ?: null;
    if ($therapyId && !in_array($therapyId, $myTherapyIds, true)) {
        $therapyId = null;
    }

    if ($action === 'add_single') {
        $date = post('date');
        $time = post('time');
        $dur = max(10, min(480, (int) post('duration')));
        if (!$validDate($date) || !$validTime($time)) {
            flash('error', 'Zadajte platný dátum a čas.');
        } else {
            $start = "$date $time";
            $end = date('Y-m-d H:i', strtotime($start) + $dur * 60);
            $err = slot_add($myId, $start, $end, $therapyId, mb_substr(post('note'), 0, 200));
            $err ? flash('error', $err) : flash('success', 'Termín ' . fmt_datetime($start) . ' bol pridaný.');
        }
        redirect('terapeut/index.php');
    }

    if ($action === 'add_bulk') {
        $from = post('date_from');
        $to = post('date_to');
        $tFrom = post('time_from');
        $tTo = post('time_to');
        $dur = max(10, min(480, (int) post('duration')));
        $gap = max(0, min(240, (int) post('gap')));
        $weekdays = array_map('intval', (array) ($_POST['weekdays'] ?? []));
        if (!$validDate($from) || !$validDate($to) || !$validTime($tFrom) || !$validTime($tTo) || $to < $from || $tTo <= $tFrom || !$weekdays) {
            flash('error', 'Skontrolujte obdobie, časový rozsah a vyberte aspoň jeden deň v týždni.');
            redirect('terapeut/index.php');
        }
        if ((strtotime($to) - strtotime($from)) / 86400 > 366) {
            flash('error', 'Obdobie môže mať najviac jeden rok.');
            redirect('terapeut/index.php');
        }
        $added = 0;
        $skipped = 0;
        for ($d = strtotime($from); $d <= strtotime($to); $d = strtotime('+1 day', $d)) {
            if (!in_array((int) date('N', $d), $weekdays, true)) {
                continue;
            }
            $day = date('Y-m-d', $d);
            $cur = strtotime("$day $tFrom");
            $limit = strtotime("$day $tTo");
            while ($cur + $dur * 60 <= $limit) {
                $start = date('Y-m-d H:i', $cur);
                $end = date('Y-m-d H:i', $cur + $dur * 60);
                slot_add($myId, $start, $end, $therapyId) === null ? $added++ : $skipped++;
                $cur += ($dur + $gap) * 60;
            }
        }
        flash($added ? 'success' : 'info', "Pridaných termínov: $added." . ($skipped ? " Preskočených (minulosť / prekryv): $skipped." : ''));
        redirect('terapeut/index.php');
    }

    if ($action === 'delete') {
        // Jednotlivé tlačidlo "Zmazať" má prednosť pred zaškrtnutými položkami.
        $ids = post('slot_id') !== ''
            ? [(int) post('slot_id')]
            : array_map('intval', (array) ($_POST['slot_ids'] ?? []));
        $n = 0;
        foreach (array_unique($ids) as $id) {
            $n += slot_delete($myId, $id) ? 1 : 0;
        }
        flash($n ? 'success' : 'error', $n ? "Zmazaných termínov: $n." : 'Nič nebolo zmazané (rezervované termíny nie je možné zmazať – najprv zrušte rezerváciu).');
        redirect('terapeut/index.php' . (isset($_GET['od']) ? '?od=' . urlencode((string) $_GET['od']) : ''));
    }
}

// Zobrazenie: 4 týždne od zvoleného dňa
$fromDate = (string) ($_GET['od'] ?? date('Y-m-d'));
if (!$validDate($fromDate)) {
    $fromDate = date('Y-m-d');
}
$toDate = date('Y-m-d', strtotime($fromDate . ' +28 days'));
$slots = therapist_slots($myId, $fromDate, $toDate);
$grouped = [];
foreach ($slots as $s) {
    $grouped[substr($s['starts_at'], 0, 10)][] = $s;
}
$stats = db()->prepare("SELECT
    (SELECT COUNT(*) FROM slots s WHERE s.therapist_id = :id AND s.starts_at >= :now
        AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.slot_id = s.id AND b.status='confirmed')) AS free,
    (SELECT COUNT(*) FROM bookings WHERE therapist_id = :id AND status='confirmed' AND starts_at >= :now) AS booked,
    (SELECT COUNT(*) FROM bookings WHERE therapist_id = :id AND status='confirmed' AND starts_at >= :now AND starts_at < :week) AS week");
$stats->execute([':id' => $myId, ':now' => date('Y-m-d H:i'), ':week' => date('Y-m-d', strtotime('+7 days'))]);
$st = $stats->fetch();

page_header('Moje termíny', 'therapist', 'terapeut/index.php');
?>
<section class="page-hero">
  <div class="container">
    <h1>Dobrý deň, <?= e(therapist_full_name($me)) ?></h1>
    <p class="muted mt-0">Tu si vypisujete voľné termíny, na ktoré sa klienti môžu online objednať.</p>
  </div>
</section>
<section class="section" style="padding-top:32px">
  <div class="container">
    <div class="stats">
      <div class="stat"><b><?= (int) $st['free'] ?></b>voľných termínov</div>
      <div class="stat"><b><?= (int) $st['booked'] ?></b>nadchádzajúcich rezervácií</div>
      <div class="stat"><b><?= (int) $st['week'] ?></b>rezervácií v najbližších 7 dňoch</div>
    </div>

    <?php if (!$myTherapies): ?>
      <div class="alert alert-info">Zatiaľ vám nebola priradená žiadna terapia. Klienti sa k vám nebudú môcť objednať, kým vám administrátor terapiu nepriradí.</div>
    <?php endif; ?>

    <div class="dash-grid">
      <div>
        <div class="card">
          <h3>Pridať jeden termín</h3>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="add_single">
            <div class="form-grid">
              <div class="form-row"><label>Dátum</label><input type="date" name="date" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>"></div>
              <div class="form-row"><label>Začiatok</label><input type="time" name="time" required value="09:00" step="300"></div>
            </div>
            <div class="form-grid">
              <div class="form-row"><label>Dĺžka (min)</label><input type="number" name="duration" min="10" max="480" value="<?= $defaultDuration ?>" required></div>
              <div class="form-row"><label>Terapia</label><?= therapy_select($myTherapies) ?></div>
            </div>
            <div class="form-row"><label>Poznámka (interná)</label><input type="text" name="note" maxlength="200"></div>
            <button class="btn">Pridať termín</button>
          </form>
        </div>

        <details class="card" style="margin-top:20px" open>
          <summary>Hromadne vypísať termíny</summary>
          <form method="post" style="margin-top:14px">
            <?= csrf_field() ?><input type="hidden" name="action" value="add_bulk">
            <div class="form-grid">
              <div class="form-row"><label>Od dátumu</label><input type="date" name="date_from" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>"></div>
              <div class="form-row"><label>Do dátumu</label><input type="date" name="date_to" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 month')) ?>"></div>
              <div class="form-row"><label>Čas od</label><input type="time" name="time_from" required value="08:00" step="300"></div>
              <div class="form-row"><label>Čas do</label><input type="time" name="time_to" required value="14:00" step="300"></div>
              <div class="form-row"><label>Dĺžka termínu (min)</label><input type="number" name="duration" min="10" max="480" value="<?= $defaultDuration ?>" required></div>
              <div class="form-row"><label>Prestávka medzi (min)</label><input type="number" name="gap" min="0" max="240" value="0"></div>
            </div>
            <div class="form-row"><label>Dni v týždni</label>
              <div class="checks" style="grid-template-columns:repeat(4,1fr)">
                <?php foreach ([1 => 'Po', 2 => 'Ut', 3 => 'St', 4 => 'Št', 5 => 'Pi', 6 => 'So', 7 => 'Ne'] as $n => $lbl): ?>
                  <label class="check"><input type="checkbox" name="weekdays[]" value="<?= $n ?>" <?= $n <= 5 ? 'checked' : '' ?>> <?= $lbl ?></label>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="form-row"><label>Terapia</label><?= therapy_select($myTherapies) ?></div>
            <p class="help">Termíny, ktoré by sa prekrývali s existujúcimi, sa automaticky preskočia.</p>
            <button class="btn">Vygenerovať termíny</button>
          </form>
        </details>
      </div>

      <div>
        <form method="get" class="toolbar">
          <div class="form-row"><label>Zobraziť 4 týždne od</label><input type="date" name="od" value="<?= e($fromDate) ?>" onchange="this.form.submit()"></div>
          <a class="btn btn-outline btn-sm" href="?od=<?= date('Y-m-d', strtotime($fromDate . ' -28 days')) ?>">‹ Skôr</a>
          <a class="btn btn-outline btn-sm" href="?od=<?= date('Y-m-d') ?>">Dnes</a>
          <a class="btn btn-outline btn-sm" href="?od=<?= e($toDate) ?>">Neskôr ›</a>
        </form>

        <?php if (!$grouped): ?>
          <div class="card center muted">V tomto období nemáte vypísané žiadne termíny.</div>
        <?php else: ?>
          <form method="post" id="slots-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete">
            <?php foreach ($grouped as $day => $items): ?>
              <div class="day-group">
                <h4><?= e(fmt_date($day)) ?></h4>
                <div class="table-wrap">
                  <table class="table">
                    <?php foreach ($items as $s): $past = $s['starts_at'] < date('Y-m-d H:i'); ?>
                      <tr>
                        <td style="width:30px"><?php if (!$s['booking_id'] && !$past): ?><input type="checkbox" name="slot_ids[]" value="<?= (int) $s['id'] ?>" aria-label="Označiť"><?php endif; ?></td>
                        <td style="width:110px"><strong><?= fmt_time($s['starts_at']) ?>–<?= fmt_time($s['ends_at']) ?></strong></td>
                        <td>
                          <?php if ($s['booking_id']): ?>
                            <span class="status status-booked">Rezervované</span>
                            <?= e($s['therapy_name']) ?> – <strong><?= e($s['client_name']) ?></strong><?= $s['child_name'] ? ' (' . e($s['child_name']) . ')' : '' ?>
                            <div class="small muted"><?= e($s['client_phone']) ?> · <?= e($s['client_email']) ?></div>
                          <?php elseif ($past): ?>
                            <span class="status status-past">Uplynulo</span>
                          <?php else: ?>
                            <span class="status status-free">Voľné</span>
                            <span class="small muted"><?= $s['therapy_only'] ? 'len ' . e($s['therapy_only']) : 'všetky moje terapie' ?></span>
                          <?php endif; ?>
                          <?php if ($s['note']): ?><div class="small muted">📝 <?= e($s['note']) ?></div><?php endif; ?>
                        </td>
                        <td class="actions">
                          <?php if ($s['booking_id']): ?>
                            <a class="btn btn-outline btn-sm" href="<?= e(url('terapeut/rezervacie.php')) ?>">Detail</a>
                          <?php elseif (!$past): ?>
                            <button class="btn btn-outline btn-sm" name="slot_id" value="<?= (int) $s['id'] ?>" onclick="return confirm('Zmazať termín?')">Zmazať</button>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </table>
                </div>
              </div>
            <?php endforeach; ?>
            <p style="margin-top:16px"><button class="btn btn-danger btn-sm" onclick="return confirm('Zmazať všetky označené voľné termíny?')">Zmazať označené</button></p>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php page_footer();

function therapy_select(array $myTherapies): string
{
    $h = '<select name="therapy_id"><option value="">Ktorákoľvek moja terapia</option>';
    foreach ($myTherapies as $t) {
        $h .= '<option value="' . (int) $t['id'] . '">Len: ' . e($t['name']) . '</option>';
    }
    return $h . '</select>';
}
