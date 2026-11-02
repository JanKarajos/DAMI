<?php
require __DIR__ . '/app/bootstrap.php';

$therapies = therapies_all();
$data = ['therapies' => [], 'therapists' => []];
foreach ($therapies as $t) {
    $ids = [];
    foreach (therapists_for_therapy((int) $t['id']) as $p) {
        $ids[] = (int) $p['id'];
        $data['therapists'][$p['id']] ??= [
            'id' => (int) $p['id'],
            'name' => therapist_full_name($p),
            'initials' => mb_substr($p['first_name'], 0, 1) . mb_substr($p['last_name'], 0, 1),
            'position' => $p['position'],
            'description' => $p['description'],
            'photo' => therapist_photo_url($p),
        ];
    }
    $data['therapies'][] = [
        'id' => (int) $t['id'],
        'name' => $t['name'],
        'description' => $t['description'],
        'duration' => (int) $t['duration_min'],
        'price' => $t['price'],
        'therapists' => $ids,
    ];
}
$data['preselect'] = (int) ($_GET['terapia'] ?? 0);
$data['csrf'] = csrf_token();
$data['api'] = url('api.php');
$data['horizonDays'] = (int) cfg('booking_horizon_days');

page_header('Rezervácia termínu', 'public', 'rezervacia.php');
?>
<section class="page-hero">
  <div class="container">
    <h1>Rezervácia termínu</h1>
    <p class="muted mt-0">Vyberte si terapiu, terapeuta a voľný čas v kalendári.</p>
  </div>
</section>

<section class="section" style="padding-top:32px">
  <div class="container" id="booking">
    <ol class="steps">
      <li data-step="1"><span>1</span> Terapia</li>
      <li data-step="2"><span>2</span> Terapeut</li>
      <li data-step="3"><span>3</span> Termín</li>
      <li data-step="4"><span>4</span> Údaje</li>
    </ol>

    <!-- Krok 1 -->
    <div class="step-panel" data-panel="1">
      <h2>Akú terapiu si želáte?</h2>
      <div class="choice-grid" id="therapy-list"></div>
    </div>

    <!-- Krok 2 -->
    <div class="step-panel hidden" data-panel="2">
      <button class="btn-link back-link" data-back="1">← späť na výber terapie</button>
      <h2>Vyberte terapeuta</h2>
      <p class="muted" id="therapist-intro"></p>
      <div class="choice-grid" id="therapist-list"></div>
    </div>

    <!-- Krok 3 -->
    <div class="step-panel hidden" data-panel="3">
      <button class="btn-link back-link" data-back="2">← späť na výber terapeuta</button>
      <div class="summary" id="summary-3"></div>
      <div class="booking-layout">
        <div class="calendar">
          <div class="cal-head">
            <button class="cal-nav" id="cal-prev" aria-label="Predchádzajúci mesiac">‹</button>
            <h3 id="cal-title"></h3>
            <button class="cal-nav" id="cal-next" aria-label="Nasledujúci mesiac">›</button>
          </div>
          <div class="cal-grid" id="cal-grid"></div>
          <div class="cal-legend"><span><i></i>voľné termíny</span></div>
        </div>
        <div class="card">
          <h3 id="times-title">Vyberte deň v kalendári</h3>
          <div id="times" class="times"></div>
          <p id="times-empty" class="muted small"></p>
        </div>
      </div>
    </div>

    <!-- Krok 4 -->
    <div class="step-panel hidden" data-panel="4">
      <button class="btn-link back-link" data-back="3">← späť na výber termínu</button>
      <div class="booking-layout">
        <form id="booking-form" class="card" novalidate>
          <h2>Kontaktné údaje</h2>
          <div class="form-grid">
            <div class="form-row">
              <label class="required" for="client_name">Meno a priezvisko</label>
              <input type="text" id="client_name" name="client_name" required maxlength="120" autocomplete="name">
            </div>
            <div class="form-row">
              <label for="child_name">Meno dieťaťa</label>
              <input type="text" id="child_name" name="child_name" maxlength="120">
            </div>
            <div class="form-row">
              <label class="required" for="client_email">E-mail</label>
              <input type="email" id="client_email" name="client_email" required maxlength="160" autocomplete="email">
            </div>
            <div class="form-row">
              <label class="required" for="client_phone">Telefón</label>
              <input type="tel" id="client_phone" name="client_phone" required maxlength="40" autocomplete="tel">
            </div>
          </div>
          <div class="form-row">
            <label for="note">Poznámka pre terapeuta</label>
            <textarea id="note" name="note" maxlength="1000"></textarea>
          </div>
          <div class="form-row" style="position:absolute;left:-9999px" aria-hidden="true">
            <label for="website">Nevypĺňajte</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
          </div>
          <div class="form-row">
            <label class="check"><input type="checkbox" name="gdpr" id="gdpr" required> Súhlasím so spracovaním osobných údajov za účelom rezervácie termínu.</label>
          </div>
          <div id="form-error" class="alert alert-error hidden"></div>
          <button type="submit" class="btn btn-accent" id="submit-btn">Záväzne rezervovať</button>
        </form>
        <div>
          <div class="summary" id="summary-4"></div>
          <p class="small muted">Po odoslaní vám príde potvrdenie e-mailom a deň pred termínom aj pripomienka. Rezerváciu môžete zrušiť cez odkaz v e-maile.</p>
        </div>
      </div>
    </div>
  </div>
</section>
<script>window.BOOKING = <?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(url('assets/booking.js')) ?>?v=1"></script>
<?php page_footer();
