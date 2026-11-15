<?php
error_reporting(E_ERROR | E_PARSE);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: text/html; charset=ISO-8859-1');

$session_key = preg_replace('/[^a-zA-Z0-9]/', '', session_id() ?: 'default_retro');
$cookie_jar_file = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'retro_cookies_' . $session_key . '.txt';

if (!empty($_GET['clear_cookies']) && file_exists($cookie_jar_file)) {
    @unlink($cookie_jar_file);
}

// Extracts direct target URLs from search engine redirectors (DuckDuckGo uddg, Google /url, Bing, Yahoo)
function unwrap_redirect_url($raw_url) {
    $raw_url = trim($raw_url);
    if ($raw_url === '') return '';

    if (strpos($raw_url, '//') === 0) {
        $raw_url = 'https:' . $raw_url;
    }

    $parts = @parse_url($raw_url);
    if (!is_array($parts)) return $raw_url;

    $host = strtolower($parts['host'] ?? '');
    $path = $parts['path'] ?? '';
    $query_str = $parts['query'] ?? '';

    if ($query_str !== '') {
        parse_str($query_str, $q);

        // DuckDuckGo redirector: https://duckduckgo.com/l/?uddg=<ENCODED_URL>
        if (!empty($q['uddg']) && is_string($q['uddg'])) {
            $candidate = trim($q['uddg']);
            if (preg_match('#^https?://#i', $candidate)) {
                return $candidate;
            }
        }

        // Google redirector: https://www.google.com/url?q=<ENCODED_URL> or &url=<ENCODED_URL>
        if (strpos($host, 'google.') !== false && ($path === '/url' || $path === '/interstitial')) {
            foreach (['q', 'url'] as $k) {
                if (!empty($q[$k]) && is_string($q[$k]) && preg_match('#^https?://#i', trim($q[$k]))) {
                    return trim($q[$k]);
                }
            }
        }

        // Generic redirect parameters on known redirect paths
        if (preg_match('#^/(l|r|redirect|out|goto)(/|$)#i', $path)) {
            foreach (['u', 'url', 'target', 'dest', 'destination', 'to'] as $k) {
                if (!empty($q[$k]) && is_string($q[$k]) && preg_match('#^https?://#i', trim($q[$k]))) {
                    return trim($q[$k]);
                }
            }
        }
    }

    // Yahoo redirector pattern: /RU=<ENCODED_URL>/RK=...
    if (strpos($host, 'yahoo.com') !== false && preg_match('#/RU=([^/]+)/#i', $path, $ym)) {
        $decoded = rawurldecode($ym[1]);
        if (preg_match('#^https?://#i', $decoded)) {
            return $decoded;
        }
    }

    return $raw_url;
}

function convert_params_to_utf8(array $params) {
    $out = [];
    $reserved = ['__proxy_action', '__proxy_method', '__proxy_p', '__proxy_ref'];
    foreach ($params as $k => $v) {
        if (in_array($k, $reserved, true)) continue;
        $k_utf8 = mb_convert_encoding((string)$k, 'UTF-8', 'ISO-8859-1');
        if (is_array($v)) {
            $out[$k_utf8] = convert_params_to_utf8($v);
        } else {
            $out[$k_utf8] = mb_convert_encoding((string)$v, 'UTF-8', 'ISO-8859-1');
        }
    }
    return $out;
}

$paginate = !empty($_REQUEST['p']) || !empty($_REQUEST['__proxy_p']) ? 1 : 0;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$request_method = 'GET';
$post_payload = null;
$referer_url = isset($_REQUEST['__proxy_ref']) ? trim($_REQUEST['__proxy_ref']) : '';

// Intercepts proxied form submissions
if (!empty($_REQUEST['__proxy_action'])) {
    $target_action = unwrap_redirect_url(trim($_REQUEST['__proxy_action']));
    $target_method = strtoupper(trim($_REQUEST['__proxy_method'] ?? 'GET'));

    if ($target_method === 'POST') {
        $url = $target_action;
        $request_method = 'POST';
        $utf8_post = convert_params_to_utf8($_POST);
        $post_payload = http_build_query($utf8_post, '', '&', PHP_QUERY_RFC1738);
    } else {
        $parsed = parse_url($target_action);
        $existing_query = [];
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $existing_query);
        }
        $utf8_get = convert_params_to_utf8($_GET);
        $merged_query = array_merge($existing_query, $utf8_get);

        $base_action = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? '');
        if (!empty($parsed['port'])) $base_action .= ':' . $parsed['port'];
        $base_action .= $parsed['path'] ?? '/';

        $final_target = $base_action;
        if (!empty($merged_query)) {
            $final_target .= '?' . http_build_query($merged_query, '', '&', PHP_QUERY_RFC1738);
        }
        $final_target = unwrap_redirect_url($final_target);

        header('Location: ?url=' . urlencode($final_target) . ($paginate ? '&p=1' : ''));
        exit;
    }
} else {
    $url = isset($_GET['url']) ? trim($_GET['url']) : '';
}

if ($url !== '' && !preg_match('#^https?://#i', $url)) {
    if (strpos($url, '//') === 0) {
        $url = 'https:' . $url;
    } else {
        $url = 'http://' . $url;
    }
}

// Unwrap redirector URLs immediately if passed to ?url=
$url = unwrap_redirect_url($url);

if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><body bgcolor="#C0C0C0">';
    echo '<table width="760" align="center" bgcolor="#D4D0C8" border="1" bordercolor="#808080" cellpadding="6" cellspacing="0"><tr><td>';
    echo '<form method="GET" action="" style="margin:0;"><font face="Verdana, Arial" size="1"><b>Address:</b> </font>';
    echo '<input type="text" name="url" size="50" value="https://github.com"> ';
    echo '<input type="submit" value="Go"><br>';
    echo '<input type="checkbox" name="p" value="1"> <font face="Verdana, Arial" size="1">Paginate (Save RAM)</font>';
    echo '</form></td></tr></table>';
    echo '<p align="center"><font face="Verdana, Arial" size="2">Please enter a valid URL above.</font></p></body></html>';
    exit;
}

