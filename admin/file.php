<?php
/**
 * Streams a customer's inspiration image to a logged-in admin.
 * Uploads live in backend/storage/uploads, which browsers can't reach directly.
 */
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';

require_admin();

$stmt = db()->prepare('SELECT stored_path, mime, original_name FROM enquiry_files WHERE id = ?');
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$file = $stmt->fetch();

$base = realpath(UPLOAD_DIR);
$path = $file ? realpath(UPLOAD_DIR . '/' . $file['stored_path']) : false;

// Must exist, sit inside the uploads folder, and be one of the allowed image types
if (!$file || !$path || !$base || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)
    || !in_array($file['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(404);
    exit('Not found');
}

header('Content-Type: ' . $file['mime']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . preg_replace('/[^\w.-]/', '_', $file['original_name']) . '"');
header('Cache-Control: private, max-age=3600');
header("Content-Security-Policy: default-src 'none'; img-src 'self'");
readfile($path);
