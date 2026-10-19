<?php
/**
 * Email alerts to the owner about new enquiries and Academy registrations.
 * The recipient and on/off switch are in Admin → Settings → Email alerts.
 */
declare(strict_types=1);

require_once __DIR__ . '/settings.php';

/** Where alerts go: the "Send alerts to" setting, else the business email, else config mail_to. */
function alert_recipient(?array $s = null): string
{
    global $CONFIG;
    $s ??= get_settings();
    foreach ([$s['alert_email'], $s['email'], $CONFIG['mail_to'] ?? ''] as $address) {
        if (filter_var($address, FILTER_VALIDATE_EMAIL)) return $address;
    }
    return '';
}

/**
 * The From address. Hosts only deliver mail sent from their own domain, so use
 * config mail_from if set, else no-reply@ the live domain. '' on localhost.
 */
function alert_sender(): string
{
    global $CONFIG;
    if (filter_var($CONFIG['mail_from'] ?? '', FILTER_VALIDATE_EMAIL)) {
        return $CONFIG['mail_from'];
    }
    $host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '') ?? '');
    $host = preg_replace('/^www\./', '', $host) ?? '';
    if (!preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,}$/', $host)) {
        return '';                                 // localhost, an IP address or nothing
    }
    return "no-reply@$host";
}

/** Why alerts can't be sent right now, or '' if they can. Shown in the admin. */
function alert_problem(?array $s = null): string
{
    $s ??= get_settings();
    if ($s['alerts'] !== 'on') return 'Email alerts are switched off.';
    if (alert_recipient($s) === '') return 'Add an email address to send alerts to.';
    if (alert_sender() === '') return 'Email can only be sent from the live website, not from this computer.';
    return '';
}

/** Full link to an admin page, for alert emails (called from api/*.php). */
function admin_link(string $page): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/', 2)), '/');
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $base . '/admin/' . $page;
}

/** Send one alert. Returns true if the server accepted the email. */
function send_admin_alert(string $subject, string $body, string $replyTo = '', bool $force = false): bool
{
    $s = get_settings();
    if ($s['alerts'] !== 'on' && !$force) return false;
    $to = alert_recipient($s);
    $from = alert_sender();
    if ($to === '' || $from === '') return false;

    $headers = ['From: Reign Fashion Empire Website <' . $from . '>'];
    if (filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    array_push($headers, 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: base64');
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return @mail($to, $encodedSubject, chunk_split(base64_encode($body)), implode("\r\n", $headers));
}
