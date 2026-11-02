<?php
declare(strict_types=1);

/* ================= Čítanie dát ================= */

function therapies_all(bool $onlyActive = true): array
{
    $sql = 'SELECT * FROM therapies' . ($onlyActive ? ' WHERE active = 1' : '') . ' ORDER BY sort_order, name';
    return db()->query($sql)->fetchAll();
}

const THERAPY_KINDS = ['therapy' => 'Terapia', 'diagnostic' => 'Diagnostika'];
const BOOKING_MODES = ['online' => 'Online rezervácia v kalendári', 'inquiry' => 'Iba kontaktný formulár'];

function therapy_is_online(array $t): bool
{
    return ($t['booking_mode'] ?? 'online') === 'online';
}

/** Rozdelí služby na ['therapy' => [...], 'diagnostic' => [...]]. */
function therapies_grouped(array $list): array
{
    $out = array_fill_keys(array_keys(THERAPY_KINDS), []);
    foreach ($list as $t) {
        $out[isset(THERAPY_KINDS[$t['kind'] ?? '']) ? $t['kind'] : 'therapy'][] = $t;
    }
    return $out;
}

function therapy_find(int $id): ?array
{
    $s = db()->prepare('SELECT * FROM therapies WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function therapist_find(int $id): ?array
{
    $s = db()->prepare('SELECT * FROM therapists WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function therapists_all(bool $onlyActive = true): array
{
    $sql = 'SELECT * FROM therapists' . ($onlyActive ? ' WHERE active = 1' : '') . ' ORDER BY sort_order, last_name, first_name';
    return db()->query($sql)->fetchAll();
}

/** Aktívni terapeuti vykonávajúci danú terapiu. */
function therapists_for_therapy(int $therapyId): array
{
    $s = db()->prepare(
        'SELECT t.* FROM therapists t
         JOIN therapist_therapy tt ON tt.therapist_id = t.id
         WHERE tt.therapy_id = ? AND t.active = 1
         ORDER BY t.sort_order, t.last_name, t.first_name'
    );
    $s->execute([$therapyId]);
    return $s->fetchAll();
}

/** [therapist_id => [therapy, ...]] */
function therapies_by_therapist(bool $onlyActive = true): array
{
    $sql = 'SELECT tt.therapist_id, th.* FROM therapist_therapy tt JOIN therapies th ON th.id = tt.therapy_id'
        . ($onlyActive ? ' WHERE th.active = 1' : '') . ' ORDER BY th.sort_order, th.name';
    $out = [];
    foreach (db()->query($sql) as $row) {
        $out[(int) $row['therapist_id']][] = $row;
    }
    return $out;
}

function therapy_ids_of_therapist(int $therapistId): array
{
    $s = db()->prepare('SELECT therapy_id FROM therapist_therapy WHERE therapist_id = ?');
    $s->execute([$therapistId]);
    return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}

function therapist_does_therapy(int $therapistId, int $therapyId): bool
{
    $s = db()->prepare("SELECT 1 FROM therapist_therapy tt JOIN therapies th ON th.id = tt.therapy_id
                        WHERE tt.therapist_id = ? AND tt.therapy_id = ? AND th.active = 1 AND th.booking_mode = 'online'");
    $s->execute([$therapistId, $therapyId]);
    return (bool) $s->fetchColumn();
}

function earliest_bookable(): string
{
    return date('Y-m-d H:i', time() + (int) cfg('min_booking_notice_hours') * 3600);
}

/**
 * Voľné sloty terapeuta pre danú terapiu v intervale [from, to) (dátumy 'Y-m-d').
 */
function free_slots(int $therapistId, int $therapyId, string $from, string $to): array
{
    $horizon = date('Y-m-d', strtotime('+' . (int) cfg('booking_horizon_days') . ' days'));
    if ($to > $horizon) {
        $to = $horizon;
    }
    $s = db()->prepare(
        "SELECT s.id, s.starts_at, s.ends_at FROM slots s
         WHERE s.therapist_id = :tid
           AND (s.therapy_id IS NULL OR s.therapy_id = :thid)
           AND s.starts_at >= :earliest
           AND s.starts_at >= :from AND s.starts_at < :to
           AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.slot_id = s.id AND b.status = 'confirmed')
         ORDER BY s.starts_at"
    );
    $s->execute([
        ':tid' => $therapistId, ':thid' => $therapyId,
        ':earliest' => earliest_bookable(), ':from' => $from, ':to' => $to,
    ]);
    return $s->fetchAll();
}

function booking_find_by_token(string $token): ?array
{
    $s = db()->prepare(
        'SELECT b.*, t.title, t.title_after, t.first_name, t.last_name, t.email AS therapist_email, t.phone AS therapist_phone
         FROM bookings b JOIN therapists t ON t.id = b.therapist_id WHERE b.token = ?'
    );
    $s->execute([$token]);
    return $s->fetch() ?: null;
}

function booking_find(int $id): ?array
{
    $s = db()->prepare(
        'SELECT b.*, t.title, t.title_after, t.first_name, t.last_name, t.email AS therapist_email, t.phone AS therapist_phone
         FROM bookings b JOIN therapists t ON t.id = b.therapist_id WHERE b.id = ?'
    );
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

/* ================= Rezervácia ================= */

/**
 * Vytvorí rezerváciu. Vracia [booking|null, chyba|null].
 */
function create_booking(int $slotId, int $therapyId, array $d): array
{
    $pdo = db();
    $therapy = therapy_find($therapyId);
    if (!$therapy || !(int) $therapy['active']) {
        return [null, 'Zvolená terapia neexistuje.'];
    }
    if (!therapy_is_online($therapy)) {
        return [null, 'Na túto službu sa nedá objednať online. Pošlite nám prosím dopyt cez kontaktný formulár.'];
    }
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $s = $pdo->prepare('SELECT * FROM slots WHERE id = ?');
        $s->execute([$slotId]);
        $slot = $s->fetch();
        if (!$slot) {
            throw new DomainException('Zvolený termín už neexistuje.');
        }
        if ($slot['therapy_id'] !== null && (int) $slot['therapy_id'] !== $therapyId) {
            throw new DomainException('Tento termín nie je určený pre zvolenú terapiu.');
        }
        if (!therapist_does_therapy((int) $slot['therapist_id'], $therapyId)) {
            throw new DomainException('Terapeut túto terapiu nevykonáva.');
        }
        if ($slot['starts_at'] < earliest_bookable()) {
            throw new DomainException('Na tento termín sa už nie je možné objednať.');
        }
        $s = $pdo->prepare("SELECT 1 FROM bookings WHERE slot_id = ? AND status = 'confirmed'");
        $s->execute([$slotId]);
        if ($s->fetchColumn()) {
            throw new DomainException('Ľutujeme, termín si medzitým rezervoval niekto iný. Vyberte si prosím iný čas.');
        }
        $token = bin2hex(random_bytes(20));
        $pdo->prepare(
            'INSERT INTO bookings (slot_id, therapist_id, therapy_id, therapy_name, starts_at, ends_at,
                client_name, client_email, client_phone, child_name, note, token)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $slotId, $slot['therapist_id'], $therapyId, $therapy['name'], $slot['starts_at'], $slot['ends_at'],
            $d['client_name'], $d['client_email'], $d['client_phone'], $d['child_name'], $d['note'], $token,
        ]);
        $id = (int) $pdo->lastInsertId();
        $pdo->exec('COMMIT');
    } catch (DomainException $ex) {
        $pdo->exec('ROLLBACK');
        return [null, $ex->getMessage()];
    } catch (Throwable $ex) {
        $pdo->exec('ROLLBACK');
        throw $ex;
    }

    $booking = booking_find($id);
    mail_booking_created($booking);
    return [$booking, null];
}

function cancel_booking(array $booking, string $by, bool $notify = true): void
{
    db()->prepare("UPDATE bookings SET status = 'cancelled', cancelled_by = ? WHERE id = ? AND status = 'confirmed'")
        ->execute([$by, $booking['id']]);
    if ($notify) {
        mail_booking_cancelled($booking, $by);
    }
}

function can_client_cancel(array $booking): bool
{
    return $booking['status'] === 'confirmed'
        && strtotime($booking['starts_at']) - time() >= (int) cfg('min_cancel_notice_hours') * 3600;
}

/* ================= E-maily ================= */

function booking_details_html(array $b): string
{
    $rows = [
        'Terapia'  => $b['therapy_name'],
        'Terapeut' => therapist_full_name($b),
        'Termín'   => fmt_datetime($b['starts_at']) . ' – ' . fmt_time($b['ends_at']),
        'Miesto'   => cfg('contact.address'),
        'Klient'   => $b['client_name'] . ($b['child_name'] ? ' (dieťa: ' . $b['child_name'] . ')' : ''),
        'Telefón'  => $b['client_phone'],
        'E-mail'   => $b['client_email'],
        'Poznámka' => $b['note'],
    ];
    $html = '<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:14px;margin:12px 0">';
    foreach ($rows as $k => $v) {
        if ($v === '' || $v === null) {
            continue;
        }
        $html .= '<tr><td style="border-bottom:1px solid #e5f0f7;color:#5f7283;width:120px">' . e($k)
            . '</td><td style="border-bottom:1px solid #e5f0f7"><strong>' . nl2br(e($v)) . '</strong></td></tr>';
    }
    return $html . '</table>';
}

function google_calendar_link(array $b): string
{
    $fmt = fn(string $dt) => (new DateTimeImmutable($dt))->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    return 'https://calendar.google.com/calendar/render?' . http_build_query([
        'action'   => 'TEMPLATE',
        'text'     => $b['therapy_name'] . ' – ' . cfg('site_short'),
        'dates'    => $fmt($b['starts_at']) . '/' . $fmt($b['ends_at']),
        'details'  => 'Terapeut: ' . therapist_full_name($b),
        'location' => cfg('contact.address'),
    ]);
}

function mail_button(string $href, string $label, string $color = '#0D6FB0'): string
{
    return '<a href="' . e($href) . '" style="display:inline-block;background:' . $color
        . ';color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:24px;font-weight:bold;margin:4px 6px 4px 0">'
        . e($label) . '</a>';
}

function mail_booking_created(array $b): void
{
    $manage = abs_url('rezervacia-detail.php?t=' . $b['token']);
    $cancelInfo = (int) cfg('min_cancel_notice_hours');
    $client = mail_layout('Rezervácia potvrdená', '
        <p>Dobrý deň, ' . e($b['client_name']) . ',</p>
        <p>ďakujeme za rezerváciu. Váš termín je <strong>záväzne zarezervovaný</strong>:</p>'
        . booking_details_html($b) . '
        <p>' . (int) cfg('reminder_hours') . ' hodín pred termínom vám pošleme pripomienku.</p>
        <p>' . mail_button(google_calendar_link($b), 'Pridať do kalendára')
        . mail_button($manage, 'Zobraziť / zrušiť rezerváciu', '#D81B81') . '</p>
        <p style="font-size:13px;color:#6b7b79">Termín môžete bezplatne zrušiť najneskôr ' . $cancelInfo
        . ' hodín vopred. V prípade otázok kontaktujte terapeuta: ' . e($b['therapist_email'])
        . ($b['therapist_phone'] ? ', ' . e($b['therapist_phone']) : '') . '.</p>
        <p>Tešíme sa na vás.<br>' . e(cfg('site_name')) . '</p>');
    send_mail($b['client_email'], 'Potvrdenie rezervácie – ' . $b['therapy_name'] . ', ' . fmt_datetime($b['starts_at']), $client, $b['therapist_email']);

    $staff = mail_layout('Nová rezervácia', '
        <p>Dobrý deň,</p><p>v rezervačnom systéme pribudla nová rezervácia:</p>'
        . booking_details_html($b)
        . '<p>' . mail_button(abs_url('terapeut/rezervacie.php'), 'Otvoriť moje rezervácie') . '</p>');
    $subject = 'Nová rezervácia: ' . $b['therapy_name'] . ', ' . fmt_datetime($b['starts_at']);
    send_mail($b['therapist_email'], $subject, $staff, $b['client_email']);
    if (cfg('mail.admin_copy')) {
        send_mail((string) cfg('mail.admin_copy'), '[kópia] ' . $subject, $staff, $b['client_email']);
    }
}

function mail_booking_cancelled(array $b, string $by): void
{
    $who = ['client' => 'klient', 'therapist' => 'terapeut', 'admin' => 'administrátor'][$by] ?? $by;
    $client = mail_layout('Rezervácia zrušená', '
        <p>Dobrý deň, ' . e($b['client_name']) . ',</p>
        <p>nasledujúca rezervácia bola zrušená (zrušil: ' . e($who) . '):</p>'
        . booking_details_html($b)
        . '<p>Nový termín si môžete kedykoľvek zarezervovať online.</p><p>'
        . mail_button(abs_url('rezervacia.php'), 'Rezervovať nový termín') . '</p>');
    send_mail($b['client_email'], 'Zrušenie rezervácie – ' . fmt_datetime($b['starts_at']), $client, $b['therapist_email']);

    $staff = mail_layout('Rezervácia zrušená', '<p>Rezervácia bola zrušená (zrušil: ' . e($who) . '). Termín je opäť voľný.</p>'
        . booking_details_html($b));
    send_mail($b['therapist_email'], 'Zrušená rezervácia: ' . fmt_datetime($b['starts_at']), $staff);
}

function mail_reminder(array $b): bool
{
    $html = mail_layout('Pripomienka termínu', '
        <p>Dobrý deň, ' . e($b['client_name']) . ',</p>
        <p>pripomíname vám blížiaci sa termín:</p>'
        . booking_details_html($b) . '
        <p>Ak sa nemôžete dostaviť, dajte nám prosím čo najskôr vedieť.</p>
        <p>' . mail_button(abs_url('rezervacia-detail.php?t=' . $b['token']), 'Detail rezervácie') . '</p>
        <p>Tešíme sa na vás.<br>' . e(cfg('site_name')) . '</p>');
    return send_mail($b['client_email'], 'Pripomienka: ' . $b['therapy_name'] . ' – ' . fmt_datetime($b['starts_at']), $html, $b['therapist_email']);
}

/** Odošle pripomienky pre termíny v najbližších X hodinách. Vracia počet odoslaných. */
function send_due_reminders(): int
{
    $now = date('Y-m-d H:i');
    $until = date('Y-m-d H:i', time() + (int) cfg('reminder_hours') * 3600);
    $s = db()->prepare(
        "SELECT b.id FROM bookings b WHERE b.status = 'confirmed' AND b.reminder_sent_at IS NULL
         AND b.starts_at > ? AND b.starts_at <= ?"
    );
    $s->execute([$now, $until]);
    $sent = 0;
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $id) {
        // Označíme vopred, aby pri súbežnom behu nedošlo k dvojitému odoslaniu.
        $u = db()->prepare("UPDATE bookings SET reminder_sent_at = ? WHERE id = ? AND reminder_sent_at IS NULL");
        $u->execute([date('Y-m-d H:i:s'), $id]);
        if ($u->rowCount() !== 1) {
            continue;
        }
        $b = booking_find((int) $id);
        // Ak bola rezervácia vytvorená tesne pred termínom, potvrdenie stačí – pripomienku neposielame.
        if (strtotime($b['starts_at']) - strtotime($b['created_at']) < 2 * 3600) {
            continue;
        }
        if (mail_reminder($b)) {
            $sent++;
        } else {
            db()->prepare('UPDATE bookings SET reminder_sent_at = NULL WHERE id = ?')->execute([$id]);
        }
    }
    return $sent;
}

function maybe_run_pseudo_cron(): void
{
    try {
        $last = (int) setting_get('pseudo_cron_last', '0');
        if (time() - $last < 600) {
            return;
        }
        setting_set('pseudo_cron_last', (string) time());
        send_due_reminders();
    } catch (Throwable $ex) {
        error_log('[DAMI cron] ' . $ex->getMessage());
    }
}

/* ================= Správa slotov (terapeut) ================= */

/** Pridá voľný slot. Vracia null pri úspechu, inak text chyby. */
function slot_add(int $therapistId, string $start, string $end, ?int $therapyId, string $note = ''): ?string
{
    if ($end <= $start) {
        return 'Koniec musí byť po začiatku.';
    }
    if ($start < date('Y-m-d H:i')) {
        return 'Termín ' . fmt_datetime($start) . ' je v minulosti.';
    }
    $s = db()->prepare('SELECT starts_at FROM slots WHERE therapist_id = ? AND starts_at < ? AND ends_at > ?');
    $s->execute([$therapistId, $end, $start]);
    if ($s->fetchColumn()) {
        return 'Termín ' . fmt_datetime($start) . ' sa prekrýva s existujúcim termínom.';
    }
    db()->prepare('INSERT INTO slots (therapist_id, therapy_id, starts_at, ends_at, note) VALUES (?,?,?,?,?)')
        ->execute([$therapistId, $therapyId ?: null, $start, $end, $note]);
    return null;
}

/** Zmaže slot terapeuta, len ak nemá aktívnu rezerváciu. */
function slot_delete(int $therapistId, int $slotId): bool
{
    $s = db()->prepare(
        "DELETE FROM slots WHERE id = ? AND therapist_id = ?
         AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.slot_id = slots.id AND b.status = 'confirmed')"
    );
    $s->execute([$slotId, $therapistId]);
    return $s->rowCount() === 1;
}

/** Sloty terapeuta vrátane info o rezervácii. */
function therapist_slots(int $therapistId, string $from, string $to): array
{
    $s = db()->prepare(
        "SELECT s.*, th.name AS therapy_only,
                b.id AS booking_id, b.client_name, b.child_name, b.client_phone, b.client_email, b.therapy_name, b.token
         FROM slots s
         LEFT JOIN therapies th ON th.id = s.therapy_id
         LEFT JOIN bookings b ON b.slot_id = s.id AND b.status = 'confirmed'
         WHERE s.therapist_id = ? AND s.starts_at >= ? AND s.starts_at < ?
         ORDER BY s.starts_at"
    );
    $s->execute([$therapistId, $from, $to]);
    return $s->fetchAll();
}
