<?php
/**
 * Client testimonials: services, queries and validation.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/** Shown under the client's name on the website. */
const TESTIMONIAL_SERVICES = [
    'bridal'        => 'Bridal',
    'aso-ebi'       => 'Aso-Ebi',
    'ready-to-wear' => 'Ready-to-Wear',
    'kids'          => "Kiddies' Wear",
    'men'           => "Men's Wear",
    'custom'        => 'Custom Design',
    'academy'       => 'Academy Student',
];

const TESTIMONIAL_MAX_QUOTE = 600;

function testimonials(bool $visibleOnly = false): array
{
    // Published = visible AND the owner confirmed the client's permission
    $sql = 'SELECT * FROM testimonials' . ($visibleOnly ? ' WHERE is_visible = 1 AND consent_confirmed = 1' : '') . ' ORDER BY sort_order, id';
    return db()->query($sql)->fetchAll();
}

/** Validate the add/edit form. Returns clean fields or throws RuntimeException. */
function testimonial_fields(array $in): array
{
    $quote = trim(preg_replace('/\s+/', ' ', clean_text($in['quote'] ?? '', TESTIMONIAL_MAX_QUOTE + 50)) ?? '');
    // Strip quotation marks the owner may have typed: the design adds its own
    $quote = trim($quote, " \"'“”‘’");
    $f = [
        'quote'       => $quote,
        'client_name' => clean_line($in['client_name'] ?? '', 80),
        'service'     => (string) ($in['service'] ?? ''),
        'consent'     => !empty($in['consent']) ? 1 : 0,
    ];
    if (mb_strlen($f['quote']) < 10) throw new RuntimeException('Please enter the client’s words (at least 10 characters).');
    if (mb_strlen($f['quote']) > TESTIMONIAL_MAX_QUOTE) throw new RuntimeException('Please shorten the quote to ' . TESTIMONIAL_MAX_QUOTE . ' characters or fewer.');
    if ($f['client_name'] === '') throw new RuntimeException('Please enter the client’s name as they would like it shown (e.g. “Adaeze O.”).');
    if (!isset(TESTIMONIAL_SERVICES[$f['service']])) throw new RuntimeException('Please choose the service.');
    if (!$f['consent']) throw new RuntimeException('Please confirm the client agreed to have their words shared on the website.');
    return $f;
}
