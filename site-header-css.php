<?php
// Štýly hlavného webu pre jeho hlavičku v rezervácii (upravené pre Shadow DOM).
require __DIR__ . '/app/bootstrap.php';

$f = (string) ($_GET['f'] ?? '');
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
header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo $css;
