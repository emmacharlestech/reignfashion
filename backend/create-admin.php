<?php
/**
 * Create an admin account, or reset its password (command line only):
 *
 *   C:\xampp\php\php.exe backend\create-admin.php --email=owner@example.com --name="Owner Name"
 *
 * A strong random password is generated and printed once, unless you pass
 * --password=... (min. 10 characters). Running it again for the same email
 * resets that account's password — use this if the owner forgets it.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/bootstrap.php';

$o = getopt('', ['email:', 'name::', 'password::']);
$email = strtolower(trim($o['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php backend/create-admin.php --email=you@example.com [--name=\"Name\"] [--password=...]\n");
    exit(1);
}
$name = trim($o['name'] ?? '') ?: 'Admin';
$generated = !isset($o['password']);
$password = $generated
    ? implode('-', str_split(substr(strtr(base64_encode(random_bytes(18)), '+/=', 'xyz'), 0, 20), 5))
    : (string) $o['password'];

if (strlen($password) < 10) {
    fwrite(STDERR, "Password must be at least 10 characters.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = db()->prepare('INSERT INTO admins (email, name, password_hash) VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), name = VALUES(name)');
$stmt->execute([$email, $name, $hash]);

echo ($stmt->rowCount() === 1 ? 'Created' : 'Updated') . " admin: $email\n";
if ($generated) {
    echo "Password: $password\n(Shown once. Log in and change it under Account.)\n";
}
