<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_therapist(true);
$mustChange = (int) $me['must_change_password'] === 1;

$error = null;
$profileError = null;
if (is_post()) {
    csrf_check();
    $action = post('action', 'password');

    if ($action === 'profile' && !$mustChange) {
        $phone = mb_substr(post('phone'), 0, 40);
        $description = mb_substr(post('description'), 0, 3000);
        if ($phone !== '' && !preg_match('/^[0-9 +()\/-]{6,}$/', $phone)) {
            $profileError = 'Zadajte platné telefónne číslo.';
        } else {
            [$photo, $photoErr] = therapist_photo_upload((int) $me['id']);
            if ($photoErr) {
                $profileError = $photoErr;
            } else {
                db()->prepare('UPDATE therapists SET phone = ?, description = ? WHERE id = ?')
                    ->execute([$phone, $description, $me['id']]);
                if ($photo) {
                    therapist_photo_set($me, $photo);
                } elseif (post('remove_photo') === '1') {
                    therapist_photo_set($me, '');
                }
                flash('success', 'Profil bol uložený.' . ($photo ? ' Nová fotka sa už zobrazuje na webe.' : ''));
                redirect('terapeut/profil.php');
            }
        }
    }

    if ($action === 'password') {
        $current = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['new'] ?? '');
        if (!password_verify($current, $me['password_hash'])) {
            $error = 'Súčasné heslo nie je správne.';
        } elseif ($current === $new) {
            $error = 'Nové heslo musí byť iné ako súčasné.';
        } elseif ($err = validate_new_password($new, (string) ($_POST['confirm'] ?? ''))) {
            $error = $err;
        } else {
            db()->prepare('UPDATE therapists SET password_hash = ?, must_change_password = 0 WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            flash('success', 'Heslo bolo úspešne zmenené.');
            redirect($mustChange ? 'terapeut/index.php' : 'terapeut/profil.php');
        }
    }
}
$therapies = therapies_by_therapist()[(int) $me['id']] ?? [];
$photoUrl = therapist_photo_url($me);

page_header('Profil a heslo', 'therapist', 'terapeut/profil.php');
?>
<section class="page-hero"><div class="container">
  <h1>Profil a heslo</h1>
  <p>Telefón, popis a fotka sa zobrazujú klientom na webe a pri výbere terapeuta v rezervácii.</p>
</div></section>
<section class="section" style="padding-top:32px">
  <div class="container dash-grid" style="grid-template-columns:minmax(0,1.5fr) minmax(0,1fr)">
    <div class="card">
      <h3>Môj profil</h3>
      <?php if ($mustChange): ?>
        <div class="alert alert-info">Profil budete môcť upraviť po nastavení vlastného hesla.</div>
      <?php else: ?>
      <?php if ($profileError): ?><div class="alert alert-error"><?= e($profileError) ?></div><?php endif; ?>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="action" value="profile">
        <div class="photo-edit">
          <div class="photo-preview" id="photo-preview">
            <?php if ($photoUrl): ?><img src="<?= e($photoUrl) ?>" alt=""><?php else: ?><span><?= e(mb_substr($me['first_name'], 0, 1) . mb_substr($me['last_name'], 0, 1)) ?></span><?php endif; ?>
          </div>
          <div>
            <label for="photo">Fotka</label>
            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
            <div class="help">JPG, PNG alebo WEBP, max. 8 MB. Fotka sa automaticky oreže na štvorec.</div>
            <?php if ($photoUrl): ?>
              <label class="check small" style="margin-top:8px"><input type="checkbox" name="remove_photo" value="1"> Odstrániť fotku</label>
            <?php endif; ?>
          </div>
        </div>
        <div class="form-row"><label for="phone">Telefón</label>
          <input type="tel" id="phone" name="phone" maxlength="40" value="<?= e($profileError ? post('phone') : $me['phone']) ?>"></div>
        <div class="form-row"><label for="description">Stručný popis</label>
          <textarea id="description" name="description" maxlength="3000" rows="6"><?= e($profileError ? post('description') : $me['description']) ?></textarea>
          <div class="help">Vzdelanie, prax, na čo sa zameriavate – zobrazí sa pri vašom mene na webe.</div></div>
        <button class="btn">Uložiť profil</button>
      </form>
      <?php endif; ?>
    </div>

    <div>
      <div class="card">
        <h3>Údaje spravované administrátorom</h3>
        <table class="table">
          <tr><th>Meno</th><td><?= e(therapist_full_name($me)) ?></td></tr>
          <tr><th>Pozícia</th><td><?= e($me['position']) ?: '—' ?></td></tr>
          <tr><th>E-mail</th><td><?= e($me['email']) ?></td></tr>
          <tr><th>Terapie</th><td><?= e(implode(', ', array_column($therapies, 'name'))) ?: '—' ?></td></tr>
        </table>
      </div>

      <div class="card" style="margin-top:20px" id="heslo">
        <h3>Zmena hesla</h3>
        <?php if ($mustChange): ?><div class="alert alert-info">Prihlásili ste sa heslom od administrátora. Pred pokračovaním si nastavte vlastné heslo.</div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="password">
          <div class="form-row"><label>Súčasné heslo</label><input type="password" name="current" required autocomplete="current-password"></div>
          <div class="form-row"><label>Nové heslo</label><input type="password" name="new" required minlength="8" autocomplete="new-password"><div class="help">Aspoň 8 znakov.</div></div>
          <div class="form-row"><label>Nové heslo znova</label><input type="password" name="confirm" required minlength="8" autocomplete="new-password"></div>
          <button class="btn">Zmeniť heslo</button>
        </form>
      </div>
    </div>
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
