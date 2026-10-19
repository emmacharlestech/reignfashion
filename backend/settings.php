<?php
/**
 * Site settings: one definition list drives the admin form, validation,
 * default values and the public JSON the website reads (api/settings.php).
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * key => [group, label, type, default, max length, help]
 * Types: text, phone, email, url:<domain|domain>, textarea, account (10-digit NUBAN),
 *        choices:<key=Label|…> (tick boxes, saved comma-separated), select:<key=Label|…>
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
    // Payment
    'payment_methods' => ['payment', 'Ways customers can pay', 'choices:transfer=Bank transfer|cash=Cash at the studio|pos=Card / POS at the studio', 'transfer,cash,pos', 60, 'Tick every option you accept. Shown on the Contact page.'],
    'payment_terms'   => ['payment', 'Payment terms', 'textarea', '', 300, 'e.g. A 50% deposit confirms your order; the balance is paid before collection.'],
    'bank_1_bank'     => ['payment', 'Account 1: bank', 'text', '', 60, 'e.g. Access Bank, GTBank, Moniepoint, OPay'],
    'bank_1_name'     => ['payment', 'Account 1: account name', 'text', '', 100, 'Exactly as the bank shows it, so customers can check before paying.'],
    'bank_1_number'   => ['payment', 'Account 1: account number', 'account', '', 10, '10 digits.'],
    'bank_2_bank'     => ['payment', 'Account 2: bank (optional)', 'text', '', 60, 'A second account, e.g. for Academy fees.'],
    'bank_2_name'     => ['payment', 'Account 2: account name', 'text', '', 100, ''],
    'bank_2_number'   => ['payment', 'Account 2: account number', 'account', '', 10, '10 digits.'],
    'bank_display'    => ['payment', 'Where bank details appear', 'select:public=On the website (Contact page) and in the admin|private=Only in the admin, for sending to customers yourself', 'public', 10, 'Either way, each enquiry and registration has a button to send the details on WhatsApp.'],
    // Email alerts
    'alerts'          => ['alerts', 'Email alerts', 'select:on=Email me about every new enquiry and registration|off=No emails: I check the admin myself', 'on', 10, ''],
    'alert_email'     => ['alerts', 'Send alerts to', 'email', '', 200, 'Leave empty to use the business email above.'],
];

const SETTING_GROUPS = [
    'contact' => 'Contact details',
    'social'  => 'Social media',
    'academy' => 'Reign Fashion Academy',
    'payment' => 'Payment',
    'alerts'  => 'Email alerts',
];

/** Parse "key=Label|key=Label" from a choices:/select: type. */
function setting_options(string $type): array
{
    $options = [];
    foreach (explode('|', substr($type, strpos($type, ':') + 1)) as $pair) {
        [$k, $label] = explode('=', $pair, 2);
        $options[$k] = $label;
    }
    return $options;
}

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
        if (str_starts_with($type, 'choices:')) {
            $picked = is_array($input[$key] ?? null) ? $input[$key] : [];
            $clean[$key] = implode(',', array_keys(array_intersect_key(setting_options($type), array_flip($picked))));
            continue;
        }
        if (str_starts_with($type, 'select:')) {
            $value = is_string($input[$key] ?? null) ? $input[$key] : '';
            $clean[$key] = isset(setting_options($type)[$value]) ? $value : $default;
            continue;
        }
        $raw = $type === 'textarea'
            ? preg_replace('/\s+/', ' ', clean_text($input[$key] ?? '', $max)) ?? ''
            : clean_line($input[$key] ?? '', $type === 'account' ? 20 : $max);   // room for spaces, stripped below
        $raw = trim($raw);
        if ($type === 'account') {
            $raw = preg_replace('/[\s-]+/', '', $raw) ?? '';   // "0123 456 789" → "0123456789"
        }
        $clean[$key] = $raw;
        if ($raw === '') {
            continue;                              // every setting may be left empty
        }

        if ($type === 'account') {
            if (!preg_match('/^\d{10}$/', $raw)) {
                $errors[$key] = "$label must be exactly 10 digits.";
            }
        } elseif ($type === 'phone') {
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

    // A bank account is all-or-nothing: a half-filled one would send money astray.
    foreach ([1, 2] as $n) {
        $parts = ["bank_{$n}_bank", "bank_{$n}_name", "bank_{$n}_number"];
        $filled = array_filter($parts, fn($k) => $clean[$k] !== '');
        if ($filled && count($filled) < 3) {
            foreach (array_diff($parts, $filled) as $k) {
                $errors[$k] ??= "Please fill in the bank, account name and account number for account $n (or clear all three).";
            }
        }
    }
    return [$clean, $errors];
}

/** Complete bank accounts, in order: [['bank' =>, 'name' =>, 'number' =>], …] */
function bank_accounts(?array $s = null): array
{
    $s ??= get_settings();
    $accounts = [];
    foreach ([1, 2] as $n) {
        $a = ['bank' => $s["bank_{$n}_bank"], 'name' => $s["bank_{$n}_name"], 'number' => $s["bank_{$n}_number"]];
        if ($a['bank'] !== '' && $a['name'] !== '' && $a['number'] !== '') {
            $accounts[] = $a;
        }
    }
    return $accounts;
}

/** Labels of the payment methods the owner ticked. */
function payment_method_labels(?array $s = null): array
{
    $s ??= get_settings();
    $options = setting_options(SETTING_DEFS['payment_methods'][2]);
    $picked = array_filter(explode(',', $s['payment_methods']));
    return array_values(array_intersect_key($options, array_flip($picked)));
}

/**
 * Plain-text payment details for the admin to send a customer (WhatsApp/email).
 * Returns '' when no bank account has been saved yet.
 */
function payment_message(string $ref = ''): string
{
    $s = get_settings();
    $accounts = bank_accounts($s);
    if (!$accounts) {
        return '';
    }
    $lines = ['Payment details for Reign Fashion Empire:'];
    foreach ($accounts as $a) {
        $lines[] = '';
        $lines[] = "Bank: {$a['bank']}";
        $lines[] = "Account name: {$a['name']}";
        $lines[] = "Account number: {$a['number']}";
    }
    if ($s['payment_terms'] !== '') {
        $lines[] = '';
        $lines[] = $s['payment_terms'];
    }
    $lines[] = '';
    $lines[] = 'Please send your proof of payment here' . ($ref !== '' ? " with your reference $ref" : '') . '. Thank you!';
    return implode("\n", $lines);
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
        // Bank details only when the owner chose to publish them and accepts transfers
        'payment' => [
            'methods'  => payment_method_labels($s),
            'terms'    => $s['payment_terms'],
            'accounts' => $s['bank_display'] === 'public' && in_array('transfer', explode(',', $s['payment_methods']), true)
                ? bank_accounts($s) : [],
        ],
    ];
}
