<?php

const MAIL_LOGO_CID = 'logo@dami';

/**
 * Jednoduché odosielanie e-mailov (HTML + text) bez externých knižníc.
 * Transport: 'log' | 'mail' | 'smtp' (config mail.transport).
 */
function send_mail($to, $subject, $html, $replyTo = null)
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || is_placeholder_email($to)) {
        $GLOBALS['mail_last_error'] = 'Neplatná alebo zástupná e-mailová adresa: ' . $to;
        mail_history_add((string) cfg('mail.transport'), $to, $subject, false, 'preskočené – neplatná alebo zástupná adresa');
        return false;
    }
    $fromEmail = (string) cfg('mail.from_email');
    $fromName  = (string) cfg('mail.from_name');
    $text = mail_html_to_text($html);
    $boundary = 'b_' . bin2hex(random_bytes(12));
    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $headers = [
        'Date: ' . date('r'),
        'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>',
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . (strpos($fromEmail, '@') !== false ? substr(strrchr($fromEmail, '@'), 1) : 'localhost') . '>',
        'MIME-Version: 1.0',
    ];
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    $alternative = "--$boundary\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text))
        . "--$boundary\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html))
        . "--$boundary--\r\n";
    // Logo je priamo v e-maile (príloha cid:), nie odkaz na web – zobrazí sa vždy, aj keď web nebeží cez https.
    $logoFile = APP_ROOT . '/assets/logo-mail.png';
    if (strpos($html, 'cid:' . MAIL_LOGO_CID) !== false && is_file($logoFile)) {
        $related = 'r_' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/related; type="multipart/alternative"; boundary="' . $related . '"';
        $body = "--$related\r\n"
            . 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n\r\n"
            . $alternative
            . "--$related\r\n"
            . "Content-Type: image/png; name=\"logo.png\"\r\nContent-Transfer-Encoding: base64\r\n"
            . 'Content-ID: <' . MAIL_LOGO_CID . ">\r\nContent-Disposition: inline; filename=\"logo.png\"\r\n\r\n"
            . chunk_split(base64_encode((string) file_get_contents($logoFile)))
            . "--$related--\r\n";
    } else {
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $body = $alternative;
    }

    $transport = (string) cfg('mail.transport');
    $error = null;
    try {
        switch ($transport) {
            case 'smtp':
                $ok = smtp_send($fromEmail, $to, "To: $to\r\nSubject: $encSubject\r\n" . implode("\r\n", $headers), $body);
                break;
            case 'mail':
                $ok = mail($to, $encSubject, $body, implode("\r\n", $headers), '-f' . $fromEmail);
                if (!$ok) {
                    $error = 'PHP mail() zlyhalo – server nemá poštový program, použite transport smtp.';
                }
                break;
            default:
                $log = sprintf("==== %s ====\nTo: %s\nSubject: %s\n\n%s\n\n", date('Y-m-d H:i:s'), $to, $subject, $text);
                $ok = file_put_contents(dirname((string) cfg('db_path')) . '/mail.log', $log, FILE_APPEND) !== false;
        }
    } catch (Exception $ex) {
        $ok = false;
        $error = $ex->getMessage();
        error_log('[DAMI mail] ' . $error);
    }
    if ($error) {
        $GLOBALS['mail_last_error'] = $error;
    }
    mail_history_add($transport, $to, $subject, (bool) $ok, $error);
    return (bool) $ok;
}

/** História odoslaných e-mailov (posledných 100) pre Diagnostiku – bez obsahu správ. */
function mail_history_add($transport, $to, $subject, $ok, $error)
{
    $file = dirname((string) cfg('db_path')) . '/mail-history.log';
    $line = date('Y-m-d H:i:s') . ' | ' . ($ok ? ($transport === 'smtp' || $transport === 'mail' ? 'ODOSLANÝ' : 'IBA LOG (nie je zapnuté odosielanie)') : 'CHYBA')
        . ' | ' . $to . ' | ' . $subject . ($error ? ' | ' . $error : '') . "\n";
    $lines = is_file($file) ? (array) @file($file) : [];
    $lines[] = $line;
    @file_put_contents($file, implode('', array_slice($lines, -100)));
}

/** Zástupný e-mail (terapeut bez doplneného e-mailu) – nikam sa neposiela. */
function is_placeholder_email($email)
{
    return str_ends_with(mb_strtolower($email), '.invalid');
}

