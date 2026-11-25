<?php
// Štýly hlavného webu pre jeho hlavičku v rezervácii (upravené pre Shadow DOM).
define('DAMI_STATIC', true);
require __DIR__ . '/app/bootstrap.php';

$f = (string) (isset($_GET['f']) ? $_GET['f'] : '');
$h = site_header();
// Iba štýly, ktoré index.html webu naozaj používa – nič iné sa týmto skriptom načítať nedá.
if (!$h || !in_array($f, $h['css'], true) || !preg_match('/\.css(\?.*)?$/i', $f)) {
    http_response_code(404);
    exit;
}
$css = site_header_css(preg_replace('/\?.*$/', '', $f));
if ($css === null) {
    http_response_code(404);
    exit;
}
// Prehliadač si štýly uloží a pri ďalšej stránke ich nesťahuje znova (bez blikania hlavičky).
$etag = '"' . md5($css) . '"';
header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=86400');
header('ETag: ' . $etag);
if (function_exists('header_remove')) {
    header_remove('Pragma');
    header_remove('Expires');
}
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit;
}
echo $css;
