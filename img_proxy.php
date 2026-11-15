<?php
error_reporting(0);

$url    = isset($_GET['url']) ? trim($_GET['url']) : '';
$max_w  = isset($_GET['w']) ? (int)$_GET['w'] : 300;
$max_h  = isset($_GET['h']) ? (int)$_GET['h'] : 0;
$is_fav = !empty($_GET['fav']);

if ($max_w < 12 || $max_w > 740) $max_w = 300;
if ($max_h < 0  || $max_h > 600) $max_h = 300;

function send_fallback_gif($is_favicon = false) {
    if ($is_favicon) {
        // Generates a clean 16x16 retro globe/page icon if remote favicon fails
        $im = imagecreatetruecolor(16, 16);
        $bg = imagecolorallocate($im, 255, 255, 255);
        $bd = imagecolorallocate($im, 0, 51, 102);
        $ac = imagecolorallocate($im, 0, 102, 204);
        imagefilledrectangle($im, 0, 0, 15, 15, $bg);
        imagerectangle($im, 1, 1, 14, 14, $bd);
        imagefilledellipse($im, 8, 8, 9, 9, $ac);
        imagerectangle($im, 3, 7, 12, 8, $bg);
        imagerectangle($im, 7, 3, 8, 12, $bg);
        header('Content-Type: image/jpeg');
        imagejpeg($im, null, 60);
        imagedestroy($im);
        exit;
    }
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

// Extracts an image resource from a Windows .ICO binary container
function parse_ico_to_gd($data) {
    if (strlen($data) < 22) return false;
    $header = unpack('vreserved/vtype/vcount', substr($data, 0, 6));
    if ($header['reserved'] !== 0 || $header['type'] !== 1 || $header['count'] < 1) {
        return false;
    }

    $offset = 6;
    for ($i = 0; $i < $header['count']; $i++) {
        if ($offset + 16 > strlen($data)) break;
        $entry = unpack('Cwidth/Cheight/Ccolors/Creserved/vplanes/vbpp/Vsize/Voffset', substr($data, $offset, 16));
        $offset += 16;

        $slice = substr($data, $entry['offset'], $entry['size']);
        if (!$slice) continue;

        // 1. Check if ICO entry embeds a direct PNG stream
        if (substr($slice, 0, 8) === "\x89PNG\r\n\x1a\n") {
            $im = @imagecreatefromstring($slice);
            if ($im) return $im;
        }

        // 2. Reconstruct 14-byte BITMAPFILEHEADER for standard 40-byte DIB BMP entries
        if (strlen($slice) > 40 && unpack('V', substr($slice, 0, 4))[1] === 40) {
            $biHeight = unpack('l', substr($slice, 8, 4))[1];
            $bpp      = unpack('v', substr($slice, 14, 2))[1];
            $clrUsed  = unpack('V', substr($slice, 32, 4))[1];

            // Halve height in DIB header (ICO stores XOR + AND mask height combined)
            $real_h = (int)($biHeight / 2);
            $patched_dib = substr($slice, 0, 8) . pack('l', $real_h) . substr($slice, 12);

            $palette_size = 0;
            if ($bpp <= 8) {
                $palette_size = ($clrUsed > 0 ? $clrUsed : (1 << $bpp)) * 4;
            }
            $pixel_offset = 14 + 40 + $palette_size;
            $bmp_file = 'BM' . pack('VvvV', 14 + strlen($patched_dib), 0, 0, $pixel_offset) . $patched_dib;

            $im = @imagecreatefromstring($bmp_file);
            if ($im) return $im;
        }
    }
    return false;
}

if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    send_fallback_gif($is_fav);
}

$ctx = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 8,
        'follow_location' => 1,
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n" .
                    "Accept: image/png,image/jpeg,image/gif,image/webp,image/x-icon,*/*;q=0.8\r\n"
    ],
    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
]);

$raw = @file_get_contents($url, false, $ctx);
if (!$raw) {
    send_fallback_gif($is_fav);
}

$src_img = @imagecreatefromstring($raw);
if (!$src_img) {
    $src_img = parse_ico_to_gd($raw);
}
if (!$src_img) {
    send_fallback_gif($is_fav);
}

$orig_w = imagesx($src_img);
$orig_h = imagesy($src_img);

if (!$is_fav && ($orig_w <= 2 || $orig_h <= 2)) {
    imagedestroy($src_img);
    send_fallback_gif(false);
}

$scale = 1.0;
if ($orig_w > $max_w) {
    $scale = min($scale, $max_w / $orig_w);
}
if ($max_h > 0 && $orig_h > $max_h) {
    $scale = min($scale, $max_h / $orig_h);
}

// Force exact 16x16 box for favicons
if ($is_fav) {
    $new_w = 16;
    $new_h = 16;
} else {
    $new_w = max(1, (int)round($orig_w * $scale));
    $new_h = max(1, (int)round($orig_h * $scale));
}

$dst_img = imagecreatetruecolor($new_w, $new_h);
$white = imagecolorallocate($dst_img, 255, 255, 255);
imagefilledrectangle($dst_img, 0, 0, $new_w, $new_h, $white);

imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
imageinterlace($dst_img, 0);

header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=86400');
imagejpeg($dst_img, null, 60);

imagedestroy($src_img);
imagedestroy($dst_img);
?>
