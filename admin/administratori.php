<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_admin();

if (is_post()) {
    csrf_check();
    $action = post('action');
    $id = (int) post('id');

    if ($action === 'create') {
        $username = post('username');
        $s = db()->prepare('SELECT 1 FROM admins WHERE username = ?');
        $s->execute([$username]);
        if (!preg_match('/^[a-zA-Z0-9._@-]{3,60}$/', $username)) {
            flash('error', 'Používateľské meno môže obsahovať písmená, čísla a znaky . _ @ - (3–60 znakov).');
        } elseif ($s->fetchColumn()) {
            flash('error', 'Toto používateľské meno už existuje.');
        } else {
            $password = random_password(12);
            db()->prepare('INSERT INTO admins (username, password_hash, must_change_password) VALUES (?, ?, 1)')
                ->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            $_SESSION['admin_credentials'] = ['username' => $username, 'password' => $password];
            flash('success', 'Administrátor bol vytvorený.');
        }
        redirect('admin/administratori.php');
    }

    if ($action === 'reset' && $id && $id !== (int) $me['id']) {
        $s = db()->prepare('SELECT username FROM admins WHERE id = ?');
        $s->execute([$id]);
        $username = $s->fetchColumn();
        if ($username !== false) {
            $password = random_password(12);
            db()->prepare('UPDATE admins SET password_hash = ?, must_change_password = 1 WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            $_SESSION['admin_credentials'] = ['username' => $username, 'password' => $password];
            flash('success', 'Heslo bolo vygenerované nanovo.');
        }
        redirect('admin/administratori.php');
    }

    if ($action === 'delete' && $id && $id !== (int) $me['id']) {
        $count = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
        if ($count > 1) {
            db()->prepare('DELETE FROM admins WHERE id = ?')->execute([$id]);
            flash('success', 'Administrátor bol zmazaný.');
        }
        redirect('admin/administratori.php');
    }
}

$credentials = isset($_SESSION['admin_credentials']) ? $_SESSION['admin_credentials'] : null;
unset($_SESSION['admin_credentials']);
$admins = db()->query('SELECT id, username, must_change_password, created_at FROM admins ORDER BY username')->fetchAll();

page_header('Administrátori', 'admin', 'admin/administratori.php');
?>
<section class="page-hero"><div class="container">
  <h1>Administrátori</h1>
  <p>Každý administrátor má vlastné prihlásenie a rovnaké práva: správa terapeutov, služieb, rezervácií a dopytov.</p>
</div></section>
<section class="section" style="padding-top:32px">
<div class="container">
<?php if ($credentials): ?>
  <div class="credentials" style="margin-bottom:24px">
    <strong>Prihlasovacie údaje pre <?= e($credentials['username']) ?></strong> – heslo sa zobrazí iba teraz, odovzdajte ho novému administrátorovi:<br><br>
    Adresa: <?= e(abs_url('admin/')) ?><br>
    Používateľské meno: <?= e($credentials['username']) ?><br>
    Heslo: <?= e($credentials['password']) ?><br><br>
    <span style="font-family:var(--font)">Pri prvom prihlásení si administrátor musí nastaviť vlastné heslo.</span>
  </div>
<?php endif; ?>

  <div class="dash-grid" style="grid-template-columns:minmax(0,1.5fr) minmax(0,1fr)">
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Používateľské meno</th><th>Vytvorený</th><th>Stav</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($admins as $a): $self = (int) $a['id'] === (int) $me['id']; ?>
        <tr>
          <td><strong><?= e($a['username']) ?></strong><?= $self ? ' <span class="small muted">(vy)</span>' : '' ?></td>
          <td class="small"><?= e(fmt_date(substr($a['created_at'], 0, 10))) ?></td>
          <td><?= (int) $a['must_change_password'] === 1
              ? '<span class="status status-booked">Čaká na zmenu hesla</span>'
              : '<span class="status status-free">Aktívny</span>' ?></td>
          <td class="actions">
            <?php if ($self): ?>
              <a class="btn btn-outline btn-sm" href="<?= e(url('admin/heslo.php')) ?>">Zmeniť moje heslo</a>
            <?php else: ?>
              <form method="post" onsubmit="return confirm('Vygenerovať nové heslo pre <?= e($a['username']) ?>?')"><?= csrf_field() ?>
                <input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <button class="btn btn-outline btn-sm">Nové heslo</button></form>
              <form method="post" onsubmit="return confirm('Naozaj zmazať administrátora <?= e($a['username']) ?>?')"><?= csrf_field() ?>
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <button class="btn btn-danger btn-sm">Zmazať</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>

    <div class="card">
      <h3>Pridať administrátora</h3>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="form-row"><label class="required">Používateľské meno</label>
          <input type="text" name="username" required maxlength="60" pattern="[a-zA-Z0-9._@-]{3,60}" placeholder="napr. lucia alebo lucia@ozdami.sk"></div>
        <div class="help" style="margin-bottom:14px">Heslo sa vygeneruje automaticky a zobrazí sa iba raz. Pri prvom prihlásení si ho administrátor zmení.</div>
        <button class="btn btn-accent">Vytvoriť administrátora</button>
      </form>
    </div>
  </div>
</div>
</section>
<?php page_footer();
