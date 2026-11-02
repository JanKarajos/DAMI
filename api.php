<?php
require __DIR__ . '/app/bootstrap.php';

$action = $_GET['action'] ?? '';

if ($action === 'slots') {
    $therapistId = (int) ($_GET['therapist'] ?? 0);
    $therapyId = (int) ($_GET['therapy'] ?? 0);
    $month = (string) ($_GET['month'] ?? date('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $month) || !therapist_does_therapy($therapistId, $therapyId)) {
        json_out(['error' => 'Neplatná požiadavka.'], 400);
    }
    $from = $month . '-01';
    $to = date('Y-m-d', strtotime($from . ' +1 month'));
    $days = [];
    foreach (free_slots($therapistId, $therapyId, $from, $to) as $s) {
        $days[substr($s['starts_at'], 0, 10)][] = [
            'id' => (int) $s['id'],
            'start' => substr($s['starts_at'], 11, 5),
            'end' => substr($s['ends_at'], 11, 5),
        ];
    }
    // Najbližší mesiac s voľným termínom (na rýchly skok v kalendári)
    $s = db()->prepare(
        "SELECT MIN(s.starts_at) FROM slots s
         WHERE s.therapist_id = ? AND (s.therapy_id IS NULL OR s.therapy_id = ?) AND s.starts_at >= ? AND s.starts_at < ?
           AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.slot_id = s.id AND b.status = 'confirmed')"
    );
    $s->execute([$therapistId, $therapyId, earliest_bookable(), date('Y-m-d', strtotime('+' . (int) cfg('booking_horizon_days') . ' days'))]);
    $next = $s->fetchColumn();
    json_out(['days' => $days, 'firstAvailable' => $next ? substr($next, 0, 10) : null]);
}

if ($action === 'book' && is_post()) {
    csrf_check();
    if (post('website') !== '') {           // honeypot proti spamu
        json_out(['error' => 'Rezerváciu sa nepodarilo odoslať.'], 400);
    }
    $d = [
        'client_name'  => mb_substr(post('client_name'), 0, 120),
        'client_email' => mb_substr(post('client_email'), 0, 160),
        'client_phone' => mb_substr(post('client_phone'), 0, 40),
        'child_name'   => mb_substr(post('child_name'), 0, 120),
        'note'         => mb_substr(post('note'), 0, 1000),
    ];
    $errors = [];
    if (mb_strlen($d['client_name']) < 3) $errors[] = 'Vyplňte meno a priezvisko.';
    if (!filter_var($d['client_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Zadajte platný e-mail.';
    if (!preg_match('/^[0-9 +()\/-]{6,}$/', $d['client_phone'])) $errors[] = 'Zadajte platné telefónne číslo.';
    if (post('gdpr') === '') $errors[] = 'Je potrebný súhlas so spracovaním osobných údajov.';
    if ($errors) {
        json_out(['error' => implode(' ', $errors)], 422);
    }
    [$booking, $err] = create_booking((int) post('slot_id'), (int) post('therapy_id'), $d);
    if ($err) {
        json_out(['error' => $err, 'slotTaken' => true], 409);
    }
    json_out(['redirect' => url('rezervacia-detail.php?t=' . $booking['token'] . '&nova=1')]);
}

json_out(['error' => 'Neznáma akcia.'], 404);
