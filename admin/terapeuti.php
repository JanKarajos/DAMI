<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

$therapies = therapies_all(false);
$therapyIds = array_map(fn($t) => (int) $t['id'], $therapies);

function send_credentials_mail(array $t, string $password, bool $isNew): bool
{
    $html = mail_layout($isNew ? 'Prístup do rezervačného systému' : 'Nové heslo do rezervačného systému', '
        <p>Dobrý deň, ' . e(therapist_full_name($t)) . ',</p>
        <p>' . ($isNew ? 'bol vám vytvorený účet terapeuta' : 'administrátor vám nastavil nové heslo') . ' v rezervačnom systéme ' . e(cfg('site_name')) . '.</p>
        <table cellpadding="6" style="font-size:15px;margin:12px 0;background:#fffbea;border-radius:8px">
          <tr><td>Prihlasovací e-mail:</td><td><strong>' . e($t['email']) . '</strong></td></tr>
          <tr><td>Heslo:</td><td><strong style="font-family:monospace">' . e($password) . '</strong></td></tr>
        </table>
        <p>Po prvom prihlásení budete vyzvaný/á nastaviť si vlastné heslo.</p>
        <p>' . mail_button(abs_url('terapeut/prihlasenie.php'), 'Prihlásiť sa') . '</p>');
    return send_mail($t['email'], $isNew ? 'Prístup do rezervačného systému' : 'Nové heslo do rezervačného systému', $html);
}

/* ---------- Akcie ---------- */
if (is_post()) {
    csrf_check();
    $action = post('action');
    $id = (int) post('id');

    if ($action === 'save') {
        $d = [
            'title'       => mb_substr(post('title'), 0, 60),
            'title_after' => mb_substr(post('title_after'), 0, 60),
            'first_name'  => mb_substr(post('first_name'), 0, 80),
            'last_name'   => mb_substr(post('last_name'), 0, 80),
            'email'       => mb_strtolower(mb_substr(post('email'), 0, 160)),
            'phone'       => mb_substr(post('phone'), 0, 40),
            'description' => mb_substr(post('description'), 0, 3000),
            'position'    => mb_substr(post('position'), 0, 120),
            'active'      => post('active') === '1' ? 1 : 0,
            'sort_order'  => (int) post('sort_order'),
        ];
        $selected = array_values(array_intersect(array_map('intval', (array) ($_POST['therapies'] ?? [])), $therapyIds));
        $errors = [];
        if ($d['first_name'] === '' || $d['last_name'] === '') $errors[] = 'Meno a priezvisko sú povinné.';
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Zadajte platný e-mail.';
        $s = db()->prepare('SELECT 1 FROM therapists WHERE email = ? AND id != ?');
        $s->execute([$d['email'], $id]);
        if ($s->fetchColumn()) $errors[] = 'Terapeut s týmto e-mailom už existuje.';
        $password = (string) ($_POST['password'] ?? '');
        if ($password !== '' && mb_strlen($password) < 8) $errors[] = 'Heslo musí mať aspoň 8 znakov.';

        if ($errors) {
            $_SESSION['form_old'] = $_POST;
            foreach ($errors as $er) flash('error', $er);
            redirect('admin/terapeuti.php?' . ($id ? 'upravit=' . $id : 'novy=1'));
        }

        $pdo = db();
        $pdo->beginTransaction();
        if ($id) {
            $cols = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($d)));
            $pdo->prepare("UPDATE therapists SET $cols WHERE id = :id")->execute($d + ['id' => $id]);
        } else {
            $password = $password !== '' ? $password : random_password();
            $d['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $cols = array_keys($d);
            $pdo->prepare('INSERT INTO therapists (' . implode(',', $cols) . ') VALUES (:' . implode(',:', $cols) . ')')->execute($d);
            $id = (int) $pdo->lastInsertId();
        }
        $pdo->prepare('DELETE FROM therapist_therapy WHERE therapist_id = ?')->execute([$id]);
        $ins = $pdo->prepare('INSERT INTO therapist_therapy (therapist_id, therapy_id) VALUES (?, ?)');
        foreach ($selected as $tid) {
            $ins->execute([$id, $tid]);
        }
        $pdo->commit();

        // Fotka (nahranie / odstránenie)
        $t = therapist_find($id);
        [$photo, $photoErr] = therapist_photo_upload($id);
        if ($photoErr) {
            flash('error', $photoErr);
        } elseif ($photo) {
            therapist_photo_set($t, $photo);
        } elseif (post('remove_photo') === '1') {
            therapist_photo_set($t, '');
        }

        if (!post('id')) {
            $t = therapist_find($id);
            $mailed = post('send_mail') === '1' && send_credentials_mail($t, $password, true);
            $_SESSION['credentials'] = ['name' => therapist_full_name($t), 'email' => $t['email'], 'password' => $password, 'mailed' => $mailed];
            flash('success', 'Terapeut bol vytvorený.');
        } else {
            flash('success', 'Údaje terapeuta boli uložené.');
        }
        redirect('admin/terapeuti.php');
    }

    $t = $id ? therapist_find($id) : null;
    if (!$t) {
        redirect('admin/terapeuti.php');
    }

    if ($action === 'reset_password') {
        if (is_placeholder_email($t['email'])) {
            flash('error', 'Najprv doplňte skutočný e-mail terapeuta (slúži na prihlásenie) a uložte zmeny.');
            redirect('admin/terapeuti.php?upravit=' . $id);
        }
        $password = (string) ($_POST['new_password'] ?? '');
        if ($password !== '' && mb_strlen($password) < 8) {
            flash('error', 'Heslo musí mať aspoň 8 znakov.');
            redirect('admin/terapeuti.php?upravit=' . $id);
        }
        $password = $password !== '' ? $password : random_password();
        db()->prepare('UPDATE therapists SET password_hash = ?, must_change_password = 1 WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        $mailed = post('send_mail') === '1' && send_credentials_mail($t, $password, false);
        $_SESSION['credentials'] = ['name' => therapist_full_name($t), 'email' => $t['email'], 'password' => $password, 'mailed' => $mailed];
        flash('success', 'Heslo bolo zmenené. Terapeut si ho pri prihlásení musí zmeniť.');
        redirect('admin/terapeuti.php');
    }

    if ($action === 'toggle') {
        db()->prepare('UPDATE therapists SET active = 1 - active WHERE id = ?')->execute([$id]);
        flash('success', $t['active'] ? 'Terapeut bol deaktivovaný (nezobrazuje sa na webe a nemôže sa prihlásiť).' : 'Terapeut bol aktivovaný.');
        redirect('admin/terapeuti.php');
    }

    if ($action === 'delete') {
        $s = db()->prepare("SELECT COUNT(*) FROM bookings WHERE therapist_id = ? AND status = 'confirmed' AND starts_at >= ?");
        $s->execute([$id, date('Y-m-d H:i')]);
        if ((int) $s->fetchColumn() > 0) {
            flash('error', 'Terapeut má nadchádzajúce rezervácie. Najprv ich zrušte, alebo ho len deaktivujte.');
        } else {
            db()->prepare('DELETE FROM therapists WHERE id = ?')->execute([$id]);
            therapist_photo_delete_file((string) $t['photo']);
            flash('success', 'Terapeut ' . therapist_full_name($t) . ' bol zmazaný.');
        }
        redirect('admin/terapeuti.php');
    }
}

/* ---------- Zobrazenie ---------- */
$editId = (int) ($_GET['upravit'] ?? 0);
$isNew = isset($_GET['novy']);
$editing = $editId ? therapist_find($editId) : null;
$old = $_SESSION['form_old'] ?? null;
unset($_SESSION['form_old']);
$credentials = $_SESSION['credentials'] ?? null;
unset($_SESSION['credentials']);

page_header('Terapeuti', 'admin', 'admin/terapeuti.php');
?>
<section class="page-hero"><div class="container"><h1>Terapeuti</h1></div></section>
<section class="section" style="padding-top:32px">
<div class="container">

<?php if ($credentials): ?>
  <div class="credentials" style="margin-bottom:24px">
    <strong>Prihlasovacie údaje pre <?= e($credentials['name']) ?></strong> – heslo sa zobrazí iba teraz, odovzdajte ho terapeutovi:<br><br>
    Adresa: <?= e(abs_url('terapeut/prihlasenie.php')) ?><br>
    E-mail: <?= e($credentials['email']) ?><br>
    Heslo: <?= e($credentials['password']) ?><br><br>
    <span style="font-family:var(--font)"><?= $credentials['mailed'] ? '✅ Údaje boli odoslané aj e-mailom.' : 'Údaje neboli odoslané e-mailom.' ?></span>
  </div>
<?php endif; ?>

<?php if ($editing || $isNew):
    $v = $old ?? ($editing ?: ['title' => '', 'title_after' => '', 'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'description' => '', 'position' => '', 'active' => 1, 'sort_order' => 0]);
    $checked = $old ? array_map('intval', (array) ($old['therapies'] ?? [])) : ($editing ? therapy_ids_of_therapist((int) $editing['id']) : []);
?>
  <p><a href="<?= e(url('admin/terapeuti.php')) ?>">← späť na zoznam</a></p>
  <div class="dash-grid" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr)">
    <form method="post" class="card" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
      <h2><?= $editing ? 'Upraviť: ' . e(therapist_full_name($editing)) : 'Nový terapeut' ?></h2>
      <div class="form-grid">
        <div class="form-row"><label>Titul</label><input type="text" name="title" value="<?= e($v['title']) ?>" placeholder="napr. Mgr., PhDr."></div>
        <div class="form-row"><label class="required">Meno</label><input type="text" name="first_name" required value="<?= e($v['first_name']) ?>"></div>
        <div class="form-row"><label class="required">Priezvisko</label><input type="text" name="last_name" required value="<?= e($v['last_name']) ?>"></div>
        <div class="form-row"><label>Titul za menom</label><input type="text" name="title_after" value="<?= e($v['title_after'] ?? '') ?>" placeholder="napr. PhD., BCBA"></div>
        <div class="form-row"><label class="required">E-mail (slúži aj na prihlásenie)</label><input type="email" name="email" required value="<?= e($v['email']) ?>">
          <?php if (is_placeholder_email((string) $v['email'])): ?><div class="help" style="color:var(--rose)">⚠ Zástupná adresa – doplňte skutočný e-mail terapeuta, potom mu nastavte heslo.</div><?php endif; ?></div>
        <div class="form-row"><label>Telefón</label><input type="tel" name="phone" value="<?= e($v['phone']) ?>"></div>
        <div class="form-row"><label>Pozícia</label><input type="text" name="position" value="<?= e($v['position']) ?>" placeholder="napr. ABA terapeut, BCBA supervízor"></div>
      </div>
      <div class="form-row"><label>Stručný popis</label><textarea name="description"><?= e($v['description']) ?></textarea></div>
      <div class="photo-edit">
        <div class="photo-preview" id="photo-preview">
          <?php $pu = $editing ? therapist_photo_url($editing) : null; ?>
          <?php if ($pu): ?><img src="<?= e($pu) ?>" alt=""><?php else: ?><span>📷</span><?php endif; ?>
        </div>
        <div>
          <label for="photo">Fotka</label>
          <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
          <div class="help">JPG, PNG alebo WEBP, max. 8 MB. Oreže sa na štvorec. Terapeut si ju môže zmeniť aj sám.</div>
          <?php if ($pu): ?><label class="check small" style="margin-top:8px"><input type="checkbox" name="remove_photo" value="1"> Odstrániť fotku</label><?php endif; ?>
        </div>
      </div>
      <div class="form-row"><label>Terapie, ktoré vykonáva</label>
        <div class="checks">
          <?php foreach ($therapies as $th): ?>
            <label class="check"><input type="checkbox" name="therapies[]" value="<?= (int) $th['id'] ?>" <?= in_array((int) $th['id'], $checked, true) ? 'checked' : '' ?>>
              <?= e($th['name']) ?><?= $th['active'] ? '' : ' <span class="muted">(neaktívna)</span>' ?></label>
          <?php endforeach; ?>
          <?php if (!$therapies): ?><span class="muted">Najprv vytvorte terapie.</span><?php endif; ?>
        </div>
      </div>
      <div class="form-grid">
        <div class="form-row"><label>Poradie na webe</label><input type="number" name="sort_order" value="<?= (int) $v['sort_order'] ?>"></div>
        <div class="form-row"><label>&nbsp;</label><label class="check"><input type="checkbox" name="active" value="1" <?= !empty($v['active']) ? 'checked' : '' ?>> Aktívny (zobrazuje sa a môže sa prihlásiť)</label></div>
      </div>
      <?php if (!$editing): ?>
        <div class="form-grid">
          <div class="form-row"><label>Úvodné heslo</label><input type="text" name="password" minlength="8" autocomplete="off" placeholder="prázdne = vygenerovať"><div class="help">Terapeut si ho pri prvom prihlásení musí zmeniť.</div></div>
          <div class="form-row"><label>&nbsp;</label><label class="check"><input type="checkbox" name="send_mail" value="1" checked> Poslať prihlasovacie údaje e-mailom</label></div>
        </div>
      <?php endif; ?>
      <button class="btn"><?= $editing ? 'Uložiť zmeny' : 'Vytvoriť terapeuta' ?></button>
    </form>

    <?php if ($editing): ?>
    <div>
      <form method="post" class="card">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
        <h3>Nastaviť nové heslo</h3>
        <div class="form-row"><label>Nové heslo</label><input type="text" name="new_password" minlength="8" autocomplete="off" placeholder="prázdne = vygenerovať"></div>
        <label class="check"><input type="checkbox" name="send_mail" value="1" checked> Poslať terapeutovi e-mailom</label>
        <p class="help">Terapeut si heslo pri ďalšom prihlásení musí zmeniť.<?= (int) $editing['must_change_password'] ? ' <br>⚠ Terapeut si ešte nezmenil úvodné heslo.' : '' ?></p>
        <button class="btn btn-accent">Nastaviť heslo</button>
      </form>
      <form method="post" class="card" style="margin-top:20px" onsubmit="return confirm('Naozaj zmazať terapeuta vrátane všetkých jeho termínov a histórie rezervácií?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
        <h3>Zmazať terapeuta</h3>
        <p class="small muted">Odporúčame radšej terapeuta deaktivovať – zachová sa história.</p>
        <button class="btn btn-danger btn-sm">Zmazať</button>
      </form>
    </div>
    <?php endif; ?>
  </div>

<?php else:
    $list = therapists_all(false);
    $map = therapies_by_therapist(false);
?>
  <p><a class="btn btn-accent" href="?novy=1">+ Nový terapeut</a></p>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Meno</th><th>Pozícia</th><th>Kontakt</th><th>Terapie</th><th>Stav</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($list as $t): ?>
        <tr>
          <td style="display:flex;align-items:center"><?= therapist_avatar_html($t, 'thumb') ?><strong><?= e(therapist_full_name($t)) ?></strong></td>
          <td><?= e($t['position']) ?></td>
          <td class="small"><?php if (is_placeholder_email($t['email'])): ?><span class="status status-booked">doplňte e-mail</span><?php else: ?><?= e($t['email']) ?><?php endif; ?><br><?= e($t['phone']) ?></td>
          <td class="small"><?= e(implode(', ', array_column($map[(int) $t['id']] ?? [], 'name'))) ?: '<span class="muted">—</span>' ?></td>
          <td>
            <span class="status <?= $t['active'] ? 'status-free' : 'status-cancelled' ?>"><?= $t['active'] ? 'Aktívny' : 'Neaktívny' ?></span>
            <?php if ((int) $t['must_change_password']): ?><div class="small muted">čaká na zmenu hesla</div><?php endif; ?>
          </td>
          <td class="actions">
            <a class="btn btn-outline btn-sm" href="?upravit=<?= (int) $t['id'] ?>">Upraviť</a>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
              <button class="btn btn-outline btn-sm"><?= $t['active'] ? 'Deaktivovať' : 'Aktivovať' ?></button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="6" class="center muted">Zatiaľ žiadni terapeuti.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</div>
</section>
<script>
(function () {
  var input = document.getElementById('photo'), box = document.getElementById('photo-preview');
  if (!input || !box) return;
  input.addEventListener('change', function () {
    var f = input.files && input.files[0];
    if (!f) return;
    var img = document.createElement('img');
    img.src = URL.createObjectURL(f);
    box.innerHTML = ''; box.appendChild(img);
  });
})();
</script>
<?php page_footer();
