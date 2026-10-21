<?php
/**
 * GET api/videos.php — published design videos for the "In Motion" section
 * (Home and Collections pages).
 */
declare(strict_types=1);

require __DIR__ . '/../backend/videos.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(405, ['ok' => false, 'error' => 'Method not allowed.']);
}

try {
    $items = array_map(fn($v) => [
        'category'      => $v['category'],
        'categoryLabel' => GALLERY_CATEGORIES[$v['category']] ?? '',
        'caption'       => $v['caption'],
        'src'           => $v['video_path'],
        'type'          => $v['mime'] === 'video/quicktime' ? 'video/mp4' : $v['mime'],   // MOV from phones is H.264; browsers play it as MP4
        'poster'        => $v['poster_path'],
        'duration'      => format_duration($v['duration_seconds'] !== null ? (int) $v['duration_seconds'] : null),
    ], design_videos(true));
} catch (Throwable $e) {
    error_log('Videos API failed: ' . $e->getMessage());
    json_response(500, ['ok' => false, 'error' => 'Videos unavailable.']);
}

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=60');
echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
