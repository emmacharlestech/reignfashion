<?php
/**
 * One-click live settings and admin synchronization.
 * Updates social media links and syncs/resets admin password.
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
    // 1. Update social settings in database
    $settings = [
        'instagram_url'  => 'https://www.instagram.com/reign_fashion_empire/',
        'instagram_name' => '@Reign-Fashion-Empire',
        'facebook_url'   => 'https://www.facebook.com/Reign-Fashion-Empire',
        'facebook_name'  => '@Reign-Fashion-Empire',
        'tiktok_url'     => 'https://www.tiktok.com/@reignfashionacademy',
        'tiktok_name'    => '@#Reign-Fashion-Academy Asaba',
    ];

    save_settings($settings);

    // 2. Set/Reset Admin password to reignfashion@@$$
    $adminEmail = 'reignfashionempire@gmail.com';
    $adminPass  = 'reignfashion@@$$';
    $adminHash  = password_hash($adminPass, PASSWORD_DEFAULT);

    $stmt = db()->prepare('INSERT INTO admins (email, name, password_hash) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), name = VALUES(name)');
    $stmt->execute([$adminEmail, 'Reign Fashion', $adminHash]);

    // 3. Clear failed login attempts to prevent lock-out
    db()->exec('DELETE FROM login_attempts');

    echo json_encode([
        'ok' => true,
        'message' => 'Live settings and admin credentials synchronized successfully!',
        'admin_email' => $adminEmail,
        'admin_password' => $adminPass,
        'social_settings' => $settings,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ]);
}
