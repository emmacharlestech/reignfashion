<?php
/**
 * Download enquiries or Academy registrations as a CSV file (opens in Excel / Google Sheets).
 *   export?type=enquiries[&status=new]   ·   export?type=academy[&status=enrolled]
 */
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/academy.php';

require_admin();

$type = (string) ($_GET['type'] ?? '');
$status = (string) ($_GET['status'] ?? 'all');

if ($type === 'enquiries') {
    $statuses = STATUSES;
    $sql = 'SELECT ref, created_at, status, name, email, phone, contact_method, service, event_date, message, admin_notes,
                   (SELECT COUNT(*) FROM enquiry_files f WHERE f.enquiry_id = e.id) AS images
            FROM enquiries e';
    $headings = ['Reference', 'Received', 'Status', 'Name', 'Email', 'Phone', 'Prefers', 'Service', 'Occasion date', 'Message', 'Private notes', 'Images'];
    $row = fn($r) => [$r['ref'], $r['created_at'], STATUSES[$r['status']] ?? $r['status'], $r['name'], $r['email'], $r['phone'],
        CONTACT_METHODS[$r['contact_method']] ?? $r['contact_method'], SERVICES[$r['service']] ?? $r['service'],
        $r['event_date'], $r['message'], $r['admin_notes'], $r['images']];
} elseif ($type === 'academy') {
    $statuses = ACADEMY_STATUSES;
    $sql = 'SELECT ref, created_at, status, name, email, phone, level, schedule, experience, admin_notes FROM academy_registrations';
    $headings = ['Reference', 'Received', 'Status', 'Name', 'Email', 'Phone', 'Level', 'Schedule', 'Experience / goals', 'Private notes'];
    $row = fn($r) => [$r['ref'], $r['created_at'], ACADEMY_STATUSES[$r['status']] ?? $r['status'], $r['name'], $r['email'], $r['phone'],
        ACADEMY_LEVELS[$r['level']] ?? $r['level'], ACADEMY_SCHEDULES[$r['schedule']] ?? $r['schedule'], $r['experience'], $r['admin_notes']];
} else {
    http_response_code(404);
    exit('Unknown export.');
}

$args = [];
if (isset($statuses[$status])) {
    $sql .= ' WHERE status = ?';
    $args[] = $status;
}
$stmt = db()->prepare($sql . ' ORDER BY created_at DESC');
$stmt->execute($args);

/** Spreadsheet apps run cells starting with = + - @ as formulas: neutralise them. */
function csv_cell(?string $v): string
{
    $v = (string) $v;
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}

$name = ($type === 'enquiries' ? 'reign-enquiries' : 'reign-academy') . (isset($statuses[$status]) ? "-$status" : '') . '-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");                       // BOM: Excel then reads ₦ and accents correctly
fputcsv($out, $headings);
foreach ($stmt as $r) {
    fputcsv($out, array_map(fn($v) => csv_cell($v === null ? '' : (string) $v), $row($r)));
}
fclose($out);
