<?php
/**
 * GET api/gallery.php — visible gallery items for the website, in display order.
 */
declare(strict_types=1);

require __DIR__ . '/../backend/gallery.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(405, ['ok' => false, 'error' => 'Method not allowed.']);
}

try {
    $items = array_map(fn($r) => [
        'id'            => (int) $r['id'],
        'title'         => $r['title'],
        'alt'           => $r['alt'],
        'category'      => $r['category'],
        'categoryLabel' => GALLERY_CATEGORIES[$r['category']] ?? $r['category'],
        'size'          => $r['size'],
        'src'           => $r['src'],
        'width'         => (int) $r['width'],
        'height'        => (int) $r['height'],
        'focus'         => $r['focus'],
        'isSample'      => (bool) $r['is_sample'],
    ], gallery_items(true));
} catch (Throwable $e) {
    error_log('Gallery API failed: ' . $e->getMessage());
    json_response(500, ['ok' => false, 'error' => 'Gallery unavailable.']);
}

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=60');
echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
