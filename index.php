<?php
require __DIR__ . '/app/bootstrap.php';

$therapies = therapies_all();
$therapists = therapists_all();
$byTherapist = therapies_by_therapist();

page_header('Online rezervácia');
?>
<section class="hero" id="uvod">
  <div class="container">
    <div class="kicker with-bars">Centrum pre deti so špeciálnymi potrebami</div>
    <h1>Termín na terapiu <span class="text-blue">jednoducho online.</span></h1>
    <p class="lead">Vyberte si službu, terapeuta a voľný čas v kalendári. Potvrdenie dostanete e-mailom a pred termínom vám pošleme pripomienku.</p>
    <div class="hero-actions">
      <a class="btn" href="<?= e(url('rezervacia.php')) ?>">Rezervovať termín <span>↘</span></a>
      <a class="text-link" href="#sluzby">Pozrieť služby ↓</a>
    </div>
  </div>
</section>

<section class="section" id="ako">
  <div class="container">
    <div class="section-head">
      <div class="kicker">Ako to funguje</div>
      <h2>Rezervácia v štyroch krokoch.</h2>
    </div>
    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,230px),1fr))">
      <?php foreach ([
          ['01', 'Služba', 'Vyberiete si terapiu alebo diagnostiku.'],
          ['02', 'Terapeut', 'Zvolíte odborníka, ktorý ju u nás vykonáva.'],
          ['03', 'Termín', 'V kalendári vidíte iba skutočne voľné časy.'],
          ['04', 'Potvrdenie', 'E-mail s potvrdením a pripomienka deň vopred.'],
      ] as [$n, $t, $d]): ?>
        <div class="card">
          <div class="kicker" style="margin-bottom:8px"><?= $n ?></div>
          <h3><?= e($t) ?></h3>
          <p class="muted" style="margin:0"><?= e($d) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section-white" id="sluzby">
  <div class="container">
    <div class="section-head">
      <div class="kicker">Služby</div>
      <h2>Vyberte si oblasť, o ktorú máte záujem.</h2>
      <p>Programy sú individuálne a prispôsobené potrebám a možnostiam každého dieťaťa.</p>
    </div>
    <div class="grid grid-2">
      <?php foreach ($therapies as $t): ?>
        <article class="therapy-card">
          <div class="meta"><?= (int) $t['duration_min'] ?> min<?= $t['price'] !== '' ? ' · ' . e($t['price']) : '' ?></div>
          <h3><?= e($t['name']) ?></h3>
          <p><?= nl2br(e($t['description'])) ?></p>
          <a class="btn btn-light btn-sm" href="<?= e(url('rezervacia.php?terapia=' . $t['id'])) ?>">Rezervovať <span>↘</span></a>
        </article>
      <?php endforeach; ?>
      <?php if (!$therapies): ?><p class="muted">Ponuka služieb sa pripravuje.</p><?php endif; ?>
    </div>
    <div class="chips" style="margin-top:46px">
      <?php foreach (['spolupráca', 'hra', 'dôvera', 'pokrok', 'komunikácia', 'samostatnosť'] as $c): ?><span class="chip"><?= $c ?></span><?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section-soft" id="personal">
  <div class="container">
    <div class="section-head">
      <div class="kicker">Personál</div>
      <h2>Ľudia, ktorí vytvárajú bezpečný priestor.</h2>
    </div>
    <div class="panel">
      <div class="grid grid-3">
        <?php foreach ($therapists as $p): ?>
          <article class="person-card">
            <?= therapist_avatar_html($p) ?>
            <?php if ($p['position']): ?><div class="position"><?= e($p['position']) ?></div><?php endif; ?>
            <h3><?= e(therapist_full_name($p)) ?></h3>
            <?php if ($p['description']): ?><p class="small"><?= nl2br(e($p['description'])) ?></p><?php endif; ?>
            <div class="tags">
              <?php foreach ($byTherapist[(int) $p['id']] ?? [] as $th): ?>
                <span class="tag"><?= e($th['name']) ?></span>
              <?php endforeach; ?>
            </div>
          </article>
        <?php endforeach; ?>
        <?php if (!$therapists): ?><p class="muted">Informácie o tíme pripravujeme.</p><?php endif; ?>
      </div>
    </div>
  </div>
</section>

<section class="section" id="kontakty">
  <div class="container">
    <div class="section-head">
      <div class="kicker">Kontakty</div>
      <h2>U nás sú dvere vždy otvorené.</h2>
      <p>Máte otázky k terapiám, diagnostike alebo k rezervácii? Neváhajte nás kontaktovať e-mailom alebo telefonicky.</p>
    </div>
    <div class="grid grid-3">
      <div class="contact-card"><div class="label">Prevádzka</div><?= e(cfg('contact.address')) ?></div>
      <div class="contact-card"><div class="label">E-mail</div><a href="mailto:<?= e(cfg('contact.email')) ?>"><?= e(cfg('contact.email')) ?></a></div>
      <?php if (cfg('contact.phone')): ?>
        <div class="contact-card"><div class="label">Telefón</div><a href="tel:<?= e(preg_replace('/\s+/', '', (string) cfg('contact.phone'))) ?>"><?= e(cfg('contact.phone')) ?></a></div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php page_footer();
