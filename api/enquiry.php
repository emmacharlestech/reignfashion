<?php
/**
 * POST api/enquiry.php — receives the website enquiry form.
 *
 * Validates every field again on the server, stores the enquiry and any
 * inspiration images, then attempts an email notification. The enquiry
 * counts as received once it is saved, even if the email cannot be sent.
 *
 * Responses (JSON): 201 saved · 400 invalid · 405 wrong method · 429 too many · 500 error
 */
declare(strict_types=1);

require __DIR__ . '/../backend/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    json_response(405, ['ok' => false, 'error' => 'Method not allowed.']);
}

// Honeypot: real visitors never fill this hidden field. Pretend success for bots.
if (!empty($_POST['website'])) {
    json_response(201, ['ok' => true, 'ref' => null]);
}

/* ---------------------------------------------------------------- Validate */
$name    = clean_line($_POST['name'] ?? '', 120);
$email   = clean_line($_POST['email'] ?? '', 200);
$phone   = clean_line($_POST['phone'] ?? '', 40);
$method  = clean_line($_POST['contact_method'] ?? '', 20);
$service = clean_line($_POST['service'] ?? '', 40);
$date    = clean_line($_POST['event_date'] ?? '', 10);
$message = clean_text($_POST['message'] ?? '');
$consent = ($_POST['consent'] ?? '') === 'yes';

$errors = [];
if (mb_strlen($name) < 2)                       $errors['name'] = 'Please enter your full name.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Please enter a valid email address.';
if (!isset(CONTACT_METHODS[$method]))           $errors['contact_method'] = 'Please choose how you would like us to contact you.';
if (!isset(SERVICES[$service]))                 $errors['service'] = 'Please select a service.';
if (mb_strlen($message) < 10)                   $errors['message'] = 'Please tell us a little more about the outfit you have in mind.';
if (!$consent)                                  $errors['consent'] = 'Please confirm that we may use your details to respond.';

$digits = preg_replace('/\D/', '', $phone) ?? '';
if (in_array($method, ['phone', 'whatsapp'], true) && $phone === '') {
    $errors['phone'] = 'Please enter a phone number for this contact method.';
} elseif ($phone !== '' && (strlen($digits) < 7 || strlen($digits) > 15)) {
    $errors['phone'] = 'Please enter a valid phone number, including your country code.';
}

$eventDate = null;
if ($date !== '') {
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        $errors['event_date'] = 'Please enter a valid date.';
    } elseif ($d < new DateTime('today')) {
        $errors['event_date'] = 'Please choose a date that is today or later.';
    } else {
        $eventDate = $date;
    }
}

