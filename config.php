<?php
/**
 * Základná konfigurácia. Hodnoty NEMEŇTE tu – vytvorte súbor config.local.php
 * (viď config.local.example.php), ktorý tieto hodnoty prepíše.
 */
$config = [
    'site_name'    => 'Občianske združenie DAMI',
    'site_short'   => 'DAMI',
    'base_url'     => 'http://localhost:8000',   // bez lomky na konci – použije sa v e-mailoch
    'timezone'     => 'Europe/Bratislava',
    'db_path'      => __DIR__ . '/data/dami.sqlite',

    'main_site_url' => 'http://www.ozdami.sk/',
    'registration'  => 'Registrované na Ministerstve vnútra Slovenskej republiky, 12.12. 2008, Číslo spisu: VV3/1 – 900/90 – 33110',

    'contact' => [
        'address' => 'Čajkovského 17, 080 05 Prešov – Solivar',
        'email'   => 'oz.dami9@gmail.com',
        'phone'   => '0907 216 194',
    ],

    // Prvé spustenie vytvorí admin účet s týmito údajmi (heslo je nutné hneď zmeniť).
    'admin_default_user' => 'admin',
    'admin_default_pass' => 'admin123',

    // Pripomienka termínu: koľko hodín vopred sa odošle e-mail.
    'reminder_hours' => 24,
    // Najkratší čas (v hodinách) pred termínom, kedy je ešte možné rezervovať / zrušiť.
    'min_booking_notice_hours' => 12,
    'min_cancel_notice_hours'  => 24,
    // Na koľko dní dopredu sa zobrazujú voľné termíny.
    'booking_horizon_days' => 120,

    // Odosielanie e-mailov: 'log' (zapisuje do data/mail.log – na testovanie), 'mail' (PHP mail()), 'smtp'
    'mail' => [
        'transport'  => 'log',
        'from_email' => 'noreply@ozdami.sk',
        'from_name'  => 'OZ DAMI – rezervácie',
        'admin_copy' => '',            // voliteľne e-mail, kam chodí kópia každej rezervácie
        'smtp' => [
            'host'       => 'smtp.example.com',
            'port'       => 587,
            'encryption' => 'tls',     // 'tls' (STARTTLS), 'ssl' alebo ''
            'username'   => '',
            'password'   => '',
        ],
    ],

    // Reverzné proxy, ktorým veríme hlavičky X-Forwarded-For / X-Forwarded-Proto
    // (Synology reverzné proxy beží na tom istom NAS, Docker používa siete 172.16.0.0/12).
    'trusted_proxies' => ['127.0.0.1', '::1', '172.16.0.0/12'],

    // Pripomienky sa odosielajú cez cron (cron/pripomienky.php). Ak cron nemáte,
    // nechajte true – kontrola sa spustí pri návšteve stránky (max. raz za 10 min).
    'pseudo_cron' => true,
];

if (is_file(__DIR__ . '/config.local.php')) {
    $local = require __DIR__ . '/config.local.php';
    if (is_array($local)) {
        $config = array_replace_recursive($config, $local);
    }
}
return $config;
