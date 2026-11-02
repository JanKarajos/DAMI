<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$me = require_therapist();

if (is_post() && post('action') === 'cancel') {
    csrf_check();
    $b = booking_find((int) post('booking_id'));
    if ($b && (int) $b['therapist_id'] === (int) $me['id'] && $b['status'] === 'confirmed') {
        cancel_booking($b, 'therapist');
        if (post('delete_slot') === '1' && $b['slot_id']) {
            slot_delete((int) $me['id'], (int) $b['slot_id']);
        }
        flash('success', 'Rezervácia bola zrušená a klient bol informovaný e-mailom.');
    }
    redirect('terapeut/rezervacie.php');
}

$showPast = isset($_GET['minule']);
$s = db()->prepare(
    'SELECT b.*, t.title, t.title_after, t.first_name, t.last_name, t.email AS therapist_email, t.phone AS therapist_phone FROM bookings b
     JOIN therapists t ON t.id = b.therapist_id
     WHERE b.therapist_id = ? AND b.starts_at ' . ($showPast ? '<' : '>=') . ' ?
     ORDER BY b.starts_at ' . ($showPast ? 'DESC LIMIT 200' : 'ASC')
);
$s->execute([$me['id'], date('Y-m-d H:i')]);
$bookings = $s->fetchAll();

page_header('Rezervácie', 'therapist', 'terapeut/rezervacie.php');
?>
<section class="page-hero"><div class="container"><h1><?= $showPast ? 'Minulé rezervácie' : 'Nadchádzajúce rezervácie' ?></h1></div></section>
<section class="section" style="padding-top:32px">
  <div class="container">
    <p><a class="btn btn-outline btn-sm" href="?<?= $showPast ? '' : 'minule' ?>"><?= $showPast ? 'Zobraziť nadchádzajúce' : 'Zobraziť minulé' ?></a></p>
    <?php include __DIR__ . '/../app/partials/bookings_table.php'; ?>
  </div>
</section>
<?php page_footer();