function resolve_url($base, $rel) {
    $rel = trim(html_entity_decode($rel, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($rel === '' || preg_match('#^(javascript:|mailto:|tel:|data:|about:|\#)#i', $rel)) {
        return '';
    }
    if (strpos($rel, '//') === 0) {
        $base_scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';
        return unwrap_redirect_url($base_scheme . ':' . $rel);
    }
    if (parse_url($rel, PHP_URL_SCHEME) != '') {
        return unwrap_redirect_url($rel);
    }

    $base_parts = parse_url($base);
    $scheme = $base_parts['scheme'] ?? 'http';
    $host = $base_parts['host'] ?? '';
    $port = isset($base_parts['port']) ? ':' . $base_parts['port'] : '';

    if ($rel[0] === '?') {
        $path = $base_parts['path'] ?? '/';
        return unwrap_redirect_url("$scheme://$host$port$path$rel");
    }

    $query_suffix = '';
    if (strpos($rel, '#') !== false) {
        $rel = explode('#', $rel, 2)[0];
    }
    if (strpos($rel, '?') !== false) {
        list($rel_path, $q) = explode('?', $rel, 2);
        $query_suffix = '?' . $q;
    } else {
        $rel_path = $rel;
    }

    if ($rel_path !== '' && $rel_path[0] === '/') {
        $path = $rel_path;
    } else {
        $base_path = $base_parts['path'] ?? '/';
        $base_dir = preg_replace('#/[^/]*$#', '/', $base_path);
        $path = $base_dir . $rel_path;
    }

    $segments = explode('/', $path);
    $resolved = [];
    foreach ($segments as $seg) {
        if ($seg === '' || $seg === '.') continue;
        if ($seg === '..') {
            array_pop($resolved);
        } else {
            $resolved[] = $seg;
        }
    }
    $final_abs = "$scheme://$host$port/" . implode('/', $resolved) . $query_suffix;
    return unwrap_redirect_url($final_abs);
}

// Detects <meta http-equiv="refresh"> or interstitial JS location redirects in HTTP 200 responses
function detect_html_redirect($html_body, $current_url) {
    if (!is_string($html_body) || $html_body === '') return '';

    // 1. Check <meta http-equiv="refresh" content="0; url=...">
    if (preg_match_all('/<meta\b[^>]*>/i', substr($html_body, 0, 16384), $meta_tags)) {
        foreach ($meta_tags[0] as $tag) {
            if (preg_match('/http-equiv\s*=\s*["\']?refresh["\']?/i', $tag) &&
                preg_match('/content\s*=\s*["\'][^"\']*?\burl\s*=\s*([^"\'\s>]+)/i', $tag, $m)) {
                $candidate = trim($m[1], " \t\n\r\0\x0B\"'");
                $resolved = resolve_url($current_url, $candidate);
                if ($resolved && $resolved !== $current_url) {
                    return $resolved;
                }
            }
        }
    }

    // 2. Check lightweight JS redirect pages (like DuckDuckGo /l/ interstitial)
    $clean_text = trim(strip_tags(preg_replace('/<(script|style|head)\b[^>]*>.*?<\/\1>/is', '', $html_body)));
    if (strlen($clean_text) < 250) {
        if (preg_match('/(?:window\.location\.replace|window\.location\.assign|location\.replace)\s*\(\s*["\']([^"\']+)["\']\s*\)/i', $html_body, $jm) ||
            preg_match('/(?:window\.location\.href|window\.location|location\.href)\s*=\s*["\']([^"\']+)["\']/i', $html_body, $jm)) {
            $js_url = str_replace(['\\/', '\\u0026', '\\x26'], ['/', '&', '&'], $jm[1]);
            $resolved = resolve_url($current_url, $js_url);
            if ($resolved && $resolved !== $current_url) {
                return $resolved;
            }
        }
    }

    return '';
}

function execute_single_http($target_url, $method = 'GET', $post_data = null, $cookie_jar = '', $referer = '', $timeout = 15, $max_bytes = 0, array $extra_headers = []) {
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36';

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $target_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 8);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_USERAGENT, $ua);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        if ($cookie_jar !== '') {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_jar);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_jar);
        }

        $headers = array_merge([
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9,pt-BR;q=0.8'
        ], $extra_headers);

        if ($referer !== '') {
            curl_setopt($ch, CURLOPT_REFERER, $referer);
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data ?? '');
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($max_bytes > 0) {
            $buffer = '';
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch_inst, $chunk) use (&$buffer, $max_bytes) {
                $buffer .= $chunk;
                if (strlen($buffer) >= $max_bytes) {
                    return 0;
                }
                return strlen($chunk);
            });
            @curl_exec($ch);
            $body = $buffer;
        } else {
            $body = @curl_exec($ch);
        }

        $effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $target_url;
        curl_close($ch);

        return ['body' => $body, 'effective_url' => unwrap_redirect_url($effective_url)];
    }

    $headers = "User-Agent: $ua\r\nAccept: text/html,*/*;q=0.8\r\nAccept-Encoding: identity\r\n";
    foreach ($extra_headers as $eh) {
        $headers .= $eh . "\r\n";
    }
    $http_opts = ['method' => $method, 'timeout' => $timeout, 'follow_location' => 1, 'max_redirects' => 5];
    if ($method === 'POST' && $post_data !== null) {
        $headers .= "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($post_data) . "\r\n";
        $http_opts['content'] = $post_data;
    }
    $http_opts['header'] = $headers;
    $ctx = stream_context_create(['http' => $http_opts, 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $body = ($max_bytes > 0) ? @file_get_contents($target_url, false, $ctx, 0, $max_bytes) : @file_get_contents($target_url, false, $ctx);
    return ['body' => $body, 'effective_url' => unwrap_redirect_url($target_url)];
}

// Stateful HTTP fetcher that also follows HTML <meta http-equiv="refresh"> and JS interstitials
function fetch_http_stateful($target_url, $method = 'GET', $post_data = null, $cookie_jar = '', $referer = '', $timeout = 15, $max_bytes = 0, array $extra_headers = []) {
    $current = unwrap_redirect_url($target_url);
    $cur_method = $method;
    $cur_post = $post_data;
    $cur_ref = $referer;

    for ($hop = 0; $hop < 4; $hop++) {
        $res = execute_single_http($current, $cur_method, $cur_post, $cookie_jar, $cur_ref, $timeout, $max_bytes, $extra_headers);
        $effective = $res['effective_url'];

        // If cURL landed on a known redirector URL (e.g. duckduckgo.com/l/?uddg=...), unwrap and follow
        $unwrapped = unwrap_redirect_url($effective);
        if ($unwrapped !== $effective && filter_var($unwrapped, FILTER_VALIDATE_URL)) {
            $cur_ref = $effective;
            $current = $unwrapped;
            $cur_method = 'GET';
            $cur_post = null;
            continue;
        }

        if ($max_bytes === 0 && !empty($res['body'])) {
            $html_redir = detect_html_redirect($res['body'], $effective);
            if ($html_redir !== '' && filter_var($html_redir, FILTER_VALIDATE_URL)) {
                $cur_ref = $effective;
                $current = $html_redir;
                $cur_method = 'GET';
                $cur_post = null;
                continue;
            }
        }

        return $res;
    }

    return $res;
}

function remove_nodes(DOMNodeList $nodes) {
    for ($i = $nodes->length - 1; $i >= 0; $i--) {
        $node = $nodes->item($i);
        if ($node && $node->parentNode) {
            $node->parentNode->removeChild($node);
        }
    }
}

function clear_all_attributes(DOMElement $el) {
    if (!$el->hasAttributes()) return;
    $names = [];
    foreach ($el->attributes as $attr) {
        $names[] = $attr->name;
    }
    foreach ($names as $n) {
        $el->removeAttribute($n);
    }
}

function normalize_color_to_hex($raw) {
    $raw = strtolower(trim($raw));
    if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $raw, $m)) {
        $hex = $m[1];
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return '#' . strtoupper($hex);
    }
    if (preg_match('/rgb\s*\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})/i', $raw, $m)) {
        $r = min(255, max(0, (int)$m[1]));
        $g = min(255, max(0, (int)$m[2]));
        $b = min(255, max(0, (int)$m[3]));
        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }
    $named = [
        'black' => '#000000', 'red' => '#CC0000', 'navy' => '#000080',
        'darkblue' => '#00008B', 'darkred' => '#8B0000', 'maroon' => '#800000',
        'darkgreen' => '#006400', 'purple' => '#800080', 'teal' => '#008080'
    ];
    return $named[$raw] ?? '';
}

function hex_to_rgb($hex) {
    $hex = ltrim($hex, '#');
    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2))
    ];
}

function get_luminance($hex) {
    list($r, $g, $b) = hex_to_rgb($hex);
    return (0.299 * $r) + (0.587 * $g) + (0.114 * $b);
}

function is_usable_brand_color($hex, $allow_black = true) {
    if (!$hex || strlen($hex) !== 7) return false;
    if ($hex === '#000000') return $allow_black;
    list($r, $g, $b) = hex_to_rgb($hex);
    $lum = get_luminance($hex);
    if ($lum > 215) return false;
    $chroma = max($r, $g, $b) - min($r, $g, $b);
    if ($chroma < 18 && $lum > 45) return false;
    return true;
}

function make_legible_on_white($hex) {
    if ($hex === '#000000') return '#003366';
    list($r, $g, $b) = hex_to_rgb($hex);
    $lum = get_luminance($hex);
    if ($lum > 110) {
        $factor = 95 / $lum;
        $r = (int)max(0, min(255, round($r * $factor)));
        $g = (int)max(0, min(255, round($g * $factor)));
        $b = (int)max(0, min(255, round($b * $factor)));
        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }
    return $hex;
}