function smtp_send($from, $to, $headers, $body)
{
    $c = cfg('mail.smtp');
    $host = $c['host'];
    $port = (int) $c['port'];
    $enc = isset($c['encryption']) ? $c['encryption'] : '';
    $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = stream_socket_client($remote, $errno, $errstr, 15);
    if (!$fp) {
        throw new RuntimeException("SMTP spojenie zlyhalo: $errstr ($errno)");
    }
    stream_set_timeout($fp, 15);

    $read = function () use ($fp) {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = function ($command, array $expect) use ($fp, $read) {
        if ($command !== '') {
            fwrite($fp, $command . "\r\n");
        }
        $resp = $read();
        if (!in_array((int) substr($resp, 0, 3), $expect, true)) {
            throw new RuntimeException('SMTP chyba pri "' . strtok($command, ' ') . '": ' . trim($resp));
        }
        return $resp;
    };

    $ehlo = 'EHLO ' . (gethostname() ?: 'localhost');
    $cmd('', [220]);
    $cmd($ehlo, [250]);
    if ($enc === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, smtp_crypto_method())) {
            throw new RuntimeException('STARTTLS zlyhalo');
        }
        $cmd($ehlo, [250]);
    }
    if (!empty($c['username'])) {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($c['username']), [334]);
        $cmd(base64_encode($c['password']), [235]);
    }
    $cmd("MAIL FROM:<$from>", [250]);
    $cmd("RCPT TO:<$to>", [250, 251]);
    $cmd('DATA', [354]);
    $data = $headers . "\r\n\r\n" . $body;
    $data = preg_replace('/^\./m', '..', $data);
    $cmd($data . "\r\n.", [250]);
    $cmd('QUIT', [221]);
    fclose($fp);
    return true;
}

function mail_html_to_text($html)
{
    $html = preg_replace('~<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>~si', '$2 ($1)', $html);
    $html = preg_replace('~</td>\s*<td[^>]*>~i', ': ', $html);
    $html = preg_replace('~<(br|/p|/tr|/h\d|/li|/div)[^>]*>~i', "\n", $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
    $text = preg_replace("/[ \t]+/", ' ', $text);
    return trim(preg_replace("/\n\s*\n+/", "\n\n", $text));
}

/** Odkaz tel: v medzinárodnom tvare (0907 216 194 → +421907216194), aby sa dalo hneď zavolať. */
function tel_href($phone)
{
    $n = preg_replace('/[^0-9+]/', '', (string) $phone);
    if (strpos($n, '00') === 0) {
        $n = '+' . substr($n, 2);
    } elseif ($n !== '' && $n[0] === '0') {
        $n = '+421' . substr($n, 1);
    }
    return 'tel:' . $n;
}

/** Odkaz v e-maile s pevnou farbou – e-mailové programy inak menia farbu odkazov (napr. Gmail na modrú). */
function mail_link($href, $text, $color)
{
    return '<a href="' . e($href) . '" style="color:' . $color . ';text-decoration:underline">'
        . '<span style="color:' . $color . '">' . e($text) . '</span></a>';
}

/** Hodnota v tabuľke údajov: e-mail a telefón ako klikateľné odkazy, ostatné ako text. */
function mail_detail_value($label, $value)
{
    if ($label === 'E-mail' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return mail_link('mailto:' . $value, $value, '#0D6FB0');
    }
    if ($label === 'Telefón') {
        return mail_link(tel_href($value), $value, '#0D6FB0');
    }
    return nl2br(e($value));
}

/** Zabalí obsah do jednotného HTML šablóny e-mailu. */
function mail_layout($title, $content)
{
    $site = e(cfg('site_name'));
    $addr = e(cfg('contact.address'));
    $mail = mail_link('mailto:' . cfg('contact.email'), (string) cfg('contact.email'), '#ffffff');
    $phone = cfg('contact.phone') ? ' · ' . mail_link(tel_href(cfg('contact.phone')), (string) cfg('contact.phone'), '#ffffff') : '';
    $t = e($title);
    $logo = 'cid:' . MAIL_LOGO_CID;
    return <<<HTML
<!doctype html><html lang="sk"><head><meta charset="utf-8"><title>$t</title></head>
<body style="margin:0;background:#f7fbfe;font-family:-apple-system,BlinkMacSystemFont,'Helvetica Neue',Arial,sans-serif;color:#183247">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f7fbfe;padding:24px 0"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:22px;overflow:hidden;box-shadow:0 14px 30px rgba(13,111,176,.08)">
<tr><td style="background:#eef8fe;padding:18px 28px"><img src="$logo" alt="DAMI" width="135" height="40" style="display:block;width:135px;height:40px;border:0"></td></tr>
<tr><td style="padding:28px">
<h1 style="margin:0 0 16px;font-size:22px;color:#16364d;letter-spacing:-.5px">$t</h1>
$content
</td></tr>
<tr><td style="background:#0D6FB0;background-image:linear-gradient(135deg,#0D6FB0,#5e2d85);color:#ffffff;padding:18px 28px;font-size:13px;line-height:1.7">$site · $addr<br>$mail$phone</td></tr>
</table></td></tr></table></body></html>
HTML;
}

/** TLS 1.2/1.1 aj na starom PHP 5.6, kde STREAM_CRYPTO_METHOD_TLS_CLIENT znamená iba TLS 1.0. */
function smtp_crypto_method()
{
    $m = STREAM_CRYPTO_METHOD_TLS_CLIENT;
    if (defined('STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT')) {
        $m |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
    }
    if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
        $m |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
    }
    return $m;
}
