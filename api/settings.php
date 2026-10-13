<?php
/**
 * GET api/settings.php — public site settings for the website's JavaScript.
 * Only information already meant to be public (contact details, social links,
 * Academy info) is returned.
 */
declare(strict_types=1);

require __DIR__ . '/../backend/settings.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(405, ['ok' => false, 'error' => 'Method not allowed.']);
}

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=60');     // changes show within a minute
echo json_encode(['ok' => true, 'settings' => public_settings()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
