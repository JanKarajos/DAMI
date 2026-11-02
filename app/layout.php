<?php
declare(strict_types=1);

/**
 * Spoločná hlavička. $area: 'public' | 'therapist' | 'admin'
 */
function page_header(string $title, string $area = 'public', string $active = ''): void
{
    $site = cfg('site_name');
    $nav = match ($area) {
        'admin' => current_admin() ? [
            'admin/index.php'      => 'Prehľad',
            'admin/terapeuti.php'  => 'Terapeuti',
            'admin/terapie.php'    => 'Terapie',
            'admin/rezervacie.php' => 'Rezervácie',
            'admin/heslo.php'      => 'Môj účet',
            'admin/odhlasenie.php' => 'Odhlásiť',
        ] : ['index.php' => 'Späť na web'],
        'therapist' => current_therapist() ? [
            'terapeut/index.php'      => 'Moje termíny',
            'terapeut/rezervacie.php' => 'Rezervácie',
            'terapeut/profil.php'     => 'Profil a heslo',
            'terapeut/odhlasenie.php' => 'Odhlásiť',
        ] : ['index.php' => 'Späť na web'],
        default => [
            'index.php#o-nas'   => 'O nás',
            'index.php#terapie' => 'Terapie',
            'index.php#tim'     => 'Náš tím',
            'index.php#kontakt' => 'Kontakt',
            'rezervacia.php'    => 'Rezervácia',
        ],
    };
    $badge = match ($area) {
        'admin' => 'Administrácia',
        'therapist' => 'Portál terapeuta',
        default => '',
    };
    ?>
<!doctype html>
<html lang="sk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | <?= e($site) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>?v=1">
<link rel="icon" href="<?= e(url('assets/logo.svg')) ?>" type="image/svg+xml">
</head>
<body class="area-<?= e($area) ?>">
<div class="topbar">
  <div class="container topbar-inner">
    <span>📍 <?= e(cfg('contact.address')) ?></span>
    <span>✉️ <a href="mailto:<?= e(cfg('contact.email')) ?>"><?= e(cfg('contact.email')) ?></a><?php if (cfg('contact.phone')): ?> &nbsp; ☎ <?= e(cfg('contact.phone')) ?><?php endif; ?></span>
  </div>
</div>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?= e(url($area === 'public' ? 'index.php' : ($area === 'admin' ? 'admin/index.php' : 'terapeut/index.php'))) ?>">
      <img src="<?= e(url('assets/logo.svg')) ?>" alt="" width="52" height="52">
      <span class="brand-text">
        <strong><?= e(cfg('site_short')) ?></strong>
        <small><?= $badge ? e($badge) : 'Občianske združenie' ?></small>
      </span>
    </a>
    <button class="nav-toggle" aria-label="Menu" onclick="document.body.classList.toggle('nav-open')">☰</button>
    <nav class="main-nav">
      <?php foreach ($nav as $href => $label): ?>
        <a href="<?= e(url($href)) ?>" class="<?= $active === $href ? 'active' : '' ?><?= $href === 'rezervacia.php' ? ' btn btn-accent' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>
<main>
<?php
    $flashes = take_flashes();
    if ($flashes): ?>
  <div class="container flashes">
    <?php foreach ($flashes as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif;
}

function page_footer(): void
{
    ?>
</main>
<footer class="site-footer" id="kontakt">
  <div class="container footer-grid">
    <div>
      <h4><?= e(cfg('site_name')) ?></h4>
      <p>Mimovládne, nezisková a nepolitická organizácia pomáhajúca deťom so zdravotným znevýhodnením a ich rodinám.</p>
    </div>
    <div>
      <h4>Kontakt</h4>
      <p><?= e(cfg('contact.address')) ?><br>
      <a href="mailto:<?= e(cfg('contact.email')) ?>"><?= e(cfg('contact.email')) ?></a>
      <?php if (cfg('contact.phone')): ?><br><?= e(cfg('contact.phone')) ?><?php endif; ?></p>
    </div>
    <div>
      <h4>Odkazy</h4>
      <p><a href="<?= e(url('rezervacia.php')) ?>">Online rezervácia</a><br>
      <a href="<?= e(url('terapeut/prihlasenie.php')) ?>">Prihlásenie pre terapeutov</a><br>
      <a href="<?= e(url('admin/prihlasenie.php')) ?>">Administrácia</a></p>
    </div>
  </div>
  <div class="footer-bottom">© <?= date('Y') ?> <?= e(cfg('site_name')) ?></div>
</footer>
</body>
</html>
<?php
}
