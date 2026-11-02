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
            'admin/terapie.php'    => 'Služby',
            'admin/rezervacie.php' => 'Rezervácie',
            'admin/dopyty.php'     => 'Dopyty' . (($n = inquiries_new_count()) ? " ($n)" : ''),
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
            'index.php#uvod'     => 'Úvod',
            'index.php#sluzby'   => 'Terapie',
            'index.php#diagnostika' => 'Diagnostika',
            'index.php#personal' => 'Personál',
            'index.php#kontakty' => 'Kontakty',
            'rezervacia.php'     => 'Rezervovať termín',
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
<link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>?v=3">
<link rel="icon" type="image/png" href="<?= e(url('assets/favicon.png')) ?>">
</head>
<body class="area-<?= e($area) ?>">
<header class="site-header">
  <div class="nav-shell">
    <a class="brand" href="<?= e(url($area === 'public' ? 'index.php' : ($area === 'admin' ? 'admin/index.php' : 'terapeut/index.php'))) ?>">
      <img src="<?= e(url('assets/logo.png')) ?>" alt="DAMI občianske združenie" width="146" height="43">
      <?php if ($badge): ?><span class="brand-badge"><?= e($badge) ?></span><?php endif; ?>
    </a>
    <button class="nav-toggle" aria-label="Menu" onclick="document.body.classList.toggle('nav-open')"><span></span><span></span><span></span></button>
    <nav class="main-nav">
      <?php foreach ($nav as $href => $label):
          $cta = in_array($href, ['rezervacia.php'], true); ?>
        <a href="<?= e(url($href)) ?>" class="<?= $active === $href ? 'active' : '' ?><?= $cta ? ' nav-cta' : '' ?>" onclick="document.body.classList.remove('nav-open')"><?= e($label) ?><?= $cta ? ' <span>↘</span>' : '' ?></a>
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
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <p class="footer-brand"><?= e(cfg('site_short')) ?></p>
        <p>Centrum pre deti so špeciálnymi potrebami</p>
      </div>
      <div>
        <h4>Kontakt</h4>
        <p><?= e(cfg('contact.address')) ?><br>
        <a href="mailto:<?= e(cfg('contact.email')) ?>"><?= e(cfg('contact.email')) ?></a>
        <?php if (cfg('contact.phone')): ?><br><a href="tel:<?= e(preg_replace('/\s+/', '', (string) cfg('contact.phone'))) ?>"><?= e(cfg('contact.phone')) ?></a><?php endif; ?></p>
      </div>
      <div>
        <h4>Odkazy</h4>
        <p><a href="<?= e(cfg('main_site_url')) ?>">ozdami.sk</a><br>
        <a href="<?= e(url('rezervacia.php')) ?>">Online rezervácia</a><br>
        <a href="<?= e(url('kontakt.php')) ?>">Kontaktný formulár</a><br>
        <a href="<?= e(url('terapeut/prihlasenie.php')) ?>">Prihlásenie pre terapeutov</a><br>
        <a href="<?= e(url('admin/prihlasenie.php')) ?>">Administrácia</a></p>
      </div>
    </div>
    <div class="footer-bottom">© <?= date('Y') ?> <?= e(cfg('site_short')) ?> · <?= e(cfg('registration')) ?></div>
  </div>
</footer>
</body>
</html>
<?php
}
