<?php
/**
 * Design videos ("In Motion"): clients wearing Reign Fashion designs.
 * Storage, uploads and queries. Videos and their cover images are saved under
 * assets/uploads/videos/, which only ever serves video and image files
 * (see assets/uploads/.htaccess).
 */
declare(strict_types=1);

require_once __DIR__ . '/gallery.php';          // SITE_ROOT, GALLERY_CATEGORIES

const VIDEO_UPLOAD_REL  = 'assets/uploads/videos';
const VIDEO_MAX_BYTES   = 60 * 1024 * 1024;       // our own ceiling; the server's PHP limits may be lower
const VIDEO_MAX_TITLE   = 120;
const VIDEO_MAX_CAPTION = 200;
const VIDEO_TYPES = [
    'video/mp4'       => 'mp4',
    'video/x-m4v'     => 'm4v',
    'video/quicktime' => 'mov',
    'video/webm'      => 'webm',
];

/** Create the table on first use, so the live site needs no manual SQL step. */
function ensure_video_table(): void
{
    static $done = false;
    if ($done) return;
    db()->exec("CREATE TABLE IF NOT EXISTS design_videos (
        id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title              VARCHAR(120) NOT NULL,
        category           VARCHAR(20)  NOT NULL,
        caption            VARCHAR(200) NOT NULL DEFAULT '',
        video_path         VARCHAR(255) NOT NULL,
        poster_path        VARCHAR(255) NULL,
        mime               VARCHAR(40)  NOT NULL,
        size_bytes         INT UNSIGNED NOT NULL,
        duration_seconds   SMALLINT UNSIGNED NULL,
        consent_confirmed  TINYINT(1)   NOT NULL,
        is_visible         TINYINT(1)   NOT NULL DEFAULT 1,
        sort_order         INT          NOT NULL DEFAULT 0,
        created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_visible_order (is_visible, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $done = true;
}

function design_videos(bool $publishedOnly = false): array
{
    ensure_video_table();
    $sql = 'SELECT * FROM design_videos' . ($publishedOnly ? ' WHERE is_visible = 1 AND consent_confirmed = 1' : '') . ' ORDER BY sort_order, id';
    return db()->query($sql)->fetchAll();
}

/** "64M" → bytes */
function ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '-1') return PHP_INT_MAX;
    $n = (int) $value;
    return match (strtolower(substr($value, -1))) {
        'g' => $n * 1024 ** 3,
        'm' => $n * 1024 ** 2,
        'k' => $n * 1024,
        default => $n,
    };
}

/** The largest video this server will actually accept. */
function video_upload_limit(): int
{
    return min(VIDEO_MAX_BYTES, ini_bytes((string) ini_get('upload_max_filesize')), ini_bytes((string) ini_get('post_max_size')) - 512 * 1024);
}

function format_mb(int $bytes): string
{
    return rtrim(rtrim(number_format($bytes / 1048576, 1), '0'), '.') . ' MB';
}

/** Validate the title/category/caption/permission fields. */
function video_fields(array $in): array
{
    $f = [
        'title'    => clean_line($in['title'] ?? '', VIDEO_MAX_TITLE),
        'category' => (string) ($in['category'] ?? ''),
        'caption'  => mb_substr(clean_line($in['caption'] ?? '', VIDEO_MAX_CAPTION + 20), 0, VIDEO_MAX_CAPTION),
        'consent'  => !empty($in['consent']) ? 1 : 0,
    ];
    if ($f['title'] === '') throw new RuntimeException('Please give the video a title, e.g. “Emerald Lace Aso-Ebi”.');
    if (!isset(GALLERY_CATEGORIES[$f['category']])) throw new RuntimeException('Please choose a category.');
    if (!$f['consent']) throw new RuntimeException('Please confirm the client agreed to this video being shown on the website.');
    return $f;
}

/**
 * Store an uploaded video (and the cover image the admin page grabbed from it).
 * Returns paths relative to the site root, or throws RuntimeException with a message for the owner.
 */
function store_video_upload(array $video, ?array $poster): array
{
    $limit = video_upload_limit();
    $err = $video['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Please choose a video to upload.');
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('That video is larger than ' . format_mb($limit) . '. Please trim it or use a shorter clip.');
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($video['tmp_name'])) throw new RuntimeException('The video could not be uploaded. Please try again.');
    if ($video['size'] > $limit) throw new RuntimeException('That video is larger than ' . format_mb($limit) . '. Please trim it or use a shorter clip.');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($video['tmp_name']) ?: '';
    if ($mime === 'application/octet-stream' && preg_match('/\.(mp4|m4v|mov)$/i', (string) $video['name'])) {
        // Some phones write MP4/MOV headers finfo doesn't recognise; check the "ftyp" box ourselves
        $head = (string) file_get_contents($video['tmp_name'], false, null, 4, 4);
        if ($head === 'ftyp') $mime = str_ends_with(strtolower($video['name']), '.mov') ? 'video/quicktime' : 'video/mp4';
    }
    if (!isset(VIDEO_TYPES[$mime])) throw new RuntimeException('Please upload an MP4, MOV or WebM video (the kind phones and WhatsApp save).');

    $dir = SITE_ROOT . '/' . VIDEO_UPLOAD_REL;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = date('Ymd') . '-' . bin2hex(random_bytes(6));
    $rel = VIDEO_UPLOAD_REL . "/$name." . VIDEO_TYPES[$mime];
    if (!move_uploaded_file($video['tmp_name'], SITE_ROOT . "/$rel")) throw new RuntimeException('The video could not be saved. Please try again.');

    $posterRel = null;
    if ($poster && ($poster['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file($poster['tmp_name']) && $poster['size'] <= 3 * 1024 * 1024) {
        $pmime = (new finfo(FILEINFO_MIME_TYPE))->file($poster['tmp_name']) ?: '';
        $ext = ['image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/png' => 'png'][$pmime] ?? null;
        if ($ext && @getimagesize($poster['tmp_name'])) {
            $posterRel = VIDEO_UPLOAD_REL . "/$name-cover.$ext";
            if (!move_uploaded_file($poster['tmp_name'], SITE_ROOT . "/$posterRel")) $posterRel = null;
        }
    }
    return ['video' => $rel, 'poster' => $posterRel, 'mime' => $mime, 'size' => (int) $video['size']];
}

/** Delete a stored video or cover, but only inside the videos folder. */
function delete_video_file(?string $rel): void
{
    if (!$rel || !str_starts_with($rel, VIDEO_UPLOAD_REL . '/')) return;
    $base = realpath(SITE_ROOT . '/' . VIDEO_UPLOAD_REL);
    $path = realpath(SITE_ROOT . '/' . $rel);
    if ($base && $path && str_starts_with($path, $base . DIRECTORY_SEPARATOR)) @unlink($path);
}

/** "1:05" from seconds */
function format_duration(?int $s): string
{
    return $s ? sprintf('%d:%02d', intdiv($s, 60), $s % 60) : '';
}