function extract_brand_palette(DOMXPath $xpath, $base_url, $cookie_jar) {
    $primary_hex = '';
    $accent_hex = '';

    $meta_nodes = $xpath->query('//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="theme-color" or translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="msapplication-tilecolor"]');
    foreach ($meta_nodes as $m) {
        if ($m instanceof DOMElement) {
            $cand = normalize_color_to_hex($m->getAttribute('content'));
            if (is_usable_brand_color($cand, true)) {
                $primary_hex = $cand;
                break;
            }
        }
    }

    $css_corpus = '';
    foreach ($xpath->query('//style') as $st) {
        $css_corpus .= "\n" . $st->textContent;
    }

    if ($primary_hex === '' || $primary_hex === '#000000') {
        $links = $xpath->query('//link[contains(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "stylesheet")]');
        $fetched = 0;
        foreach ($links as $lnk) {
            if ($fetched >= 2) break;
            if (!($lnk instanceof DOMElement)) continue;
            $href = resolve_url($base_url, $lnk->getAttribute('href'));
            if ($href) {
                $res = fetch_http_stateful($href, 'GET', null, $cookie_jar, $base_url, 4, 120000);
                if (!empty($res['body'])) {
                    $css_corpus .= "\n" . $res['body'];
                    $fetched++;
                }
            }
        }
    }

    if ($css_corpus !== '') {
        if (preg_match_all('/--(?:brand|primary|theme|main|header|navbar|site|cor)[a-z0-9-_]*\s*:\s*([^;\}]+)/i', $css_corpus, $matches)) {
            foreach ($matches[1] as $raw_val) {
                $cand = normalize_color_to_hex($raw_val);
                if (is_usable_brand_color($cand, true)) {
                    if ($primary_hex === '') $primary_hex = $cand;
                    if ($cand !== '#000000' && $accent_hex === '') $accent_hex = $cand;
                }
            }
        }

        if (preg_match_all('/(?:^|[\}\s])(?:header|\.header|#header|nav|\.navbar|\.top-bar|\.header-bar|\.menu-bar)[^{]*\{([^}]+)\}/i', $css_corpus, $rule_matches)) {
            foreach ($rule_matches[1] as $declarations) {
                if (preg_match('/background(?:-color)?\s*:\s*([^;\}]+)/i', $declarations, $bgm)) {
                    if (preg_match('/(#[0-9a-f]{3,6}|rgb\s*\([^)]+\)|black|red|navy|darkred|darkblue)/i', $bgm[1], $cm)) {
                        $cand = normalize_color_to_hex($cm[1]);
                        if (is_usable_brand_color($cand, true)) {
                            if ($primary_hex === '') $primary_hex = $cand;
                            if ($cand !== '#000000' && $accent_hex === '') $accent_hex = $cand;
                        }
                    }
                }
            }
        }

        if ($accent_hex === '' && preg_match_all('/#[0-9a-fA-F]{6}/', $css_corpus, $all_hex)) {
            $counts = array_count_values(array_map('strtoupper', $all_hex[0]));
            arsort($counts);
            foreach ($counts as $h => $cnt) {
                list($r, $g, $b) = hex_to_rgb($h);
                $chroma = max($r, $g, $b) - min($r, $g, $b);
                $lum = get_luminance($h);
                if ($chroma >= 65 && $lum >= 25 && $lum <= 175) {
                    $accent_hex = $h;
                    break;
                }
            }
        }
    }

    if ($primary_hex === '') {
        $primary_hex = ($accent_hex !== '') ? $accent_hex : '#003366';
    }

    $bar_lum = get_luminance($primary_hex);
    $bar_text = ($bar_lum > 145) ? '#000000' : '#FFFFFF';
    $bar_sep  = ($bar_lum > 145) ? '#333333' : '#FFCC00';

    $raw_link = ($primary_hex === '#000000' && $accent_hex !== '') ? $accent_hex : $primary_hex;
    $link_color = make_legible_on_white($raw_link);

    return [
        'bar_bg'     => $primary_hex,
        'bar_text'   => $bar_text,
        'bar_sep'    => $bar_sep,
        'link_color' => $link_color
    ];
}

function extract_site_identity(DOMXPath $xpath, $base_url) {
    $logo_url = '';
    $favicon_url = '';

    $meta_logo = $xpath->query('//meta[@property="og:logo" or @name="logo"]');
    foreach ($meta_logo as $ml) {
        if ($ml instanceof DOMElement) {
            $c = resolve_url($base_url, $ml->getAttribute('content'));
            $p = strtolower(parse_url($c, PHP_URL_PATH) ?? '');
            if ($c && substr($p, -4) !== '.svg') {
                $logo_url = 'img_proxy.php?h=45&w=200&url=' . urlencode($c);
                break;
            }
        }
    }

    if ($logo_url === '') {
        $logo_candidates = $xpath->query(
            '//img[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "logo") or ' .
            'contains(translate(@id, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "logo") or ' .
            'contains(translate(@alt, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "logo") or ' .
            'contains(translate(@src, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "logo")] | ' .
            '//a[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "logo") or ' .
            'contains(translate(@id, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "logo") or ' .
            'contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "brand")]/img'
        );

        foreach ($logo_candidates as $img) {
            if (!($img instanceof DOMElement)) continue;

            $inside_content = false;
            $anc = $img->parentNode;
            while ($anc && $anc->nodeName !== 'body') {
                $n = strtolower($anc->nodeName);
                $cls = ($anc instanceof DOMElement) ? strtolower($anc->getAttribute('class') . ' ' . $anc->getAttribute('id')) : '';
                if (in_array($n, ['article', 'figure', 'main', 'footer'], true) ||
                    preg_match('/(card|story|article|post|item|feed|carousel|banner|ad-|promo|sponsor|partner)/i', $cls)) {
                    $inside_content = true;
                    break;
                }
                $anc = $anc->parentNode;
            }
            if ($inside_content) continue;

            $src = '';
            foreach (['src', 'data-src', 'data-original'] as $attr) {
                $val = trim($img->getAttribute($attr));
                if ($val !== '' && strpos($val, 'data:') !== 0) {
                    $src = $val;
                    break;
                }
            }
            if ($src === '') continue;

            $abs = resolve_url($base_url, $src);
            if (!$abs) continue;

            $lower_abs = strtolower($abs);
            $path_part = strtolower(parse_url($abs, PHP_URL_PATH) ?? '');
            if (substr($path_part, -4) === '.svg' || strpos($lower_abs, '.svg') !== false ||
                preg_match('/(avatar|profile|user|author|thumb|placeholder|pixel|1x1)/i', $lower_abs)) {
                continue;
            }

            $logo_url = 'img_proxy.php?h=45&w=200&url=' . urlencode($abs);
            if ($img->parentNode) {
                $img->parentNode->removeChild($img);
            }
            break;
        }
    }

    if ($logo_url === '') {
        $icon_links = $xpath->query('//link[contains(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "icon")]');
        foreach ($icon_links as $lnk) {
            if (!($lnk instanceof DOMElement)) continue;
            $href = trim($lnk->getAttribute('href'));
            if ($href === '' || strpos($href, 'data:') === 0) continue;
            $abs_icon = resolve_url($base_url, $href);
            $icon_path = strtolower(parse_url($abs_icon, PHP_URL_PATH) ?? '');
            if (substr($icon_path, -4) === '.svg') continue;
            $favicon_url = $abs_icon;
            if (preg_match('/\.(png|gif|jpg|jpeg|ico)$/i', $icon_path)) {
                break;
            }
        }
        if ($favicon_url === '') {
            $parts = parse_url($base_url);
            $favicon_url = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '') . '/favicon.ico';
        }
        $favicon_url = 'img_proxy.php?h=16&w=16&fav=1&url=' . urlencode($favicon_url);
    }

    return [
        'logo_proxy'    => $logo_url,
        'favicon_proxy' => $favicon_url
    ];
}

