<?php
require dirname(__DIR__) . '/app/bootstrap.php';

if (current_therapist()) {
    redirect('terapeut/index.php');
}
$error = null;
if (is_post()) {
    csrf_check();
    if (login_throttled('therapist')) {
        $error = 'Príliš veľa neúspešných pokusov. Skúste to znova o 15 minút.';
    } elseif (therapist_attempt_login(post('email'), (string) ($_POST['password'] ?? ''))) {
        login_clear('therapist');
        redirect('terapeut/index.php');
    } else {
        login_failed('therapist');
        $error = 'Nesprávny e-mail alebo heslo.';
    }
}
page_header('Prihlásenie terapeuta', 'therapist');
?>
<div class="container">
  <div class="card auth-box">
    <h1>Prihlásenie terapeuta</h1>
    <p class="muted">Prihlasovacie údaje vám pridelí administrátor.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="form-row"><label for="email">E-mail</label>
        <input type="email" id="email" name="email" required autofocus autocomplete="username" value="<?= e(post('email')) ?>"></div>
      <div class="form-row"><label for="password">Heslo</label>
        <input type="password" id="password" name="password" required autocomplete="current-password"></div>
      <button class="btn">Prihlásiť sa</button>
    </form>
    <p class="small muted" style="margin-top:16px">Zabudli ste heslo? Kontaktujte administrátora, ktorý vám nastaví nové.</p>
  </div>
</div>
<?php page_footer();
