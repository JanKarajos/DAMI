<?php
declare(strict_types=1);

/* ================= Dopyty z kontaktného formulára ================= */

/** Ochrana proti spamu: najviac N dopytov za hodinu z jednej IP. Prihlásený admin (testovanie) nie je obmedzený. */
function inquiry_throttled(): bool
{
    if (current_admin()) {
        return false;
    }
    $s = db()->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND area = 'inquiry' AND created_at > ?");
    $s->execute([client_ip(), time() - 3600]);
    return (int) $s->fetchColumn() >= (int) cfg('inquiry_limit_per_hour', 10);
}

function inquiry_find(int $id): ?array
{
    $s = db()->prepare('SELECT * FROM inquiries WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function inquiries_new_count(): int
{
    try {
        return (int) db()->query("SELECT COUNT(*) FROM inquiries WHERE status = 'new'")->fetchColumn();
    } catch (PDOException $ex) {
        return 0;   // menu sa musí zobraziť aj pri probléme s databázou (napr. kvôli Diagnostike)
    }
}

/** Uloží dopyt a odošle e-maily. $therapy môže byť null (všeobecná otázka). */
function create_inquiry(?array $therapy, array $d): array
{
    db()->prepare(
        'INSERT INTO inquiries (therapy_id, therapy_name, client_name, client_email, client_phone, child_name, child_age, message)
         VALUES (?,?,?,?,?,?,?,?)'
    )->execute([
        $therapy['id'] ?? null, $therapy['name'] ?? 'Všeobecná otázka',
        $d['client_name'], $d['client_email'], $d['client_phone'], $d['child_name'], $d['child_age'], $d['message'],
    ]);
    db()->prepare("INSERT INTO login_attempts (ip, area, created_at) VALUES (?, 'inquiry', ?)")->execute([client_ip(), time()]);
    $inq = inquiry_find((int) db()->lastInsertId());
    mail_inquiry_created($inq, $therapy);
    return $inq;
}

function inquiry_details_html(array $q): string
{
    $rows = [
        'Služba'  => $q['therapy_name'],
        'Meno'    => $q['client_name'],
        'Dieťa'   => trim($q['child_name'] . ($q['child_age'] !== '' ? ', vek: ' . $q['child_age'] : ''), ', '),
        'E-mail'  => $q['client_email'],
        'Telefón' => $q['client_phone'],
        'Správa'  => $q['message'],
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

function mail_inquiry_created(array $q, ?array $therapy): void
{
    // Automatická odpoveď klientovi
    $client = mail_layout('Ďakujeme za váš dopyt', '
        <p>Dobrý deň, ' . e($q['client_name']) . ',</p>
        <p>ďakujeme, váš dopyt sme prijali. Ozveme sa vám čo najskôr e-mailom alebo telefonicky a dohodneme ďalší postup.</p>'
        . inquiry_details_html($q) . '
        <p style="font-size:13px;color:#6b7b79">V prípade otázok nás kontaktujte na ' . e(cfg('contact.email'))
        . (cfg('contact.phone') ? ' alebo ' . e(cfg('contact.phone')) : '') . '.</p>
        <p>S pozdravom<br>' . e(cfg('site_name')) . '</p>');
    send_mail($q['client_email'], 'Prijali sme váš dopyt – ' . $q['therapy_name'], $client, (string) cfg('contact.email'));

    // Upozornenie pre centrum a terapeutov danej služby
    $staff = mail_layout('Nový dopyt z webu', '
        <p>Dobrý deň,</p><p>cez kontaktný formulár prišiel nový dopyt:</p>'
        . inquiry_details_html($q)
        . '<p>' . mail_button('mailto:' . $q['client_email'], 'Odpovedať klientovi')
        . mail_button(abs_url('admin/dopyty.php'), 'Otvoriť v administrácii', '#D81B81') . '</p>');
    $subject = 'Nový dopyt: ' . $q['therapy_name'] . ' – ' . $q['client_name'];
    $to = array_filter([(string) cfg('contact.email'), (string) cfg('mail.admin_copy')]);
    if ($therapy) {
        foreach (therapists_for_therapy((int) $therapy['id']) as $t) {
            $to[] = $t['email'];
        }
    }
    foreach (array_unique(array_map('mb_strtolower', $to)) as $addr) {
        send_mail($addr, $subject, $staff, $q['client_email']);
    }
}
