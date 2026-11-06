<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

if (is_post()) {
    csrf_check();
    $id = (int) post('id');
    if (post('action') === 'toggle' && $id) {
        db()->prepare("UPDATE inquiries SET status = CASE status WHEN 'new' THEN 'done' ELSE 'new' END WHERE id = ?")->execute([$id]);
        flash('success', 'Stav dopytu bol zmenený.');
    }
    if (post('action') === 'delete' && $id) {
        db()->prepare('DELETE FROM inquiries WHERE id = ?')->execute([$id]);
        flash('success', 'Dopyt bol zmazaný.');
    }
    redirect('admin/dopyty.php' . (isset($_GET['vsetky']) ? '?vsetky=1' : ''));
}

$all = isset($_GET['vsetky']);
$list = db()->query('SELECT * FROM inquiries' . ($all ? '' : " WHERE status = 'new'") . ' ORDER BY created_at DESC LIMIT 300')->fetchAll();

page_header('Dopyty', 'admin', 'admin/dopyty.php');
?>
<section class="page-hero"><div class="container">
  <h1>Dopyty z kontaktného formulára</h1>
  <p>Služby s objednaním „iba kontaktný formulár“ (napr. ABA, INPP) a všeobecné otázky z webu.</p>
</div></section>
<section class="section" style="padding-top:32px">
<div class="container">
  <p>
    <a class="btn btn-sm <?= $all ? 'btn-outline' : '' ?>" href="?">Nevybavené</a>
    <a class="btn btn-sm <?= $all ? '' : 'btn-outline' ?>" href="?vsetky=1">Všetky</a>
  </p>
  <?php if (!$list): ?>
    <div class="card muted center"><?= $all ? 'Zatiaľ žiadne dopyty.' : 'Žiadne nevybavené dopyty.' ?></div>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Prijaté</th><th>Služba</th><th>Klient</th><th>Správa</th><th>Stav</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $q): ?>
      <tr>
        <td class="small"><?= e(fmt_datetime(substr($q['created_at'], 0, 16))) ?></td>
        <td><?= e($q['therapy_name']) ?></td>
        <td><strong><?= e($q['client_name']) ?></strong><br>
          <a class="small" href="mailto:<?= e($q['client_email']) ?>"><?= e($q['client_email']) ?></a>
          <?php if ($q['client_phone']): ?><br><a class="small" href="tel:<?= e(preg_replace('/\s+/', '', $q['client_phone'])) ?>"><?= e($q['client_phone']) ?></a><?php endif; ?>
          <?php if ($q['child_name'] || $q['child_age']): ?><div class="small muted">Dieťa: <?= e(trim($q['child_name'] . ', ' . $q['child_age'], ', ')) ?></div><?php endif; ?></td>
        <td class="small" style="max-width:420px"><?= nl2br(e($q['message'])) ?></td>
        <td><span class="status <?= $q['status'] === 'new' ? 'status-free' : 'status-cancelled' ?>"><?= $q['status'] === 'new' ? 'Nový' : 'Vybavený' ?></span></td>
        <td class="actions">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
            <button class="btn btn-outline btn-sm"><?= $q['status'] === 'new' ? 'Označiť ako vybavený' : 'Vrátiť medzi nové' ?></button></form>
          <form method="post" onsubmit="return confirm('Zmazať dopyt?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
            <button class="btn btn-danger btn-sm">Zmazať</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
</section>
<?php page_footer();
