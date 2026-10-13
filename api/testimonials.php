<?php
/**
 * GET api/testimonials.php — published testimonials for the website carousel.
 */
declare(strict_types=1);

require __DIR__ . '/../backend/testimonials.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(405, ['ok' => false, 'error' => 'Method not allowed.']);
}

try {
    $items = array_map(fn($t) => [
        'quote'        => $t['quote'],
        'name'         => $t['client_name'],
        'serviceLabel' => TESTIMONIAL_SERVICES[$t['service']] ?? '',
    ], testimonials(true));
} catch (Throwable $e) {
    error_log('Testimonials API failed: ' . $e->getMessage());
    json_response(500, ['ok' => false, 'error' => 'Testimonials unavailable.']);
}

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=60');
echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
