<?php
/**
 * GET api/google-reviews.php — Google rating and reviews for the home page
 * carousel. Returns data: null when the API key isn't set or there is
 * nothing to show, and the page keeps the section hidden.
 */
declare(strict_types=1);

require __DIR__ . '/../backend/google-reviews.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(405, ['ok' => false, 'error' => 'Method not allowed.']);
}

try {
    $data = google_reviews();
} catch (Throwable $e) {
    error_log('Google reviews API failed: ' . $e->getMessage());
    $data = null;
}

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=600');
echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
