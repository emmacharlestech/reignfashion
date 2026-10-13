<?php
/**
 * Reign Fashion Academy registrations: shared labels.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const ACADEMY_LEVELS = [
    'beginner'     => 'Beginner',
    'intermediate' => 'Intermediate',
    'advanced'     => 'Advanced',
    'professional' => 'Professional Mastery',
];

const ACADEMY_SCHEDULES = [
    'weekdays' => 'Weekdays',
    'weekends' => 'Weekends',
    'either'   => 'Either is fine',
];

const ACADEMY_STATUSES = [
    'new'       => 'New',
    'contacted' => 'Contacted',
    'enrolled'  => 'Enrolled',
    'closed'    => 'Closed',
];
