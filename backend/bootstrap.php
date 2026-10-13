<?php
/**
 * Shared setup for every backend script: config, database, helpers.
 */
declare(strict_types=1);

if (PHP_VERSION_ID < 80000) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('This site needs PHP 8.0 or newer (the server is running PHP ' . PHP_VERSION . '). In cPanel, open "Select PHP Version" and choose 8.1 or 8.2.');
}

const BACKEND_DIR = __DIR__;
const UPLOAD_DIR  = __DIR__ . '/storage/uploads';

$configFile = BACKEND_DIR . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'The site backend is not installed yet.']);
    exit;
}

/** @var array $CONFIG */
$CONFIG = require $configFile;

ini_set('display_errors', $CONFIG['debug'] ? '1' : '0');
error_reporting(E_ALL);
date_default_timezone_set('Africa/Lagos');

/** Lazily-created PDO connection with safe defaults. */
function db(): PDO
{
    static $pdo = null;
    global $CONFIG;
    if ($pdo === null) {
        $c = $CONFIG['db'];
        $pdo = new PDO(
            "mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4",
            $c['user'],
            $c['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        $pdo->exec("SET time_zone = '+01:00'");
    }
    return $pdo;
}

function json_response(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($body);
    exit;
}

/**
 * Guarantee valid UTF-8. Browsers always send UTF-8, but text pasted through
 * some tools arrives in Windows-1252; convert it rather than lose it.
 */
function to_utf8(string $value): string
{
    return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
}

/** Trim, strip control characters (incl. CR/LF: blocks header injection), cap length. */
function clean_line(?string $value, int $max = 200): string
{
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', to_utf8((string) $value)) ?? '';
    return mb_substr(trim($value), 0, $max);
}

/** Like clean_line but keeps line breaks (for message bodies). */
function clean_text(?string $value, int $max = 5000): string
{
    $value = str_replace(["\r\n", "\r"], "\n", to_utf8((string) $value));
    $value = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', ' ', $value) ?? '';
    return mb_substr(trim($value), 0, $max);
}

/** Salted hash of the visitor's IP: lets us rate-limit without storing raw IPs. */
function visitor_hash(): string
{
    global $CONFIG;
    return hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown', $CONFIG['app_secret']);
}

/** Service keys accepted by the enquiry form, with display labels. */
const SERVICES = [
    'bridal'        => 'Bridal & Wedding Gowns',
    'aso-ebi'       => 'Aso-Ebi & Traditional Outfits',
    'ready-to-wear' => 'Ready-to-Wear',
    'kids'          => "Kiddies' Wear",
    'men'           => "Men's Wear",
    'custom'        => 'Custom Design',
    'academy'       => 'Fashion Academy (Training)',
    'other'         => 'Other Enquiries',
];

const CONTACT_METHODS = ['email' => 'Email', 'phone' => 'Phone call', 'whatsapp' => 'WhatsApp'];
