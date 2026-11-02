<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$admin = require_admin(true);
$mustChange = (int) $admin['must_change_password'] === 1;

$error = null;
if (is_post()) {
    csrf_check();
    $current = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['new'] ?? '');
    $username = post('username');
    if (!password_verify($current, $admin['password_hash'])) {
        $error = 'Súčasné heslo nie je správne.';
    } elseif (!preg_match('/^[a-zA-Z0-9._@-]{3,60}$/', $username)) {
        $error = 'Používateľské meno môže obsahovať písmená, čísla a znaky . _ @ - (3–60 znakov).';
    } elseif ($new === '' && $mustChange) {
        $error = 'Nastavte si nové heslo.';
    } elseif ($new !== '' && ($err = validate_new_password($new, (string) ($_POST['confirm'] ?? '')))) {
        $error = $err;
    } elseif ($new !== '' && $new === $current) {
        $error = 'Nové heslo musí byť iné ako súčasné.';
    } else {
        $s = db()->prepare('SELECT 1 FROM admins WHERE username = ? AND id != ?');
        $s->execute([$username, $admin['id']]);
        if ($s->fetchColumn()) {
            $error = 'Toto používateľské meno už existuje.';
        } else {
            db()->prepare('UPDATE admins SET username = ? WHERE id = ?')->execute([$username, $admin['id']]);
            if ($new !== '') {
                db()->prepare('UPDATE admins SET password_hash = ?, must_change_password = 0 WHERE id = ?')
                    ->execute([password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
                session_regenerate_id(true);
            }
            flash('success', 'Údaje účtu boli uložené.');
            redirect($mustChange ? 'admin/index.php' : 'admin/heslo.php');
        }
    }
}

page_header('Môj účet', 'admin', 'admin/heslo.php');
?>
<section class="page-hero"><div class="container"><h1>Môj účet administrátora</h1></div></section>
<section class="section" style="padding-top:32px">
  <div class="container" style="max-width:560px">
    <div class="card">
      <?php if ($mustChange): ?><div class="alert alert-info">Používate predvolené heslo. Zmeňte si ho prosím.</div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="form-row"><label>Používateľské meno</label><input type="text" name="username" required value="<?= e(is_post() ? post('username') : $admin['username']) ?>"></div>
        <div class="form-row"><label>Súčasné heslo</label><input type="password" name="current" required autocomplete="current-password"></div>
        <div class="form-row"><label>Nové heslo<?= $mustChange ? '' : ' (nechajte prázdne, ak ho nemeníte)' ?></label><input type="password" name="new" minlength="8" autocomplete="new-password" <?= $mustChange ? 'required' : '' ?>></div>
        <div class="form-row"><label>Nové heslo znova</label><input type="password" name="confirm" minlength="8" autocomplete="new-password"></div>
        <button class="btn">Uložiť</button>
      </form>
    </div>
  </div>
</section>
<?php page_footer();
