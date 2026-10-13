<?php
/**
 * POST api/academy-register.php — Reign Fashion Academy registration form.
 * Validates, stores the registration, then attempts an email notification.
 *
 * Responses (JSON): 201 saved · 400 invalid · 405 wrong method · 429 too many · 500 error
 */
declare(strict_types=1);

require __DIR__ . '/../backend/academy.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    json_response(405, ['ok' => false, 'error' => 'Method not allowed.']);
}

// Honeypot: real visitors never fill this hidden field
if (!empty($_POST['website'])) {
    json_response(201, ['ok' => true, 'ref' => null]);
}

$name       = clean_line($_POST['name'] ?? '', 120);
$email      = clean_line($_POST['email'] ?? '', 200);
$phone      = clean_line($_POST['phone'] ?? '', 40);
$level      = clean_line($_POST['level'] ?? '', 20);
$schedule   = clean_line($_POST['schedule'] ?? '', 20);
$experience = clean_text($_POST['experience'] ?? '', 1500);
$consent    = ($_POST['consent'] ?? '') === 'yes';

$errors = [];
if (mb_strlen($name) < 2)                       $errors['name'] = 'Please enter your full name.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Please enter a valid email address.';
$digits = preg_replace('/\D/', '', $phone) ?? '';
if ($phone === '')                              $errors['phone'] = 'Please enter a phone number so we can reach you.';
elseif (strlen($digits) < 7 || strlen($digits) > 15) $errors['phone'] = 'Please enter a valid phone number.';
if (!isset(ACADEMY_LEVELS[$level]))             $errors['level'] = 'Please choose the level that suits you.';
if (!isset(ACADEMY_SCHEDULES[$schedule]))       $errors['schedule'] = 'Please choose when you would prefer to attend.';
if (!$consent)                                  $errors['consent'] = 'Please confirm that we may use your details to contact you.';

if ($errors) {
    json_response(400, ['ok' => false, 'errors' => $errors]);
}

$ipHash = visitor_hash();
try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM academy_registrations WHERE ip_hash = ? AND created_at > NOW() - INTERVAL ? MINUTE');
    $stmt->execute([$ipHash, $CONFIG['rate_limit_minutes']]);
    if ((int) $stmt->fetchColumn() >= $CONFIG['rate_limit_count']) {
        json_response(429, ['ok' => false, 'error' => 'You have sent several registrations in a short time. Please wait a few minutes, or contact us on WhatsApp.']);
    }

    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO academy_registrations (ref, name, email, phone, level, schedule, experience, consent_at, ip_hash)
                   VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)')
        ->execute(['tmp-' . bin2hex(random_bytes(6)), $name, $email, $phone, $level, $schedule, $experience !== '' ? $experience : null, $ipHash]);
    $id  = (int) $pdo->lastInsertId();
    $ref = sprintf('RA-%s-%04d', date('ym'), $id);
    $pdo->prepare('UPDATE academy_registrations SET ref = ? WHERE id = ?')->execute([$ref, $id]);
    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Academy registration failed: ' . $e->getMessage());
    json_response(500, ['ok' => false, 'error' => 'Sorry, your registration could not be saved. Please try again or contact us on WhatsApp.']);
}

// Email notification (only when a sender address on the site's domain is configured)
if (filter_var($CONFIG['mail_to'], FILTER_VALIDATE_EMAIL) && filter_var($CONFIG['mail_from'], FILTER_VALIDATE_EMAIL)) {
    $body = implode("\n", [
        "New Academy registration $ref",
        str_repeat('-', 40),
        "Name:      $name",
        "Email:     $email",
        "Phone:     $phone",
        'Level:     ' . ACADEMY_LEVELS[$level],
        'Schedule:  ' . ACADEMY_SCHEDULES[$schedule],
        '',
        'Experience / goals:',
        $experience !== '' ? $experience : '—',
    ]);
    $headers = implode("\r\n", [
        'From: Reign Fashion Empire Website <' . $CONFIG['mail_from'] . '>',
        'Reply-To: ' . $email,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ]);
    $subject = '=?UTF-8?B?' . base64_encode("New Academy registration $ref — $name") . '?=';
    if (@mail($CONFIG['mail_to'], $subject, chunk_split(base64_encode($body)), $headers)) {
        db()->prepare('UPDATE academy_registrations SET email_sent = 1 WHERE id = ?')->execute([$id]);
    }
}

json_response(201, ['ok' => true, 'ref' => $ref]);
