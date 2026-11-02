<?php
declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Relatívna cesta k rootu aplikácie (funguje aj v podpriečinku). */
function url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
        $app = realpath(APP_ROOT) ?: APP_ROOT;
        $base = ($docRoot !== '' && str_starts_with($app, $docRoot))
            ? str_replace('\\', '/', substr($app, strlen($docRoot)))
            : '';
        $base = rtrim($base, '/');
    }
    return $base . '/' . ltrim($path, '/');
}

/** Absolútna URL pre e-maily. */
function abs_url(string $path = ''): string
{
    return rtrim((string) cfg('base_url'), '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . (preg_match('~^https?://~', $path) ? $path : url($path)));
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        http_response_code(413);
        exit('Odoslané dáta sú príliš veľké (pravdepodobne fotka). Vráťte sa späť a vyberte menší súbor.');
    }
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Platnosť formulára vypršala. Vráťte sa späť a obnovte stránku.');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function json_out($data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function therapist_full_name(array $t): string
{
    return trim(($t['title'] ? $t['title'] . ' ' : '') . $t['first_name'] . ' ' . $t['last_name']);
}

const SK_DAYS = ['nedeľa', 'pondelok', 'utorok', 'streda', 'štvrtok', 'piatok', 'sobota'];
const SK_DAYS_SHORT = ['Ne', 'Po', 'Ut', 'St', 'Št', 'Pi', 'So'];
const SK_MONTHS = ['', 'január', 'február', 'marec', 'apríl', 'máj', 'jún', 'júl', 'august', 'september', 'október', 'november', 'december'];

/** napr. "streda 4. 11. 2026 o 14:00" */
function fmt_datetime(string $dt): string
{
    $d = new DateTimeImmutable($dt);
    return SK_DAYS[(int) $d->format('w')] . ' ' . $d->format('j. n. Y') . ' o ' . $d->format('H:i');
}

function fmt_date(string $dt): string
{
    $d = new DateTimeImmutable($dt);
    return SK_DAYS_SHORT[(int) $d->format('w')] . ' ' . $d->format('j. n. Y');
}

function fmt_time(string $dt): string
{
    return (new DateTimeImmutable($dt))->format('H:i');
}

function random_password(int $len = 10): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'cli';
}
