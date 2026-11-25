<?php

/*
 * Horné menu hlavného webu ozdami.sk v rezervácii.
 *
 * Hlavičku (<header class="site-header">) aj štýly berieme priamo z index.html webu,
 * takže menu v rezervácii je vždy rovnaké ako na webe. Vkladá sa do Shadow DOM,
 * aby sa CSS webu a CSS aplikácie navzájom neovplyvňovali.
 *
 * Zdroj: index.html v nadradenom priečinku (aplikácia v /rezervacia/ na hostingu webu),
 * inak cez HTTP z main_site_url (výsledok sa na hodinu uloží do data/).
 */

/** Vráti ['html' => hlavička, 'css' => [url štýlov]] alebo null, ak sa hlavička nedá načítať. */
function site_header()
{
    static $result = false;
    if ($result !== false) {
        return $result;
    }
    $result = null;
    if (!cfg('main_site_nav') || !class_exists('DOMDocument')) {
        return null;
    }
    $main = site_main_base();
    $html = site_header_source('index.html');
    if ($html === null) {
        return null;
    }

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    $xp = new DOMXPath($doc);
    $header = $xp->query('//header[contains(concat(" ", normalize-space(@class), " "), " site-header ")]')->item(0);
    if (!$header) {
        return null;
    }

    $appBase = url('');                              // napr. /rezervacia/
    $appPath = rtrim((string) parse_url($appBase, PHP_URL_PATH), '/') . '/';
    foreach ($xp->query('.//a[@href]', $header) as $a) {
        /** @var DOMElement $a */
        $abs = site_header_abs($a->getAttribute('href'), $main);
        $a->setAttribute('href', $abs);
        $classes = array_diff(preg_split('/\s+/', trim($a->getAttribute('class'))), ['active', '']);
        $path = (string) parse_url($abs, PHP_URL_PATH);
        if ($appPath !== '/' && str_starts_with($path, $appPath) && $a->parentNode->nodeName === 'nav') {
            $classes[] = 'active';                   // odkaz na rezerváciu je zvýraznený
        }
        $a->setAttribute('class', implode(' ', $classes));
    }
    foreach ($xp->query('.//img[@src]', $header) as $img) {
        /** @var DOMElement $img */
        $img->setAttribute('src', site_header_abs($img->getAttribute('src'), $main));
    }
    foreach ($xp->query('.//script', $header) as $s) {
        $s->parentNode->removeChild($s);
    }

    // Pätička webu (rovnaké prepísanie odkazov a obrázkov, bez zvýraznenia)
    $footerHtml = null;
    $footer = $xp->query('//body//footer[not(ancestor::header)]')->item(0);
    if ($footer) {
        foreach ($xp->query('.//a[@href]', $footer) as $a) {
            $a->setAttribute('href', site_header_abs($a->getAttribute('href'), $main));
        }
        foreach ($xp->query('.//img[@src]', $footer) as $img) {
            $img->setAttribute('src', site_header_abs($img->getAttribute('src'), $main));
        }
        foreach ($xp->query('.//script', $footer) as $s) {
            $s->parentNode->removeChild($s);
        }
        $footerHtml = $doc->saveHTML($footer);
    }

    $css = [];
    foreach ($xp->query('//head/link[@href]') as $link) {
        /** @var DOMElement $link */
        if (strtolower($link->getAttribute('rel')) === 'stylesheet') {
            $css[] = $link->getAttribute('href');
        }
    }
    $result = ['html' => $doc->saveHTML($header), 'footer' => $footerHtml, 'css' => $css];
    return $result;
}

/**
 * Adresa hlavného webu pre odkazy v menu, logu a pätičke.
 * Ak je rezervácia v podpriečinku webu (index.html je o priečinok vyššie), použije sa cesta
 * na tom istom serveri, napr. "/". Odkazy potom fungujú cez http aj https bez ohľadu na nastavenie.
 * Inak (napr. rezervácia na NAS) sa použije main_site_url.
 */
function site_main_base()
{
    if (is_file(dirname(APP_ROOT) . '/index.html')) {
        $base = rtrim(url(''), '/');                 // napr. /rezervacia
        $pos = strrpos($base, '/');
        return ($pos === false ? '' : substr($base, 0, $pos)) . '/';
    }
    return rtrim((string) cfg('main_site_url'), '/') . '/';
}

