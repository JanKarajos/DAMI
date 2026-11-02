<?php
require dirname(__DIR__) . '/app/bootstrap.php';

if (current_admin()) {
    redirect('admin/index.php');
}
$error = null;
if (is_post()) {
    csrf_check();
    if (login_throttled('admin')) {
        $error = 'Príliš veľa neúspešných pokusov. Skúste to znova o 15 minút.';
    } elseif (admin_attempt_login(post('username'), (string) ($_POST['password'] ?? ''))) {
        login_clear('admin');
        redirect('admin/index.php');
    } else {
        login_failed('admin');
        $error = 'Nesprávne meno alebo heslo.';
    }
}
page_header('Prihlásenie administrátora', 'admin');
?>
<div class="container">
  <div class="card auth-box">
    <h1>Administrácia</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="form-row"><label for="username">Používateľské meno</label>
        <input type="text" id="username" name="username" required autofocus autocomplete="username" value="<?= e(post('username')) ?>"></div>
      <div class="form-row"><label for="password">Heslo</label>
        <input type="password" id="password" name="password" required autocomplete="current-password"></div>
      <button class="btn">Prihlásiť sa</button>
    </form>
  </div>
</div>
<?php page_footer();