// Recursively locates a target key (e.g. 'tree', 'blob', 'repo', 'refInfo') inside GitHub's dynamic route JSON
function find_github_key_recursive(array $data, $target_key, $max_depth = 4) {
    if (isset($data[$target_key]) && is_array($data[$target_key])) {
        return $data[$target_key];
    }
    if ($max_depth <= 0) return null;

    foreach ($data as $k => $v) {
        if (is_array($v)) {
            $found = find_github_key_recursive($v, $target_key, $max_depth - 1);
            if ($found !== null) return $found;
        }
    }
    return null;
}

// Extracts GitHub JSON payloads directly from RAW HTML before DOMDocument can corrupt <script> contents
function build_github_view_from_raw($raw_html, $current_url, $cookie_jar) {
    $host = strtolower(parse_url($current_url, PHP_URL_HOST) ?? '');
    if (strpos($host, 'github.com') === false) return '';

    $url_path = trim(parse_url($current_url, PHP_URL_PATH) ?? '', '/');
    // Match: owner/repo or owner/repo/(tree|blob)/branch/subpath
    if (!preg_match('#^([^/]+)/([^/]+)(?:/(tree|blob)/([^/]+)(?:/(.*))?)?$#', $url_path, $um)) {
        return '';
    }

    $url_owner   = $um[1];
    $url_repo    = $um[2];
    $url_mode    = $um[3] ?? ''; // '' (root), 'tree', or 'blob'
    $url_branch  = $um[4] ?? '';
    $url_subpath = $um[5] ?? '';

    $reserved_owners = ['login', 'session', 'settings', 'explore', 'trending', 'topics', 'search', 'notifications', 'pulls', 'issues', 'marketplace', 'orgs', 'users'];
    if (in_array(strtolower($url_owner), $reserved_owners, true)) {
        return '';
    }

    $payloads = [];

    // 1. Extract all embedded JSON scripts from raw HTML string
    if (preg_match_all('/<script\b[^>]*data-target="react-(?:app|partial)\.embeddedData"[^>]*>(.*?)<\/script>/is', $raw_html, $matches)) {
        foreach ($matches[1] as $raw_json) {
            $raw_json = trim($raw_json);
            $decoded = @json_decode($raw_json, true);
            if (!is_array($decoded)) {
                $decoded = @json_decode(html_entity_decode($raw_json, ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            }
            if (is_array($decoded)) {
                if (!empty($decoded['payload']) && is_array($decoded['payload'])) {
                    $payloads[] = $decoded['payload'];
                } elseif (!empty($decoded['props']['initialPayload']) && is_array($decoded['props']['initialPayload'])) {
                    $payloads[] = $decoded['props']['initialPayload'];
                }
            }
        }
    }

    // 2. If on /tree/ or /blob/ and no payload was found in HTML, query GitHub with Accept: application/json
    if (empty($payloads) && ($url_mode === 'tree' || $url_mode === 'blob')) {
        $json_res = execute_single_http($current_url, 'GET', null, $cookie_jar, $current_url, 10, 0, [
            'Accept: application/json',
            'X-Requested-With: XMLHttpRequest'
        ]);
        if (!empty($json_res['body'])) {
            $decoded = @json_decode($json_res['body'], true);
            if (!empty($decoded['payload']) && is_array($decoded['payload'])) {
                $payloads[] = $decoded['payload'];
            }
        }
    }

    $tree_items     = null;
    $blob_data      = null;
    $repo_info      = null;
    $ref_info       = null;
    $readme_html    = '';

    foreach ($payloads as $p) {
        if ($repo_info === null) {
            $repo_info = find_github_key_recursive($p, 'repo');
        }
        if ($ref_info === null) {
            $ref_info = find_github_key_recursive($p, 'refInfo');
        }
        if ($tree_items === null) {
            $t = find_github_key_recursive($p, 'tree');
            if (is_array($t) && !empty($t['items']) && is_array($t['items'])) {
                $tree_items = $t['items'];
            }
        }
        if ($blob_data === null) {
            $b = find_github_key_recursive($p, 'blob');
            if (is_array($b) && (isset($b['rawLines']) || isset($b['hlLines']) || isset($b['richText']) || isset($b['headerInfo']))) {
                $blob_data = $b;
            }
        }
        if ($readme_html === '') {
            $ov = find_github_key_recursive($p, 'overview');
            if (is_array($ov) && !empty($ov['overviewFiles']) && is_array($ov['overviewFiles'])) {
                foreach ($ov['overviewFiles'] as $of) {
                    if (!empty($of['richText'])) {
                        $readme_html .= $of['richText'];
                    }
                }
            }
            if ($readme_html === '' && !empty($t['readme']['richText'])) {
                $readme_html = $t['readme']['richText'];
            }
        }
    }

    $owner  = $repo_info['ownerLogin'] ?? $url_owner;
    $rname  = $repo_info['name'] ?? $url_repo;
    $branch = $ref_info['name'] ?? ($url_branch !== '' ? $url_branch : ($repo_info['defaultBranch'] ?? 'main'));

    // Recalculate subpath accurately if branch name has slashes
    $subpath = $url_subpath;
    if ($url_mode !== '' && $branch !== '' && preg_match('#^' . preg_quote("$owner/$rname/$url_mode/$branch", '#') . '(?:/(.*))?$#i', $url_path, $pm)) {
        $subpath = $pm[1] ?? '';
    }

    $out_html = '';

    // Header Breadcrumb Bar for Repository Navigation
    if ($tree_items !== null || $blob_data !== null || $url_mode === 'tree' || $url_mode === 'blob') {
        $root_href = "https://github.com/$owner/$rname";
        $out_html .= '<table width="100%" bgcolor="#EFEFEF" border="1" bordercolor="#CCCCCC" cellpadding="5" cellspacing="0"><tr><td>';
        $out_html .= '<font face="Verdana, Arial, sans-serif" size="2"><b>Repository:</b> ';
        $out_html .= '<a href="' . htmlspecialchars($root_href, ENT_QUOTES, 'UTF-8') . '"><b>' . htmlspecialchars("$owner / $rname", ENT_QUOTES, 'UTF-8') . '</b></a>';
        $out_html .= ' &nbsp;[' . htmlspecialchars($branch, ENT_QUOTES, 'UTF-8') . ']';
        if ($subpath !== '') {
            $out_html .= '<br><b>Path:</b> /' . htmlspecialchars($subpath, ENT_QUOTES, 'UTF-8');
        }
        $out_html .= '</font></td></tr></table><br>';
    }

    // A. Render Directory Tree Table (Root or Subfolder)
    if (is_array($tree_items) && count($tree_items) > 0) {
        $out_html .= '<table width="100%" border="1" bordercolor="#CCCCCC" cellpadding="4" cellspacing="0">';
        $out_html .= '<tr bgcolor="#D4D0C8"><td width="75"><b>Type</b></td><td><b>Name</b></td></tr>';

        // Parent Directory (..) link when inside a subfolder
        if ($subpath !== '') {
            $parent_parts = explode('/', trim($subpath, '/'));
            array_pop($parent_parts);
            if (empty($parent_parts)) {
                $parent_href = "https://github.com/$owner/$rname";
            } else {
                $parent_href = "https://github.com/$owner/$rname/tree/$branch/" . implode('/', $parent_parts);
            }
            $out_html .= '<tr><td>[DIR]</td><td><a href="' . htmlspecialchars($parent_href, ENT_QUOTES, 'UTF-8') . '"><b>.. (Parent Directory)</b></a></td></tr>';
        }

        // Sort directories first, then files
        usort($tree_items, function ($a, $b) {
            $a_dir = (($a['contentType'] ?? '') === 'directory') ? 0 : 1;
            $b_dir = (($b['contentType'] ?? '') === 'directory') ? 0 : 1;
            if ($a_dir !== $b_dir) return $a_dir - $b_dir;
            return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
        });

        foreach ($tree_items as $item) {
            $name  = $item['name'] ?? '';
            $path  = $item['path'] ?? ($subpath !== '' ? "$subpath/$name" : $name);
            $ctype = $item['contentType'] ?? 'file';
            if ($name === '') continue;

            $is_dir = ($ctype === 'directory' || $ctype === 'submodule');
            $route  = $is_dir ? 'tree' : 'blob';
            $href   = "https://github.com/$owner/$rname/$route/$branch/" . ltrim($path, '/');

            $out_html .= '<tr>';
            $out_html .= '<td>' . ($is_dir ? '<b>[DIR]</b>' : '[FILE]') . '</td>';
            $out_html .= '<td><a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . ($is_dir ? '<b>' . htmlspecialchars($name . '/', ENT_QUOTES, 'UTF-8') . '</b>' : htmlspecialchars($name, ENT_QUOTES, 'UTF-8')) . '</a></td>';
            $out_html .= '</tr>';
        }
        $out_html .= '</table><br>';
    }

    // B. Render Source File Content (/blob/ route)
    if ($url_mode === 'blob' || $blob_data !== null) {
        // Link back to containing folder
        if ($subpath !== '') {
            $parent_parts = explode('/', trim($subpath, '/'));
            array_pop($parent_parts);
            $folder_href = empty($parent_parts)
                ? "https://github.com/$owner/$rname"
                : "https://github.com/$owner/$rname/tree/$branch/" . implode('/', $parent_parts);
            $out_html .= '<p><a href="' . htmlspecialchars($folder_href, ENT_QUOTES, 'UTF-8') . '"><b>&lt;&lt; Back to Folder</b></a></p>';
        }

        $code_lines = [];
        $rich_blob  = '';

        if (is_array($blob_data)) {
            if (!empty($blob_data['rawLines']) && is_array($blob_data['rawLines'])) {
                $code_lines = $blob_data['rawLines'];
            } elseif (!empty($blob_data['hlLines']) && is_array($blob_data['hlLines'])) {
                foreach ($blob_data['hlLines'] as $hl) {
                    $code_lines[] = html_entity_decode(strip_tags((string)$hl), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            } elseif (!empty($blob_data['richText']) && is_string($blob_data['richText'])) {
                $rich_blob = $blob_data['richText'];
            }
        }

        // Direct Fallback to raw.githubusercontent.com if GitHub deferred rawLines in the JSON payload
        if (empty($code_lines) && $rich_blob === '' && $subpath !== '') {
            $raw_file_url = "https://raw.githubusercontent.com/$owner/$rname/$branch/" . ltrim($subpath, '/');
            $raw_res = execute_single_http($raw_file_url, 'GET', null, $cookie_jar, $current_url, 10, 250000);
            if (!empty($raw_res['body'])) {
                $code_lines = preg_split("/\r\n|\n|\r/", $raw_res['body']);
            }
        }

        if (!empty($code_lines)) {
            $formatted_lines = [];
            $max_lines = min(count($code_lines), 1500);
            for ($i = 0; $i < $max_lines; $i++) {
                $num = str_pad((string)($i + 1), 4, ' ', STR_PAD_LEFT);
                $line_txt = str_replace("\t", '    ', $code_lines[$i]);
                $wrapped = wordwrap($line_txt, 82, "\n      ", true);
                $formatted_lines[] = $num . ': ' . $wrapped;
            }
            if (count($code_lines) > 1500) {
                $formatted_lines[] = "\n... [Truncated at 1500 lines to protect browser memory] ...";
            }

            $out_html .= '<table width="100%" bgcolor="#F8F8F8" border="1" bordercolor="#CCCCCC" cellpadding="6" cellspacing="0"><tr><td>';
            $out_html .= '<pre><font face="Courier New, Courier, monospace" size="2">' . htmlspecialchars(implode("\n", $formatted_lines), ENT_QUOTES, 'UTF-8') . '</font></pre>';
            $out_html .= '</td></tr></table><br>';
        } elseif ($rich_blob !== '') {
            $out_html .= '<table width="100%" bgcolor="#FFFFFF" border="1" bordercolor="#CCCCCC" cellpadding="8" cellspacing="0"><tr><td>';
            $out_html .= $rich_blob;
            $out_html .= '</td></tr></table><br>';
        }
    }

    // C. Append README / Overview HTML if available
    if ($readme_html !== '') {
        $out_html .= '<table width="100%" bgcolor="#FFFFFF" border="1" bordercolor="#CCCCCC" cellpadding="8" cellspacing="0">';
        $out_html .= '<tr bgcolor="#D4D0C8"><td><b>README</b></td></tr>';
        $out_html .= '<tr><td>' . $readme_html . '</td></tr></table><br>';
    }

    return $out_html;
}

$http_res = fetch_http_stateful($url, $request_method, $post_payload, $cookie_jar_file, $referer_url);
$html = $http_res['body'];
if (!empty($http_res['effective_url']) && filter_var($http_res['effective_url'], FILTER_VALIDATE_URL)) {
    $url = $http_res['effective_url'];
}

if (!$html) {
    die('<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><body bgcolor="#C0C0C0"><font face="Verdana" size="2"><b>Connection Error:</b> Failed to load ' . htmlspecialchars($url) . '</font></body></html>');
}

if (!mb_check_encoding($html, 'UTF-8')) {
    $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1, Windows-1252');
}

// Build GitHub repository/subfolder/blob HTML BEFORE DOMDocument strips or corrupts <script> payloads
$github_rendered_html = build_github_view_from_raw($html, $url, $cookie_jar_file);

$html = preg_replace('/<!DOCTYPE[^>]*>/i', '', $html);
$html = preg_replace('/<\?xml[^>]*\?>/i', '', $html);

libxml_use_internal_errors(true);
$dom = new DOMDocument('1.0', 'UTF-8');
$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
libxml_clear_errors();
$xpath = new DOMXPath($dom);

$page_title = 'Retro Web 800x600';
$title_nodes = $xpath->query('//title');
if ($title_nodes->length > 0) {
    $page_title = trim($title_nodes->item(0)->textContent) ?: $page_title;
}

// Extract Brand Palette and Strict Site Identity before stripping <head>
$palette  = extract_brand_palette($xpath, $url, $cookie_jar_file);
$identity = extract_site_identity($xpath, $url);

// If GitHub custom view was built, replace the noisy React body content with clean HTML 4.01 view
if ($github_rendered_html !== '') {
    $body_target = $dom->getElementsByTagName('body')->item(0);
    if ($body_target) {
        // Keep <header> and <nav> temporarily so Step 5 can extract top navigation links, then replace main content
        $main_nodes = $xpath->query('//main | //react-app | //react-partial | //table[@aria-labelledby="folders-and-files"]');
        remove_nodes($main_nodes);

        $gh_dom = new DOMDocument('1.0', 'UTF-8');
        @$gh_dom->loadHTML('<?xml encoding="UTF-8"><div id="gh-retro-root">' . $github_rendered_html . '</div>');
        $gh_root = $gh_dom->getElementById('gh-retro-root');
        if ($gh_root) {
            $imported = $dom->importNode($gh_root, true);
            if ($body_target->firstChild) {
                $body_target->insertBefore($imported, $body_target->firstChild);
            } else {
                $body_target->appendChild($imported);
            }
        }
    }
}

// 1. Purge incompatible tags using exact CSS token matching
$lixo = $xpath->query(
    '//head | //script | //style | //iframe | //canvas | //noscript | //meta | //link | ' .
    '//svg | //video | //audio | //object | //embed | //source | //template | //dialog | ' .
    '//*[@hidden] | //*[@aria-hidden="true" and not(self::table) and not(.//a)] | ' .
    '//*[contains(concat(" ", normalize-space(@class), " "), " sr-only ")] | ' .
    '//*[contains(concat(" ", normalize-space(@class), " "), " visually-hidden ")] | ' .
    '//*[contains(concat(" ", normalize-space(@class), " "), " d-none ")] | ' .
    '//*[contains(concat(" ", normalize-space(@class), " "), " hidden ")] | ' .
    '//*[contains(@style, "display:none")] | //*[contains(@style, "display: none")] | ' .
    '//*[contains(@id, "cookie-banner")] | //*[contains(@class, "cookie-banner")]'
);
remove_nodes($lixo);
remove_nodes($xpath->query('//comment()'));

// 2. Unwrap <picture> preserving inner <img>
$pictures = $xpath->query('//picture');
for ($i = $pictures->length - 1; $i >= 0; $i--) {
    $pic = $pictures->item($i);
    $imgs = $pic->getElementsByTagName('img');
    if ($imgs->length > 0 && $pic->parentNode) {
        $pic->parentNode->replaceChild($imgs->item(0)->cloneNode(true), $pic);
    } elseif ($pic->parentNode) {
        $pic->parentNode->removeChild($pic);
    }
}

// 3. Normalize <img> tags
$all_imgs = $xpath->query('//img');
for ($i = $all_imgs->length - 1; $i >= 0; $i--) {
    $img = $all_imgs->item($i);
    if (!($img instanceof DOMElement)) continue;

    $src = '';
    foreach (['src', 'data-src', 'data-original', 'data-lazy-src', 'data-srcset', 'srcset'] as $attr) {
        $val = trim($img->getAttribute($attr));
        if ($val !== '' && strpos($val, 'data:') !== 0) {
            if (strpos($attr, 'srcset') !== false) {
                $parts = preg_split('/[\s,]+/', $val);
                $val = $parts[0] ?? '';
            }
            if ($val !== '') {
                $src = $val;
                break;
            }
        }
    }

    $abs_src = resolve_url($url, $src);
    $img_path = strtolower(parse_url($abs_src, PHP_URL_PATH) ?? '');
    if (!$abs_src || substr($img_path, -4) === '.svg') {
        if ($img->parentNode) $img->parentNode->removeChild($img);
        continue;
    }

    $is_thumb = false;
    $p = $img->parentNode;
    while ($p && $p->nodeName !== 'body') {
        if ($p->nodeName === 'a') {
            $is_thumb = true;
            break;
        }
        $p = $p->parentNode;
    }

    $max_w = $is_thumb ? 130 : 520;
    $proxy_src = 'img_proxy.php?w=' . $max_w . '&url=' . urlencode($abs_src);

    clear_all_attributes($img);
    $img->setAttribute('src', $proxy_src);
    $img->setAttribute('border', '1');
    if ($is_thumb) {
        $img->setAttribute('hspace', '6');
        $img->setAttribute('vspace', '2');
    }
}

// 4. Rewrite <a> links (automatically unwraps DuckDuckGo ?uddg= and Google /url?q= via resolve_url)
$p_Param = $paginate ? '&p=1' : '';
$all_links = $xpath->query('//a');
for ($i = $all_links->length - 1; $i >= 0; $i--) {
    $a = $all_links->item($i);
    if (!($a instanceof DOMElement)) continue;

    $raw_href = $a->getAttribute('href');
    $abs_href = resolve_url($url, $raw_href);

    $text = trim(preg_replace('/\s+/', ' ', $a->textContent));
    $has_img = ($a->getElementsByTagName('img')->length > 0);

    if (!$abs_href || ($text === '' && !$has_img)) {
        if ($a->parentNode) $a->parentNode->removeChild($a);
        continue;
    }

    clear_all_attributes($a);
    $a->setAttribute('href', '?url=' . urlencode($abs_href) . $p_Param);
}

// 4.5. Form & Input Transpiler
$all_buttons = $xpath->query('//button');
for ($i = $all_buttons->length - 1; $i >= 0; $i--) {
    $btn = $all_buttons->item($i);
    if (!($btn instanceof DOMElement)) continue;

    $in_form = false;
    $p = $btn->parentNode;
    while ($p && $p->nodeName !== 'body') {
        if ($p->nodeName === 'form') {
            $in_form = true;
            break;
        }
        $p = $p->parentNode;
    }

    $btn_type = strtolower(trim($btn->getAttribute('type') ?: 'submit'));
    if (!$in_form || $btn_type === 'button') {
        if ($btn->parentNode) $btn->parentNode->removeChild($btn);
        continue;
    }

    $label = trim(preg_replace('/\s+/', ' ', $btn->textContent));
    if ($label === '') {
        $label = $btn->getAttribute('aria-label') ?: ($btn->getAttribute('title') ?: 'Submit');
    }

    $inp = $dom->createElement('input');
    $inp->setAttribute('type', $btn_type === 'reset' ? 'reset' : 'submit');
    $inp->setAttribute('value', $label);
    if ($btn->hasAttribute('name')) {
        $inp->setAttribute('name', $btn->getAttribute('name'));
    }

    if ($btn->parentNode) {
        $btn->parentNode->replaceChild($inp, $btn);
    }
}

$valid_input_types = ['text', 'password', 'checkbox', 'radio', 'submit', 'reset', 'hidden'];
$all_inputs = $xpath->query('//input');
for ($i = $all_inputs->length - 1; $i >= 0; $i--) {
    $inp = $all_inputs->item($i);
    if (!($inp instanceof DOMElement)) continue;

    $type = strtolower(trim($inp->getAttribute('type') ?: 'text'));
    if ($type === 'image') {
        $type = 'submit';
    } elseif ($type === 'button' || $type === 'file') {
        if ($inp->parentNode) $inp->parentNode->removeChild($inp);
        continue;
    } elseif (!in_array($type, $valid_input_types, true)) {
        $type = 'text';
    }

    $name = trim($inp->getAttribute('name'));
    $value = $inp->getAttribute('value');
    $checked = $inp->hasAttribute('checked');
    $size = (int)$inp->getAttribute('size');
    $maxlength = (int)$inp->getAttribute('maxlength');

    if ($name === '' && !in_array($type, ['submit', 'reset'], true)) {
        if ($inp->parentNode) $inp->parentNode->removeChild($inp);
        continue;
    }

    clear_all_attributes($inp);
    $inp->setAttribute('type', $type);
    if ($name !== '') {
        $inp->setAttribute('name', $name);
    }
    if ($value !== '') {
        $inp->setAttribute('value', $value);
    } elseif ($type === 'submit') {
        $inp->setAttribute('value', 'Submit');
    }
    if ($type === 'text' || $type === 'password') {
        $inp->setAttribute('size', ($size > 0 && $size <= 45) ? (string)$size : '25');
        if ($maxlength > 0) {
            $inp->setAttribute('maxlength', (string)$maxlength);
        }
    }
    if ($checked && ($type === 'checkbox' || $type === 'radio')) {
        $inp->setAttribute('checked', 'checked');
    }
}

$all_forms = $xpath->query('//form');
for ($i = $all_forms->length - 1; $i >= 0; $i--) {
    $form = $all_forms->item($i);
    if (!($form instanceof DOMElement)) continue;

    $raw_action = trim($form->getAttribute('action'));
    $abs_action = ($raw_action === '') ? $url : resolve_url($url, $raw_action);
    if (!$abs_action) $abs_action = $url;

    $method = strtoupper(trim($form->getAttribute('method') ?: 'GET'));
    if ($method !== 'POST') $method = 'GET';

    clear_all_attributes($form);
    $form->setAttribute('action', '');
    $form->setAttribute('method', $method);

    $h_action = $dom->createElement('input');
    $h_action->setAttribute('type', 'hidden');
    $h_action->setAttribute('name', '__proxy_action');
    $h_action->setAttribute('value', $abs_action);

    $h_method = $dom->createElement('input');
    $h_method->setAttribute('type', 'hidden');
    $h_method->setAttribute('name', '__proxy_method');
    $h_method->setAttribute('value', $method);

    $h_pag = $dom->createElement('input');
    $h_pag->setAttribute('type', 'hidden');
    $h_pag->setAttribute('name', '__proxy_p');
    $h_pag->setAttribute('value', (string)$paginate);

    $h_ref = $dom->createElement('input');
    $h_ref->setAttribute('type', 'hidden');
    $h_ref->setAttribute('name', '__proxy_ref');
    $h_ref->setAttribute('value', $url);

    if ($form->firstChild) {
        $form->insertBefore($h_ref, $form->firstChild);
        $form->insertBefore($h_pag, $form->firstChild);
        $form->insertBefore($h_method, $form->firstChild);
        $form->insertBefore($h_action, $form->firstChild);
    } else {
        $form->appendChild($h_action);
        $form->appendChild($h_method);
        $form->appendChild($h_pag);
        $form->appendChild($h_ref);
    }

    $submits = $xpath->query('.//input[@type="submit"]', $form);
    if ($submits->length === 0) {
        $sub = $dom->createElement('input');
        $sub->setAttribute('type', 'submit');
        $sub->setAttribute('value', 'Go');
        $form->appendChild($dom->createTextNode(' '));
        $form->appendChild($sub);
    }
}

// 5. Safe Navigation Extraction
$menu_items = [];
$seen_hrefs = [];
$extracted_forms = [];
$body_el = $dom->getElementsByTagName('body')->item(0);
$total_body_len = $body_el ? max(1, strlen($body_el->textContent)) : 1;

$nav_containers = $xpath->query(
    '//nav | //*[@role="navigation"] | ' .
    '//header[not(ancestor::article) and not(ancestor::main) and not(ancestor::section)]'
);

$safe_to_remove = [];
foreach ($nav_containers as $container) {
    if ($xpath->query('.//main | .//table', $container)->length > 0) {
        continue;
    }
    if ((strlen($container->textContent) / $total_body_len) > 0.35 && $github_rendered_html === '') {
        continue;
    }

    foreach ($xpath->query('.//form', $container) as $f) {
        $extracted_forms[] = $f->cloneNode(true);
    }
    foreach ($xpath->query('.//a', $container) as $a) {
        $href = $a->getAttribute('href');
        $label = trim(preg_replace('/\s+/', ' ', $a->textContent));
        if ($href && $label !== '' && mb_strlen($label, 'UTF-8') <= 35 && !isset($seen_hrefs[$href])) {
            $seen_hrefs[$href] = true;
            $menu_items[] = ['label' => $label, 'href' => $href];
        }
    }
    $safe_to_remove[] = $container;
}

for ($i = count($safe_to_remove) - 1; $i >= 0; $i--) {
    $node = $safe_to_remove[$i];
    if ($node && $node->parentNode) {
        $node->parentNode->removeChild($node);
    }
}

if ($body_el && !empty($extracted_forms) && $github_rendered_html === '') {
    $search_box = $extracted_forms[0];
    if ($body_el->firstChild) {
        $body_el->insertBefore($search_box, $body_el->firstChild);
    } else {
        $body_el->appendChild($search_box);
    }
}

// 6. Convert H1-H6 headings
foreach (['h1' => 4, 'h2' => 3, 'h3' => 3, 'h4' => 2, 'h5' => 2, 'h6' => 2] as $htag => $fsize) {
    $headers = $xpath->query('//' . $htag);
    for ($i = $headers->length - 1; $i >= 0; $i--) {
        $h = $headers->item($i);
        $txt = trim(preg_replace('/\s+/', ' ', $h->textContent));
        if ($txt === '' && $h->getElementsByTagName('img')->length === 0) {
            if ($h->parentNode) $h->parentNode->removeChild($h);
            continue;
        }
        $p = $dom->createElement('p');
        $font = $dom->createElement('font');
        $font->setAttribute('face', 'Verdana, Arial, sans-serif');
        $font->setAttribute('size', (string)$fsize);
        $font->setAttribute('color', $palette['link_color']);
        $b = $dom->createElement('b');

        while ($h->childNodes->length > 0) {
            $b->appendChild($h->childNodes->item(0));
        }
        $font->appendChild($b);
        $p->appendChild($font);
        if ($h->parentNode) {
            $h->parentNode->replaceChild($p, $h);
        }
    }
}

$articles = $xpath->query('//article');
for ($i = $articles->length - 1; $i >= 0; $i--) {
    $art = $articles->item($i);
    $txt = trim($art->textContent);
    if ($txt !== '' || $art->getElementsByTagName('img')->length > 0) {
        $hr = $dom->createElement('hr');
        $hr->setAttribute('size', '1');
        $hr->setAttribute('noshade', 'noshade');
        $art->appendChild($hr);
    }
}

// 7. Unwrap modern container tags
$unwrap_tags = [
    'main', 'section', 'article', 'figure', 'figcaption', 'footer', 'header', 'aside',
    'div', 'span', 'time', 'mark', 'details', 'summary', 'menu', 'fieldset', 'legend',
    'label', 'react-app', 'react-partial', 'turbo-frame'
];
foreach ($unwrap_tags as $tag) {
    $nodes = $xpath->query('//' . $tag);
    for ($i = $nodes->length - 1; $i >= 0; $i--) {
        $node = $nodes->item($i);
        $parent = $node->parentNode;
        if (!$parent) continue;

        $has_content = (trim($node->textContent) !== '' || $node->getElementsByTagName('img')->length > 0 || $node->getElementsByTagName('input')->length > 0);
        $is_block = in_array($tag, ['section', 'article', 'figure', 'div', 'fieldset', 'header', 'aside'], true);

        while ($node->childNodes->length > 0) {
            $parent->insertBefore($node->childNodes->item(0), $node);
        }
        if ($has_content && $is_block) {
            $parent->insertBefore($dom->createElement('br'), $node);
        }
        $parent->removeChild($node);
    }
}

// 8. Sanitize attributes and enforce <font> inside <td>, <th>, and <li>
$allowed_attrs = [
    'href', 'src', 'width', 'height', 'border', 'bordercolor', 'cellpadding', 'cellspacing',
    'bgcolor', 'align', 'valign', 'size', 'color', 'face', 'noshade',
    'hspace', 'vspace', 'colspan', 'rowspan',
    'action', 'method', 'type', 'name', 'value', 'checked', 'selected',
    'multiple', 'rows', 'cols', 'maxlength'
];

foreach ($xpath->query('//*') as $el) {
    if (!($el instanceof DOMElement)) continue;
    $to_remove = [];
    if ($el->hasAttributes()) {
        foreach ($el->attributes as $attr) {
            if (!in_array(strtolower($attr->name), $allowed_attrs, true)) {
                $to_remove[] = $attr->name;
            }
        }
    }
    foreach ($to_remove as $attr_name) {
        $el->removeAttribute($attr_name);
    }

    if (in_array($el->nodeName, ['td', 'th', 'li'], true) && $el->childNodes->length > 0) {
        // Avoid overriding Courier font inside <pre> blocks
        if ($el->getElementsByTagName('pre')->length === 0) {
            $font = $dom->createElement('font');
            $font->setAttribute('face', 'Verdana, Arial, sans-serif');
            $font->setAttribute('size', '2');
            while ($el->childNodes->length > 0) {
                $font->appendChild($el->childNodes->item(0));
            }
            $el->appendChild($font);
        }
    }
}

// 9. Serialize body nodes and apply Memory-Saving Pagination when enabled
$body_node = $dom->getElementsByTagName('body')->item(0);
$pages = [''];
if ($body_node) {
    $chunk_limit = 20000;
    $current_idx = 0;

    foreach ($body_node->childNodes as $child) {
        $fragment = $dom->saveHTML($child);
        if (trim($fragment) === '') continue;

        if ($paginate && strlen($pages[$current_idx]) > 0 && (strlen($pages[$current_idx]) + strlen($fragment)) > $chunk_limit) {
            $current_idx++;
            $pages[$current_idx] = '';
        }
        $pages[$current_idx] .= $fragment;
    }
}

$total_pages = count($pages);
if ($current_page > $total_pages) {
    $current_page = $total_pages;
}
$body_html = $pages[$current_page - 1] ?? '';

$body_html = preg_replace('/(<br\s*\/?>\s*){3,}/i', '<br><br>', $body_html);
$body_html = preg_replace('/(<hr[^>]*>\s*<br\s*\/?>\s*)+/i', '<hr size="1" noshade>', $body_html);

$top_limit = ($identity['logo_proxy'] !== '') ? 6 : 8;
$top_links  = array_slice($menu_items, 0, $top_limit);
$side_links = array_slice($menu_items, $top_limit, 35);

function render_pagination_bar($url, $current_page, $total_pages) {
    if ($total_pages <= 1) return '';
    $base_link = '?url=' . urlencode($url) . '&p=1&page=';
    $out = '<table width="100%" bgcolor="#EFEFEF" border="1" bordercolor="#CCCCCC" cellpadding="4" cellspacing="0"><tr><td align="center">';
    $out .= '<font face="Verdana, Arial, sans-serif" size="1">';
    if ($current_page > 1) {
        $out .= '<a href="' . htmlspecialchars($base_link . ($current_page - 1), ENT_QUOTES, 'UTF-8') . '"><b>&lt;&lt; Previous</b></a> | ';
    } else {
        $out .= '<font color="#888888">&lt;&lt; Previous</font> | ';
    }
    $out .= '<b>Page ' . $current_page . ' of ' . $total_pages . '</b>';

    $start = max(1, $current_page - 3);
    $end   = min($total_pages, $current_page + 3);
    $out .= ' ( ';
    for ($i = $start; $i <= $end; $i++) {
        if ($i === $current_page) {
            $out .= '<b>[' . $i . ']</b> ';
        } else {
            $out .= '<a href="' . htmlspecialchars($base_link . $i, ENT_QUOTES, 'UTF-8') . '">' . $i . '</a> ';
        }
    }
    $out .= ') | ';

    if ($current_page < $total_pages) {
        $out .= '<a href="' . htmlspecialchars($base_link . ($current_page + 1), ENT_QUOTES, 'UTF-8') . '"><b>Next &gt;&gt;</b></a>';
    } else {
        $out .= '<font color="#888888">Next &gt;&gt;</font>';
    }
    $out .= '</font></td></tr></table><br>';
    return $out;
}

function to_retro_charset($str) {
    return preg_replace_callback('/[\x{80}-\x{10FFFF}]/u', function ($m) {
        return '&#' . mb_ord($m[0], 'UTF-8') . ';';
    }, $str);
}

$pagination_html = $paginate ? render_pagination_bar($url, $current_page, $total_pages) : '';

ob_start();
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">
<title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
<style type="text/css">
body, td, th, p, li { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 10pt; color: #000000; }
pre, code, tt { font-family: Courier New, Courier, monospace; font-size: 9pt; }
a { color: <?php echo $palette['link_color']; ?>; text-decoration: underline; }
</style>
</head>
<body bgcolor="#C0C0C0" text="#000000" link="<?php echo $palette['link_color']; ?>" vlink="#660099" alink="#FF0000" topmargin="6" leftmargin="6" marginwidth="6" marginheight="6">

<!-- Unified Master Table (760px exact width) -->
<table width="760" align="center" bgcolor="#FFFFFF" border="1" bordercolor="#808080" cellpadding="0" cellspacing="0">

<!-- ROW 1: Browser Address Bar + Pagination Toggle + Cookie Reset + Web Search -->
<tr bgcolor="#D4D0C8">
<td colspan="2">
<table width="760" border="0" cellpadding="4" cellspacing="0">
<tr>
<td valign="top" width="510">
<form method="GET" action="" style="margin:0;">
<font face="Verdana, Arial, sans-serif" size="1"><b>Address:</b></font>
<input type="text" name="url" size="45" value="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
<input type="submit" value="Go"><br>
<input type="checkbox" name="p" value="1"<?php echo $paginate ? ' checked' : ''; ?>>
<font face="Verdana, Arial, sans-serif" size="1">Paginate (Save RAM)</font>
&nbsp;|&nbsp;
<a href="?url=<?php echo urlencode($url) . $p_Param; ?>&amp;clear_cookies=1"><font face="Verdana, Arial, sans-serif" size="1" color="#000000">Clear Cookies</font></a>
</form>
</td>
<td valign="top" align="right" width="250">
<form method="GET" action="" style="margin:0;">
<input type="hidden" name="__proxy_action" value="https://html.duckduckgo.com/html/">
<input type="hidden" name="__proxy_method" value="GET">
<input type="hidden" name="__proxy_p" value="<?php echo $paginate; ?>">
<input type="text" name="q" size="16" value="">
<input type="submit" value="Search">
</form>
</td>
</tr>
</table>
</td>
</tr>

<!-- ROW 2: Site Brand Bar -->
<tr bgcolor="<?php echo $palette['bar_bg']; ?>">
<td colspan="2">
<table width="760" border="0" cellpadding="5" cellspacing="0" bgcolor="<?php echo $palette['bar_bg']; ?>">
<tr>
<td valign="middle" align="left">
<?php if ($identity['logo_proxy'] === '' && $identity['favicon_proxy'] !== ''): ?>
<img src="<?php echo htmlspecialchars($identity['favicon_proxy'], ENT_QUOTES, 'UTF-8'); ?>" width="16" height="16" border="0" align="absmiddle" hspace="4">
<?php endif; ?>
<?php if (!empty($top_links)): ?>
<?php foreach ($top_links as $idx => $item): ?>
<a href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"><font face="Verdana, Arial, sans-serif" size="1" color="<?php echo $palette['bar_text']; ?>"><b><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></b></font></a><?php if ($idx < count($top_links) - 1): ?><font face="Verdana, Arial, sans-serif" size="1" color="<?php echo $palette['bar_sep']; ?>"> | </font><?php endif; ?>
<?php endforeach; ?>
<?php else: ?>
<font face="Verdana, Arial, sans-serif" size="1" color="<?php echo $palette['bar_text']; ?>"><b><?php echo htmlspecialchars(parse_url($url, PHP_URL_HOST) ?: $page_title, ENT_QUOTES, 'UTF-8'); ?></b></font>
<?php endif; ?>
</td>
<?php if ($identity['logo_proxy'] !== ''): ?>
<td valign="middle" align="right" width="210">
<a href="?url=<?php echo urlencode(($Parts = parse_url($url)) ? (($Parts['scheme'] ?? 'http') . '://' . ($Parts['host'] ?? '')) : $url) . $p_Param; ?>"><img src="<?php echo htmlspecialchars($identity['logo_proxy'], ENT_QUOTES, 'UTF-8'); ?>" border="0" alt="Logo"></a>
</td>
<?php endif; ?>
</tr>
</table>
</td>
</tr>

<!-- ROW 3: Main Content Area -->
<tr>
<?php if (!empty($side_links)): ?>
<td width="160" valign="top" bgcolor="#EFEFEF">
<table width="160" border="0" cellpadding="8" cellspacing="0">
<tr>
<td valign="top">
<font face="Verdana, Arial, sans-serif" size="2" color="<?php echo $palette['link_color']; ?>"><b>SECTIONS</b></font>
<hr size="1" noshade>
<?php foreach ($side_links as $item): ?>
<font face="Verdana, Arial, sans-serif" size="1" color="<?php echo $palette['link_color']; ?>">&#149;</font>
<a href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"><font face="Verdana, Arial, sans-serif" size="1" color="<?php echo $palette['link_color']; ?>"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></font></a><br><br>
<?php endforeach; ?>
</td>
</tr>
</table>
</td>
<?php endif; ?>

<td width="<?php echo !empty($side_links) ? '600' : '760'; ?>" valign="top" bgcolor="#FFFFFF">
<table width="100%" border="0" cellpadding="8" cellspacing="0">
<tr>
<td valign="top">
<font face="Verdana, Arial, sans-serif" size="2">
<?php echo $pagination_html; ?>
<?php echo $body_html; ?>
<?php echo $pagination_html; ?>
</font>
</td>
</tr>
</table>
</td>
</tr>

</table>

</body>
</html>
<?php
$final_output = ob_get_clean();
echo to_retro_charset($final_output);
?>