function site_header_abs($href, $main)
{
    if ($href === '' || preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $href)) {
        return $href;                                // https:, mailto:, tel:, //cdn…
    }
    if ($href[0] === '/') {
        if ($main[0] === '/') {
            return $href;                            // web na tom istom serveri
        }
        $origin = preg_replace('#^(https?://[^/]+).*$#i', '$1', $main);
        return $origin . $href;
    }
    return $main . $href;                            // #sluzby, rezervacia/…, obrazok.png
}

/** Obsah súboru hlavného webu: lokálne z nadradeného priečinka, inak HTTP (s cache). */
function site_header_source($file)
{
    $file = ltrim((string) parse_url($file, PHP_URL_PATH), '/');
    if ($file === '' || str_contains($file, '..')) {
        return null;
    }
    $local = dirname(APP_ROOT) . '/' . $file;
    if (is_file($local)) {
        return (string) file_get_contents($local);
    }
    $cache = APP_ROOT . '/data/site-' . md5($file) . '.cache';
    if (is_file($cache) && time() - filemtime($cache) < 3600) {
        return (string) file_get_contents($cache);
    }
    $url = rtrim((string) cfg('main_site_url'), '/') . '/' . $file;
    $body = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 4]]));
    if ($body === false || $body === '') {
        return is_file($cache) ? (string) file_get_contents($cache) : null;
    }
    @file_put_contents($cache, $body);
    return $body;
}

/** CSS webu upravené pre Shadow DOM (:root → :host). */
function site_header_css($file)
{
    $css = site_header_source($file);
    if ($css === null) {
        return null;
    }
    $css = preg_replace('/:root\b/', ':host', $css);
    // relatívne url(...) v CSS smerujú na web
    $file = ltrim($file, '/');
    $dir = strrpos($file, '/') === false ? '' : substr($file, 0, strrpos($file, '/') + 1);
    $base = site_main_base() . $dir;
    return preg_replace_callback('/url\(\s*([\'"]?)(?!data:|https?:|\/\/|#)([^\'")]+)\1\s*\)/i',
        function ($m) use ($base) { return 'url(' . $m[1] . site_header_abs($m[2], $base) . $m[1] . ')'; }, $css);
}

/** Spoločný začiatok Shadow DOM: štýly webu + základ, ktorý web nastavuje na <body>. */
function site_part_styles(array $h)
{
    $links = '';
    foreach ($h['css'] as $href) {
        $links .= '<link rel="stylesheet" href="' . e(url('site-header-css.php') . '?f=' . rawurlencode($href)) . '">';
    }
    return $links . '<style>:host{display:block;font-family:-apple-system,BlinkMacSystemFont,"Helvetica Neue",Arial,sans-serif;'
        . 'line-height:1.55;-webkit-font-smoothing:antialiased;color:var(--ink,#183247)}</style>';
}

/** Vloží časť webu (hlavičku alebo pätičku) do Shadow DOM. $js beží s premennou root. */
function site_part_render($id, $html, array $h, $js = '')
{
    return '<div id="' . $id . '-host"></div>'
        . '<template id="' . $id . '-tpl">' . site_part_styles($h) . $html . '</template>'
        . '<script>(function(){var host=document.getElementById("' . $id . '-host"),tpl=document.getElementById("' . $id . '-tpl");'
        . 'if(!host||!tpl||!host.attachShadow)return;var root=host.attachShadow({mode:"open"});root.appendChild(tpl.content.cloneNode(true));'
        . $js . '})();</script>';
}

/** Hlavička webu – vkladá sa hneď za <body>. */
function site_header_render(array $h)
{
    return site_part_render('site-header', $h['html'], $h,
        'var header=root.querySelector("[data-header]")||root.querySelector("header"),toggle=root.querySelector("[data-menu-toggle]"),menu=root.querySelector("[data-menu]");'
        . 'var upd=function(){header&&header.classList.toggle("scrolled",window.scrollY>18)};upd();window.addEventListener("scroll",upd,{passive:true});'
        . 'var close=function(){menu&&menu.classList.remove("open");toggle&&toggle.setAttribute("aria-expanded","false");document.body.classList.remove("menu-open")};'
        . 'toggle&&toggle.addEventListener("click",function(){var o=toggle.getAttribute("aria-expanded")==="true";toggle.setAttribute("aria-expanded",String(!o));'
        . 'menu&&menu.classList.toggle("open",!o);document.body.classList.toggle("menu-open",!o)});'
        . 'menu&&menu.querySelectorAll("a").forEach(function(a){a.addEventListener("click",close)});');
}

/** Pätička webu – vkladá sa namiesto pätičky aplikácie. */
function site_footer_render(array $h)
{
    return site_part_render('site-footer', (string) $h['footer'], $h);
}