/* ----------------------------------------------------------------- Uploads */
$ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$uploads = [];
if (!empty($_FILES['inspiration']) && is_array($_FILES['inspiration']['name'])) {
    $f = $_FILES['inspiration'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    foreach ($f['name'] as $i => $origName) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        if (count($uploads) >= $CONFIG['max_files']) { $errors['inspiration'] = "Please send up to {$CONFIG['max_files']} images."; break; }
        if ($f['error'][$i] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'][$i])) { $errors['inspiration'] = 'An image could not be uploaded. Please try again.'; break; }
        if ($f['size'][$i] > $CONFIG['max_file_bytes']) { $errors['inspiration'] = 'Each image must be 5 MB or smaller.'; break; }
        $mime = $finfo->file($f['tmp_name'][$i]) ?: '';          // real type from file contents
        if (!isset($ALLOWED[$mime]) || @getimagesize($f['tmp_name'][$i]) === false) {
            $errors['inspiration'] = 'Images must be JPG, PNG or WEBP.'; break;
        }
        $uploads[] = ['tmp' => $f['tmp_name'][$i], 'name' => clean_line($origName, 255), 'mime' => $mime, 'ext' => $ALLOWED[$mime], 'size' => (int) $f['size'][$i]];
    }
}

if ($errors) {
    json_response(400, ['ok' => false, 'errors' => $errors]);
}

/* -------------------------------------------------------------- Rate limit */
$ipHash = visitor_hash();
try {
    $stmt = db()->prepare('SELECT COUNT(*) FROM enquiries WHERE ip_hash = ? AND created_at > NOW() - INTERVAL ? MINUTE');
    $stmt->execute([$ipHash, $CONFIG['rate_limit_minutes']]);
    if ((int) $stmt->fetchColumn() >= $CONFIG['rate_limit_count']) {
        json_response(429, ['ok' => false, 'error' => 'You have sent several enquiries in a short time. Please wait a few minutes, or contact us on WhatsApp.']);
    }
} catch (Throwable $e) {
    error_log('Enquiry rate-limit check failed: ' . $e->getMessage());
    json_response(500, ['ok' => false, 'error' => 'Sorry, your enquiry could not be saved. Please try again or use WhatsApp.']);
}

/* -------------------------------------------------------------------- Save */
$moved = [];
try {
    $pdo = db();
    $pdo->beginTransaction();

    $ins = $pdo->prepare('INSERT INTO enquiries
        (ref, name, email, phone, contact_method, service, event_date, message, consent_at, ip_hash, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)');
    $ins->execute([
        'tmp-' . bin2hex(random_bytes(6)), $name, $email, $phone ?: null, $method, $service,
        $eventDate, $message, $ipHash, clean_line($_SERVER['HTTP_USER_AGENT'] ?? '', 255),
    ]);
    $id  = (int) $pdo->lastInsertId();
    $ref = sprintf('RF-%s-%04d', date('ym'), $id);
    $pdo->prepare('UPDATE enquiries SET ref = ? WHERE id = ?')->execute([$ref, $id]);

    $sub = date('Y/m');
    if ($uploads && !is_dir(UPLOAD_DIR . "/$sub")) {
        mkdir(UPLOAD_DIR . "/$sub", 0750, true);
    }
    $fileIns = $pdo->prepare('INSERT INTO enquiry_files (enquiry_id, stored_path, original_name, mime, size_bytes) VALUES (?, ?, ?, ?, ?)');
    foreach ($uploads as $u) {
        $rel = "$sub/" . bin2hex(random_bytes(12)) . '.' . $u['ext'];   // random name, never the visitor's
        if (!move_uploaded_file($u['tmp'], UPLOAD_DIR . "/$rel")) {
            throw new RuntimeException('Could not store upload');
        }
        $moved[] = UPLOAD_DIR . "/$rel";
        $fileIns->execute([$id, $rel, $u['name'], $u['mime'], $u['size']]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    foreach ($moved as $path) @unlink($path);
    error_log('Enquiry save failed: ' . $e->getMessage());
    json_response(500, ['ok' => false, 'error' => 'Sorry, your enquiry could not be saved. Please try again or use WhatsApp.']);
}

/* ------------------------------------------- Email notification (optional) */
if (filter_var($CONFIG['mail_to'], FILTER_VALIDATE_EMAIL) && filter_var($CONFIG['mail_from'], FILTER_VALIDATE_EMAIL)) {
    $body = implode("\n", [
        "New enquiry $ref from the Reign Fashion Empire website",
        str_repeat('-', 48),
        "Name:              $name",
        "Email:             $email",
        'Phone:             ' . ($phone ?: '—'),
        'Preferred contact: ' . CONTACT_METHODS[$method],
        'Service:           ' . SERVICES[$service],
        'Occasion date:     ' . ($eventDate ?? '—'),
        'Images:            ' . count($uploads) . ' (view in the admin inbox)',
        '',
        'Message:',
        $message,
    ]);
    $subject = '=?UTF-8?B?' . base64_encode("New enquiry $ref: " . SERVICES[$service] . " — $name") . '?=';
    $headers = implode("\r\n", [
        'From: Reign Fashion Empire Website <' . $CONFIG['mail_from'] . '>',
        'Reply-To: ' . $email,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ]);
    if (@mail($CONFIG['mail_to'], $subject, chunk_split(base64_encode($body)), $headers)) {
        db()->prepare('UPDATE enquiries SET email_sent = 1 WHERE id = ?')->execute([$id]);
    } else {
        error_log("Enquiry $ref saved but notification email failed.");
    }
}

json_response(201, ['ok' => true, 'ref' => $ref]);
