<?php
declare(strict_types=1);

const PHOTO_MAX_BYTES = 8 * 1024 * 1024;
const PHOTO_SIZE = 600;               // výsledná fotka je štvorec 600×600 px
const PHOTO_DIR = 'uploads/therapists';

/** URL fotky terapeuta alebo null. */
function therapist_photo_url(array $t): ?string
{
    if (empty($t['photo']) || !is_file(APP_ROOT . '/' . $t['photo'])) {
        return null;
    }
    return url($t['photo']) . '?v=' . filemtime(APP_ROOT . '/' . $t['photo']);
}

/**
 * Spracuje nahranú fotku z $_FILES[$field] pre terapeuta.
 * Vracia [cesta|null, chyba|null]. Ak nebol nahraný žiadny súbor, vracia [null, null].
 */
function therapist_photo_upload(int $therapistId, string $field = 'photo'): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > PHOTO_MAX_BYTES) {
        return [null, 'Fotka je príliš veľká (max. 8 MB).'];
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return [null, 'Fotku sa nepodarilo nahrať. Skúste to znova.'];
    }
    $info = @getimagesize($f['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($allowed[$info[2]])) {
        return [null, 'Povolené sú iba obrázky JPG, PNG alebo WEBP.'];
    }
    if ($info[0] < 100 || $info[1] < 100 || $info[0] * $info[1] > 40_000_000) {
        return [null, 'Fotka musí mať aspoň 100×100 px a najviac 40 Mpx.'];
    }

    $dir = APP_ROOT . '/' . PHOTO_DIR;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = $therapistId . '-' . bin2hex(random_bytes(6));

    // Fotku prekódujeme cez GD (zahodí sa tým aj prípadný škodlivý obsah a EXIF/GPS údaje).
    if (function_exists('imagecreatetruecolor')) {
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($f['tmp_name']),
            IMAGETYPE_PNG  => @imagecreatefrompng($f['tmp_name']),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($f['tmp_name']) : false,
        };
        if (!$src) {
            return [null, 'Obrázok sa nepodarilo spracovať.'];
        }
        $src = photo_fix_orientation($src, $f['tmp_name'], $info[2]);
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        // orez na štvorec – horizontálne na stred, vertikálne skôr hore (tvár)
        $sx = (int) (($w - $side) / 2);
        $sy = (int) (($h - $side) * 0.25);
        $dst = imagecreatetruecolor(PHOTO_SIZE, PHOTO_SIZE);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, PHOTO_SIZE, PHOTO_SIZE, $side, $side);
        $rel = PHOTO_DIR . '/' . $name . '.jpg';
        $ok = imagejpeg($dst, APP_ROOT . '/' . $rel, 86);
        imagedestroy($src);
        imagedestroy($dst);
        if (!$ok) {
            return [null, 'Fotku sa nepodarilo uložiť.'];
        }
    } else {
        $rel = PHOTO_DIR . '/' . $name . '.' . $allowed[$info[2]];
        if (!move_uploaded_file($f['tmp_name'], APP_ROOT . '/' . $rel)) {
            return [null, 'Fotku sa nepodarilo uložiť.'];
        }
    }
    return [$rel, null];
}

function photo_fix_orientation($img, string $path, int $type)
{
    if ($type !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
        return $img;
    }
    $exif = @exif_read_data($path);
    $angle = match ((int) ($exif['Orientation'] ?? 1)) {
        3 => 180, 6 => -90, 8 => 90, default => 0,
    };
    if ($angle) {
        $rotated = imagerotate($img, $angle, 0);
        if ($rotated) {
            imagedestroy($img);
            return $rotated;
        }
    }
    return $img;
}

/** Nastaví novú fotku terapeuta (starú zmaže). $rel = '' fotku odstráni. */
function therapist_photo_set(array $t, string $rel): void
{
    db()->prepare('UPDATE therapists SET photo = ? WHERE id = ?')->execute([$rel, $t['id']]);
    therapist_photo_delete_file((string) ($t['photo'] ?? ''));
}

function therapist_photo_delete_file(string $rel): void
{
    if ($rel !== '' && str_starts_with($rel, PHOTO_DIR . '/') && !str_contains($rel, '..')) {
        @unlink(APP_ROOT . '/' . $rel);
    }
}

/** HTML: fotka alebo iniciály. */
function therapist_avatar_html(array $t, string $class = 'person-photo'): string
{
    $url = therapist_photo_url($t);
    if ($url) {
        return '<div class="' . e($class) . ' has-photo"><img src="' . e($url) . '" alt="' . e(therapist_full_name($t)) . '" loading="lazy"></div>';
    }
    return '<div class="' . e($class) . '">' . e(mb_substr($t['first_name'], 0, 1) . mb_substr($t['last_name'], 0, 1)) . '</div>';
}
