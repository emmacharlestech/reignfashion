<?php
/**
 * One-click live settings synchronization.
 * Updates social media links and handles in the settings table.
 *
 * Usage:
 *   https://reignfashionempire.com/api/sync-settings.php?key=reign2026
 */
declare(strict_types=1);

require_once __DIR__ . '/../backend/settings.php';

header('Content-Type: application/json; charset=utf-8');

$key = $_GET['key'] ?? '';
$config = config();
$validKey = ($key === 'reign2026' || $key === ($config['app_secret'] ?? ''));

if (!$validKey) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid or missing synchronization key. Use ?key=reign2026']);
    exit;
}

try {
    $settings = [
        'instagram_url'  => 'https://www.instagram.com/reign_fashion_empire/',
        'instagram_name' => '@Reign-Fashion-Empire',
        'facebook_url'   => 'https://www.facebook.com/Reign-Fashion-Empire',
        'facebook_name'  => '@Reign-Fashion-Empire',
        'tiktok_url'     => 'https://www.tiktok.com/@reignfashionacademy',
        'tiktok_name'    => '@#Reign-Fashion-Academy Asaba',
    ];

    save_settings($settings);

    echo json_encode([
        'ok' => true,
        'message' => 'Live settings synchronized successfully!',
        'updated' => $settings,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ]);
}
