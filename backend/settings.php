<?php
/**
 * Site settings: one definition list drives the admin form, validation,
 * default values and the public JSON the website reads (api/settings.php).
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * key => [group, label, type, default, max length, help]
 * Types: text, phone, email, url:<domain|domain>, textarea
 */
const SETTING_DEFS = [
    // Contact
    'whatsapp'        => ['contact', 'WhatsApp number', 'phone', '0813 575 9554', 30, 'The number customers chat with. Local (0813…) or international (+234…) format.'],
    'phone_1'         => ['contact', 'Phone number 1', 'phone', '0813 575 9554', 30, 'Shown on the site as written.'],
    'phone_2'         => ['contact', 'Phone number 2', 'phone', '0907 171 7484', 30, 'Optional.'],
    'email'           => ['contact', 'Business email', 'email', 'reignfashionempire@gmail.com', 200, ''],
    'address'         => ['contact', 'Studio address', 'textarea', 'Anni Victor Plaza, Old Anwai Road, opposite Dwell Petrol Filling Station, Asaba, Delta State', 250, 'Also used for the "Get directions" map link.'],
    'hours'           => ['contact', 'Opening hours', 'text', '', 120, 'e.g. Mon–Sat, 9am–6pm. Leave empty to show "To be confirmed".'],
    // Social
    'instagram_url'   => ['social', 'Instagram profile link', 'url:instagram.com', '', 200, 'Copy from the browser address bar on the profile, e.g. https://www.instagram.com/yourname'],
    'instagram_name'  => ['social', 'Instagram account name', 'text', 'Reign Fashion Empire', 80, 'Shown as text until the link is added.'],
    'facebook_url'    => ['social', 'Facebook page link', 'url:facebook.com|fb.com', '', 200, 'e.g. https://www.facebook.com/yourpage'],
    'facebook_name'   => ['social', 'Facebook page name', 'text', 'Reign Fashion Empire', 80, ''],
    'tiktok_url'      => ['social', 'TikTok profile link', 'url:tiktok.com', '', 200, 'e.g. https://www.tiktok.com/@yourname'],
    'tiktok_name'     => ['social', 'TikTok account name', 'text', 'Reign Fashion Academy Asaba', 80, ''],
    // Academy
    'academy_duration'=> ['academy', 'Course duration', 'text', '', 120, 'e.g. 3 months (Beginner) to 12 months (Professional Mastery)'],
    'academy_fees'    => ['academy', 'Fees', 'text', '', 120, 'e.g. From ₦150,000, or "Available on enquiry"'],
    'academy_intake'  => ['academy', 'Next intake', 'text', '', 120, 'e.g. January 2027, or "Ongoing enrolment"'],
];

const SETTING_GROUPS = [
    'contact' => 'Contact details',
    'social'  => 'Social media',
    'academy' => 'Reign Fashion Academy',
];

/** All settings, with defaults for anything not yet saved. */
function get_settings(): array
{
    $values = array_map(fn($d) => $d[3], SETTING_DEFS);
    try {
        foreach (db()->query('SELECT `key`, `value` FROM settings') as $row) {
            if (array_key_exists($row['key'], $values)) {
                $values[$row['key']] = $row['value'];
            }
        }
    } catch (Throwable $e) {
        error_log('Settings read failed: ' . $e->getMessage());
    }
    return $values;
}

/** Digits for a tel: link / WhatsApp, converting Nigerian local 0XXXXXXXXXX to 234XXXXXXXXXX. */
function phone_international(string $phone): string
{
    $d = preg_replace('/\D/', '', $phone) ?? '';
    if (strlen($d) === 11 && $d[0] === '0') {
        $d = '234' . substr($d, 1);
    }
    return $d;
}

/**
 * Validate submitted values. Returns [cleanValues, errors].
 */
function validate_settings(array $input): array
{
    $clean = [];
    $errors = [];
    foreach (SETTING_DEFS as $key => [$group, $label, $type, $default, $max]) {
        $raw = $type === 'textarea'
            ? preg_replace('/\s+/', ' ', clean_text($input[$key] ?? '', $max)) ?? ''
            : clean_line($input[$key] ?? '', $max);
        $raw = trim($raw);
        $clean[$key] = $raw;
        if ($raw === '') {
            continue;                              // every setting may be left empty
        }

        if ($type === 'phone') {
            $digits = phone_international($raw);
            if (!preg_match('/^\+?[\d\s().-]+$/', $raw) || strlen($digits) < 8 || strlen($digits) > 15) {
                $errors[$key] = "$label doesn't look like a valid phone number.";
            }
        } elseif ($type === 'email') {
            if (!filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                $errors[$key] = "$label must be a valid email address.";
            }
        } elseif (str_starts_with($type, 'url:')) {
            $hosts = explode('|', substr($type, 4));
            $host = strtolower((string) parse_url($raw, PHP_URL_HOST));
            $hostOk = false;
            foreach ($hosts as $h) {
                if ($host === $h || str_ends_with($host, ".$h")) $hostOk = true;
            }
            if (!filter_var($raw, FILTER_VALIDATE_URL) || parse_url($raw, PHP_URL_SCHEME) !== 'https' || !$hostOk) {
                $errors[$key] = "$label must be a full https:// link on " . $hosts[0] . '.';
            }
        }
    }
    return [$clean, $errors];
}

function save_settings(array $clean): void
{
    $stmt = db()->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
    foreach ($clean as $key => $value) {
        $stmt->execute([$key, $value]);
    }
}

/** Shape used by the website's JavaScript (matches SITE_CONFIG in js/script.js). */
function public_settings(): array
{
    $s = get_settings();
    $phones = [];
    foreach (['phone_1', 'phone_2'] as $k) {
        if ($s[$k] !== '') {
            $phones[] = ['display' => $s[$k], 'tel' => '+' . phone_international($s[$k])];
        }
    }
    return [
        'whatsappNumber' => $s['whatsapp'] !== '' ? phone_international($s['whatsapp']) : '',
        'phones'  => $phones,
        'email'   => $s['email'],
        'address' => $s['address'],
        'hours'   => $s['hours'],
        'social'  => [
            'instagram' => ['url' => $s['instagram_url'], 'name' => $s['instagram_name']],
            'facebook'  => ['url' => $s['facebook_url'],  'name' => $s['facebook_name']],
            'tiktok'    => ['url' => $s['tiktok_url'],    'name' => $s['tiktok_name']],
        ],
        'academy' => [
            'duration' => $s['academy_duration'],
            'fees'     => $s['academy_fees'],
            'intake'   => $s['academy_intake'],
        ],
    ];
}
