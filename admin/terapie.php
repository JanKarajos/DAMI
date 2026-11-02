<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

$therapists = therapists_all(false);
$therapistIds = array_map(fn($t) => (int) $t['id'], $therapists);

if (is_post()) {
    csrf_check();
    $action = post('action');
    $id = (int) post('id');

    if ($action === 'save') {
        $d = [
            'name'         => mb_substr(post('name'), 0, 160),
            'description'  => mb_substr(post('description'), 0, 3000),
            'duration_min' => max(10, min(480, (int) post('duration_min'))),
            'price'        => mb_substr(post('price'), 0, 60),
            'active'       => post('active') === '1' ? 1 : 0,
            'sort_order'   => (int) post('sort_order'),
        ];
        if ($d['name'] === '') {
            $_SESSION['form_old'] = $_POST;
            flash('error', 'Názov terapie je povinný.');
            redirect('admin/terapie.php?' . ($id ? 'upravit=' . $id : 'nova=1'));
        }
        $selected = array_values(array_intersect(array_map('intval', (array) ($_POST['therapists'] ?? [])), $therapistIds));
        $pdo = db();
        $pdo->beginTransaction();
        if ($id) {
            $cols = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($d)));
            $pdo->prepare("UPDATE therapies SET $cols WHERE id = :id")->execute($d + ['id' => $id]);
        } else {
            $cols = array_keys($d);
            $pdo->prepare('INSERT INTO therapies (' . implode(',', $cols) . ') VALUES (:' . implode(',:', $cols) . ')')->execute($d);
            $id = (int) $pdo->lastInsertId();
        }
        $pdo->prepare('DELETE FROM therapist_therapy WHERE therapy_id = ?')->execute([$id]);
        $ins = $pdo->prepare('INSERT INTO therapist_therapy (therapist_id, therapy_id) VALUES (?, ?)');
        foreach ($selected as $tid) {
            $ins->execute([$tid, $id]);
        }
        $pdo->commit();
        flash('success', 'Terapia bola uložená.');
        redirect('admin/terapie.php');
    }

    if ($action === 'toggle' && $id) {
        db()->prepare('UPDATE therapies SET active = 1 - active WHERE id = ?')->execute([$id]);
        flash('success', 'Stav terapie bol zmenený.');
        redirect('admin/terapie.php');
    }

    if ($action === 'delete' && $id) {
        $s = db()->prepare("SELECT COUNT(*) FROM bookings WHERE therapy_id = ? AND status = 'confirmed' AND starts_at >= ?");
        $s->execute([$id, date('Y-m-d H:i')]);
        if ((int) $s->fetchColumn() > 0) {
            flash('error', 'Na túto terapiu existujú nadchádzajúce rezervácie. Terapiu môžete deaktivovať.');
        } else {
            db()->prepare('DELETE FROM therapies WHERE id = ?')->execute([$id]);
            flash('success', 'Terapia bola zmazaná.');
        }
        redirect('admin/terapie.php');
    }
}

$editId = (int) ($_GET['upravit'] ?? 0);
$isNew = isset($_GET['nova']);
$editing = $editId ? therapy_find($editId) : null;
$old = $_SESSION['form_old'] ?? null;
unset($_SESSION['form_old']);

page_header('Terapie', 'admin', 'admin/terapie.php');
?>
<section class="page-hero"><div class="container"><h1>Terapie</h1></div></section>
<section class="section" style="padding-top:32px">
<div class="container">
<?php if ($editing || $isNew):
    $v = $old ?? ($editing ?: ['name' => '', 'description' => '', 'duration_min' => 60, 'price' => '', 'active' => 1, 'sort_order' => 0]);
    $assigned = [];
    if ($old) {
        $assigned = array_map('intval', (array) ($old['therapists'] ?? []));
    } elseif ($editing) {
        $s = db()->prepare('SELECT therapist_id FROM therapist_therapy WHERE therapy_id = ?');
        $s->execute([$editing['id']]);
        $assigned = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
    }
