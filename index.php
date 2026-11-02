<?php
require __DIR__ . '/app/bootstrap.php';

$therapies = therapies_all();
$therapists = therapists_all();
$byTherapist = therapies_by_therapist();

page_header('Domov');
?>
<section class="hero">
  <span class="bubble" style="width:260px;height:260px;right:-60px;top:-60px"></span>
  <span class="bubble" style="width:120px;height:120px;right:22%;bottom:40px"></span>
  <div class="container">
    <h1>Pomáhame deťom rozvíjať ich potenciál</h1>
    <p>Občianske združenie DAMI v Prešove poskytuje terapie pre deti so zdravotným znevýhodnením a podporu ich rodinám. Termín si teraz môžete zarezervovať jednoducho online.</p>
    <p style="margin-top:28px">
      <a class="btn btn-accent" href="<?= e(url('rezervacia.php')) ?>">Rezervovať termín</a>
      &nbsp;<a class="btn btn-light" href="#terapie">Ponuka terapií</a>
    </p>
  </div>
</section>

<section class="section" id="o-nas">
  <div class="container grid grid-2" style="align-items:center">
    <div>
      <h2>O nás</h2>
      <p>Sme mimovládna, nezisková a nepolitická organizácia, ktorá vznikla s cieľom pomáhať deťom s ťažkým zdravotným znevýhodnením a ich rodinám.</p>
      <p>Pracujeme metódami <strong>aplikovanej behaviorálnej analýzy (ABA)</strong> a <strong>neurofeedbacku (EEG biofeedback)</strong>. Naším cieľom je zvyšovať potenciál dieťaťa vo všetkých základných oblastiach života.</p>
    </div>
    <div class="card" style="border-left:5px solid var(--warm)">
      <h3>Ako prebieha rezervácia?</h3>
      <ol style="padding-left:20px;margin:0">
        <li>Vyberiete si terapiu.</li>
        <li>Zvolíte terapeuta, ktorý ju vykonáva.</li>
        <li>V kalendári vyberiete voľný deň a čas.</li>
        <li>Vyplníte kontaktné údaje – potvrdenie príde e-mailom.</li>
        <li>Deň pred termínom vám pošleme pripomienku.</li>
      </ol>
    </div>
  </div>
</section>

<section class="section section-soft" id="terapie">
  <div class="container">
    <div class="section-title">
      <h2>Naše terapie</h2>
      <p>Programy sú individuálne a prispôsobené potrebám a možnostiam každého dieťaťa.</p>
    </div>
    <div class="grid grid-3">
      <?php foreach ($therapies as $t): ?>
        <article class="card therapy-card">
          <h3><?= e($t['name']) ?></h3>
          <div class="meta">⏱ <?= (int) $t['duration_min'] ?> min<?= $t['price'] !== '' ? ' · ' . e($t['price']) : '' ?></div>
          <p><?= nl2br(e($t['description'])) ?></p>
          <a class="btn btn-outline btn-sm" href="<?= e(url('rezervacia.php?terapia=' . $t['id'])) ?>">Rezervovať</a>
        </article>
      <?php endforeach; ?>
      <?php if (!$therapies): ?><p class="center muted">Ponuka terapií sa pripravuje.</p><?php endif; ?>
    </div>
  </div>
</section>

<section class="section" id="tim">
  <div class="container">
    <div class="section-title">
      <h2>Náš tím</h2>
      <p>Odborníci, ktorí s vašimi deťmi pracujú.</p>
    </div>
    <div class="grid grid-3">
      <?php foreach ($therapists as $p): ?>
        <article class="card person-card">
          <div class="avatar"><?= e(mb_substr($p['first_name'], 0, 1) . mb_substr($p['last_name'], 0, 1)) ?></div>
          <h3><?= e(therapist_full_name($p)) ?></h3>
          <?php if ($p['position']): ?><div class="position"><?= e($p['position']) ?></div><?php endif; ?>
          <p class="small"><?= nl2br(e($p['description'])) ?></p>
          <div class="tags">
            <?php foreach ($byTherapist[(int) $p['id']] ?? [] as $th): ?>
              <span class="tag"><?= e($th['name']) ?></span>
            <?php endforeach; ?>
          </div>
        </article>
      <?php endforeach; ?>
      <?php if (!$therapists): ?><p class="center muted">Informácie o tíme pripravujeme.</p><?php endif; ?>
    </div>
  </div>
</section>
<?php page_footer();
