<?php
/**
 * Gallery ("Selected Creations"): categories, upload processing, queries.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const SITE_ROOT          = __DIR__ . '/..';
const GALLERY_UPLOAD_REL = 'assets/uploads/gallery';      // public, images only (see assets/uploads/.htaccess)
const GALLERY_MAX_BYTES  = 8 * 1024 * 1024;
const GALLERY_MAX_EDGE   = 1600;                            // longest side after processing

const GALLERY_CATEGORIES = [
    'bridal'        => 'Bridal',
    'aso-ebi'       => 'Aso-Ebi',
    'ready-to-wear' => 'Ready-to-Wear',
    'kids'          => 'Kiddies',
    'men'           => 'Men',
];

const GALLERY_SIZES = [
    'standard' => 'Standard',
    'tall'     => 'Tall (portrait, 2 rows)',
    'wide'     => 'Wide (landscape, 2 columns)',
];

/** Which part of the photo stays visible when it is cropped to the tile. */
const GALLERY_FOCUS = [
    '50% 15%' => 'Top (faces, gele)',
    '50% 30%' => 'Upper middle',
    '50% 50%' => 'Centre',
    '50% 75%' => 'Lower (hems, trains)',
];

/** Gallery items in display order. */
function gallery_items(bool $visibleOnly = false): array
{
    $sql = 'SELECT * FROM gallery_items' . ($visibleOnly ? ' WHERE is_visible = 1' : '') . ' ORDER BY sort_order, id';
    return db()->query($sql)->fetchAll();
}

/**
 * Validate and store an uploaded gallery image.
 * Returns ['src' => ..., 'width' => ..., 'height' => ...] or throws RuntimeException with a user-facing message.
 */
function store_gallery_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Please choose a photo to upload.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The photo could not be uploaded. Please try again.');
    }
    if ($file['size'] > GALLERY_MAX_BYTES) {
        throw new RuntimeException('That photo is larger than 8 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $info = @getimagesize($file['tmp_name']);
    if (!isset($types[$mime]) || $info === false || $info[0] < 300 || $info[1] < 300) {
        throw new RuntimeException('Please upload a JPG, PNG or WebP photo at least 300 × 300 pixels.');
    }
    if ($info[0] > 8000 || $info[1] > 8000) {
        throw new RuntimeException('That photo is too large (over 8000 pixels on a side).');
    }

    $dir = SITE_ROOT . '/' . GALLERY_UPLOAD_REL;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(6));

    // With GD: re-encode (resizes, strips metadata and anything hidden in the file)
    if (extension_loaded('gd')) {
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
            'image/png'  => @imagecreatefrompng($file['tmp_name']),
            'image/webp' => @imagecreatefromwebp($file['tmp_name']),
        };
        if (!$src) {
            throw new RuntimeException('That photo could not be read. Please try a different file.');
        }
        [$w, $h] = [imagesx($src), imagesy($src)];
        $scale = min(1, GALLERY_MAX_EDGE / max($w, $h));
        [$nw, $nh] = [(int) round($w * $scale), (int) round($h * $scale)];
        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $useWebp = function_exists('imagewebp');
        $rel = GALLERY_UPLOAD_REL . "/$name." . ($useWebp ? 'webp' : 'jpg');
        $ok = $useWebp ? imagewebp($dst, SITE_ROOT . "/$rel", 82) : imagejpeg($dst, SITE_ROOT . "/$rel", 85);
        imagedestroy($src);
        imagedestroy($dst);
        if (!$ok) {
            throw new RuntimeException('The photo could not be saved. Please try again.');
        }
        return ['src' => $rel, 'width' => $nw, 'height' => $nh];
    }

    // Without GD: the admin page already resized the photo in the browser; store it as-is
    // after the content checks above.
    $rel = GALLERY_UPLOAD_REL . "/$name." . $types[$mime];
    if (!move_uploaded_file($file['tmp_name'], SITE_ROOT . "/$rel")) {
        throw new RuntimeException('The photo could not be saved. Please try again.');
    }
    return ['src' => $rel, 'width' => $info[0], 'height' => $info[1]];
}

/** Delete an image file, but only if it is one we uploaded (never the site's own assets). */
function delete_gallery_file(string $src): void
{
    if (!str_starts_with($src, GALLERY_UPLOAD_REL . '/')) {
        return;
    }
    $base = realpath(SITE_ROOT . '/' . GALLERY_UPLOAD_REL);
    $path = realpath(SITE_ROOT . '/' . $src);
    if ($base && $path && str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
        @unlink($path);
    }
}

/**
 * One-time import of the tiles currently hard-coded in index.html, so the
 * owner can manage them from the admin area. Runs only if the table is empty.
 */
function seed_gallery_from_html(): int
{
    if ((int) db()->query('SELECT COUNT(*) FROM gallery_items')->fetchColumn() > 0) {
        return 0;
    }
    $html = @file_get_contents(SITE_ROOT . '/index.html');
    if (!$html || !preg_match('/<ul class="gallery__grid"[^>]*>(.*?)<\/ul>/s', $html, $m)) {
        return 0;
    }
    preg_match_all('/<li class="g-item(?: g-item--(tall|wide))?" data-category="([\w-]+)">(.*?)<\/li>/s', $m[1], $tiles, PREG_SET_ORDER);

    $ins = db()->prepare('INSERT INTO gallery_items (title, alt, category, size, src, width, height, focus, is_sample, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $order = 0;
    foreach ($tiles as [, $size, $category, $body]) {
        if (!preg_match('/<img src="([^"]+)" alt="([^"]*)" width="(\d+)" height="(\d+)"/', $body, $img)) {
            continue;
        }
        preg_match('/object-position:\s*([^"]+)"/', $body, $pos);
        preg_match('/<span class="g-item__title">([^<]+)<\/span>/', $body, $title);
        $isSample = str_contains($body, 'is-sample') ? 1 : 0;
        $alt = html_entity_decode(preg_replace('/^Sample image:\s*/', '', $img[2]), ENT_QUOTES);
        $ins->execute([
            html_entity_decode($title[1] ?? 'Design', ENT_QUOTES), $alt, $category, $size ?: 'standard',
            $img[1], (int) $img[3], (int) $img[4], trim($pos[1] ?? '50% 30%'), $isSample, $order += 10,
        ]);
    }
    return $order / 10;
}
