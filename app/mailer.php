<?php
declare(strict_types=1);

/**
 * Jednoduché odosielanie e-mailov (HTML + text) bez externých knižníc.
 * Transport: 'log' | 'mail' | 'smtp' (config mail.transport).
 */
function send_mail(string $to, string $subject, string $html, ?string $replyTo = null): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
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
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . (explode('@', $fromEmail)[1] ?? 'localhost') . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    $body = "--$boundary\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text))
        . "--$boundary\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html))
        . "--$boundary--\r\n";

    try {
        switch (cfg('mail.transport')) {
            case 'smtp':
                return smtp_send($fromEmail, $to, "To: $to\r\nSubject: $encSubject\r\n" . implode("\r\n", $headers), $body);
            case 'mail':
                return mail($to, $encSubject, $body, implode("\r\n", $headers), '-f' . $fromEmail);
            default:
                $log = sprintf("==== %s ====\nTo: %s\nSubject: %s\n\n%s\n\n", date('Y-m-d H:i:s'), $to, $subject, $text);
                return file_put_contents(dirname((string) cfg('db_path')) . '/mail.log', $log, FILE_APPEND) !== false;
        }
    } catch (Throwable $ex) {
        error_log('[DAMI mail] ' . $ex->getMessage());
        return false;
    }
}

function smtp_send(string $from, string $to, string $headers, string $body): bool
{
    $c = cfg('mail.smtp');
    $host = $c['host'];
    $port = (int) $c['port'];
    $enc = $c['encryption'] ?? '';
    $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = stream_socket_client($remote, $errno, $errstr, 15);
    if (!$fp) {
        throw new RuntimeException("SMTP spojenie zlyhalo: $errstr ($errno)");
    }
    stream_set_timeout($fp, 15);

    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = function (string $command, array $expect) use ($fp, $read): string {
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
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
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

function mail_html_to_text(string $html): string
{
    $html = preg_replace('~<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>~si', '$2 ($1)', $html);
    $html = preg_replace('~</td>\s*<td[^>]*>~i', ': ', $html);
    $html = preg_replace('~<(br|/p|/tr|/h\d|/li|/div)[^>]*>~i', "\n", $html);
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
    $text = preg_replace("/[ \t]+/", ' ', $text);
    return trim(preg_replace("/\n\s*\n+/", "\n\n", $text));
}

/** Zabalí obsah do jednotného HTML šablóny e-mailu. */
function mail_layout(string $title, string $content): string
{
    $site = e(cfg('site_name'));
    $addr = e(cfg('contact.address'));
    $mail = e(cfg('contact.email'));
    $phone = cfg('contact.phone') ? ' · ' . e(cfg('contact.phone')) : '';
    $t = e($title);
    $logo = e(abs_url('assets/logo.png'));
    return <<<HTML
<!doctype html><html lang="sk"><head><meta charset="utf-8"><title>$t</title></head>
<body style="margin:0;background:#f7fbfe;font-family:-apple-system,BlinkMacSystemFont,'Helvetica Neue',Arial,sans-serif;color:#183247">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f7fbfe;padding:24px 0"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:22px;overflow:hidden;box-shadow:0 14px 30px rgba(13,111,176,.08)">
<tr><td style="background:#eef8fe;padding:18px 28px"><img src="$logo" alt="DAMI" height="40" style="display:block;height:40px;border:0"></td></tr>
<tr><td style="padding:28px">
<h1 style="margin:0 0 16px;font-size:22px;color:#16364d;letter-spacing:-.5px">$t</h1>
$content
</td></tr>
<tr><td style="background:#0D6FB0;background-image:linear-gradient(135deg,#0D6FB0,#5e2d85);color:#ffffff;padding:16px 28px;font-size:12px">$site · $addr<br>$mail$phone</td></tr>
</table></td></tr></table></body></html>
HTML;
}
