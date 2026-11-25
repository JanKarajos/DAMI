<?php
/*
 * Kompatibilita so starším PHP (hosting ozdami.sk beží na PHP 5.6).
 * Funkcie z novších verzií PHP, ktoré aplikácia používa – definujú sa iba tam, kde chýbajú.
 * Tento súbor musí zostať platný aj pre PHP 5.6 (bez typov, ??, fn a pod.).
 */

if (!defined('IMAGETYPE_WEBP')) {
    define('IMAGETYPE_WEBP', 18);
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        return $needle === '' || strpos((string) $haystack, (string) $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return strncmp((string) $haystack, (string) $needle, strlen((string) $needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        $needle = (string) $needle;
        return $needle === '' || substr((string) $haystack, -strlen($needle)) === $needle;
    }
}
if (!function_exists('intdiv')) {
    function intdiv($a, $b)
    {
        return (int) (($a - $a % $b) / $b);
    }
}
if (!function_exists('array_is_list')) {
    function array_is_list(array $a)
    {
        return $a === array() || array_keys($a) === range(0, count($a) - 1);
    }
}
if (!function_exists('random_bytes')) {
    function random_bytes($length)
    {
        $strong = false;
        $bytes = function_exists('openssl_random_pseudo_bytes') ? openssl_random_pseudo_bytes($length, $strong) : false;
        if ($bytes === false || !$strong) {
            if (function_exists('mcrypt_create_iv')) {
                $bytes = mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
            }
        }
        if ($bytes === false || strlen($bytes) !== $length) {
            throw new RuntimeException('Nepodarilo sa získať náhodné dáta.');
        }
        return $bytes;
    }
}
if (!function_exists('random_int')) {
    function random_int($min, $max)
    {
        $range = $max - $min;
        if ($range <= 0) {
            return $min;
        }
        // nestranné náhodné číslo z random_bytes
        $bits = (int) ceil(log($range + 1, 2));
        $bytes = (int) ceil($bits / 8);
        $mask = (1 << $bits) - 1;
        do {
            $v = hexdec(bin2hex(random_bytes($bytes))) & $mask;
        } while ($v > $range);
        return $min + $v;
    }
}
