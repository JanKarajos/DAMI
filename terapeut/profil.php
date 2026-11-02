<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_therapist(true);
$mustChange = (int) $me['must_change_password'] === 1;

$error = null;
if (is_post()) {
    csrf_check();
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
$therapies = therapies_by_therapist()[(int) $me['id']] ?? [];

page_header('Profil a heslo', 'therapist', 'terapeut/profil.php');
?>
<section class="page-hero"><div class="container"><h1>Profil a heslo</h1></div></section>
<section class="section" style="padding-top:32px">
  <div class="container grid grid-2" style="align-items:start">
    <div class="card">
      <h3>Moje údaje</h3>
      <table class="table">
        <tr><th>Meno</th><td><?= e(therapist_full_name($me)) ?></td></tr>
        <tr><th>Pozícia</th><td><?= e($me['position']) ?></td></tr>
        <tr><th>E-mail (prihlasovací)</th><td><?= e($me['email']) ?></td></tr>
        <tr><th>Telefón</th><td><?= e($me['phone']) ?></td></tr>
        <tr><th>Terapie</th><td><?= e(implode(', ', array_column($therapies, 'name'))) ?: '—' ?></td></tr>
        <tr><th>Popis</th><td class="small"><?= nl2br(e($me['description'])) ?></td></tr>
      </table>
      <p class="small muted">Zmenu údajov vykonáva administrátor.</p>
    </div>
    <div class="card">
      <h3>Zmena hesla</h3>
      <?php if ($mustChange): ?><div class="alert alert-info">Prihlásili ste sa heslom od administrátora. Pred pokračovaním si nastavte vlastné heslo.</div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="form-row"><label>Súčasné heslo</label><input type="password" name="current" required autocomplete="current-password"></div>
        <div class="form-row"><label>Nové heslo</label><input type="password" name="new" required minlength="8" autocomplete="new-password"><div class="help">Aspoň 8 znakov.</div></div>
        <div class="form-row"><label>Nové heslo znova</label><input type="password" name="confirm" required minlength="8" autocomplete="new-password"></div>
        <button class="btn">Zmeniť heslo</button>
      </form>
    </div>
  </div>
</section>
<?php page_footer();