?>
  <p><a href="<?= e(url('admin/terapie.php')) ?>">← späť na zoznam</a></p>
  <form method="post" class="card" style="max-width:820px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
    <h2><?= $editing ? 'Upraviť terapiu' : 'Nová terapia' ?></h2>
    <div class="form-row"><label class="required">Názov</label><input type="text" name="name" required value="<?= e($v['name']) ?>"></div>
    <div class="form-row"><label>Popis</label><textarea name="description"><?= e($v['description']) ?></textarea></div>
    <div class="form-grid">
      <div class="form-row"><label>Predvolená dĺžka (min)</label><input type="number" name="duration_min" min="10" max="480" value="<?= (int) $v['duration_min'] ?>"><div class="help">Použije sa ako predvolená dĺžka pri vypisovaní termínov.</div></div>
      <div class="form-row"><label>Cena (voliteľné)</label><input type="text" name="price" value="<?= e($v['price']) ?>" placeholder="napr. 30 €"></div>
      <div class="form-row"><label>Poradie</label><input type="number" name="sort_order" value="<?= (int) $v['sort_order'] ?>"></div>
    </div>
    <div class="form-row"><label class="check"><input type="checkbox" name="active" value="1" <?= !empty($v['active']) ? 'checked' : '' ?>> Aktívna (zobrazuje sa a dá sa rezervovať)</label></div>
    <div class="form-row"><label>Terapeuti, ktorí terapiu vykonávajú</label>
      <div class="checks">
        <?php foreach ($therapists as $t): ?>
          <label class="check"><input type="checkbox" name="therapists[]" value="<?= (int) $t['id'] ?>" <?= in_array((int) $t['id'], $assigned, true) ? 'checked' : '' ?>>
            <?= e(therapist_full_name($t)) ?><?= $t['active'] ? '' : ' <span class="muted">(neaktívny)</span>' ?></label>
        <?php endforeach; ?>
        <?php if (!$therapists): ?><span class="muted">Zatiaľ žiadni terapeuti.</span><?php endif; ?>
      </div>
    </div>
    <button class="btn"><?= $editing ? 'Uložiť zmeny' : 'Vytvoriť terapiu' ?></button>
  </form>
  <?php if ($editing): ?>
    <form method="post" style="margin-top:16px" onsubmit="return confirm('Naozaj zmazať terapiu?')">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
      <button class="btn btn-danger btn-sm">Zmazať terapiu</button>
    </form>
  <?php endif; ?>

<?php else:
    $list = therapies_all(false);
    $counts = [];
    foreach (db()->query('SELECT tt.therapy_id, t.title, t.title_after, t.first_name, t.last_name FROM therapist_therapy tt JOIN therapists t ON t.id = tt.therapist_id ORDER BY t.last_name') as $r) {
        $counts[(int) $r['therapy_id']][] = therapist_full_name($r);
    }
?>
  <p><a class="btn btn-accent" href="?nova=1">+ Nová terapia</a></p>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Názov</th><th>Dĺžka</th><th>Cena</th><th>Terapeuti</th><th>Stav</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($list as $t): ?>
        <tr>
          <td><strong><?= e($t['name']) ?></strong><div class="small muted"><?= e(mb_strimwidth($t['description'], 0, 120, '…')) ?></div></td>
          <td><?= (int) $t['duration_min'] ?> min</td>
          <td><?= e($t['price']) ?></td>
          <td class="small"><?= e(implode(', ', $counts[(int) $t['id']] ?? [])) ?: '<span class="muted">nikto</span>' ?></td>
          <td><span class="status <?= $t['active'] ? 'status-free' : 'status-cancelled' ?>"><?= $t['active'] ? 'Aktívna' : 'Neaktívna' ?></span></td>
          <td class="actions">
            <a class="btn btn-outline btn-sm" href="?upravit=<?= (int) $t['id'] ?>">Upraviť</a>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
              <button class="btn btn-outline btn-sm"><?= $t['active'] ? 'Deaktivovať' : 'Aktivovať' ?></button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="6" class="center muted">Zatiaľ žiadne terapie.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</div>
</section>
<?php page_footer();
