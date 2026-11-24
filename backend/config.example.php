<?php
/**
 * Reign Fashion Empire — backend configuration TEMPLATE.
 *
 * backend/install.php creates the real backend/config.php from this,
 * filling in a generated database password and secret. On a live host,
 * copy this file to config.php and fill in the host's details by hand.
 * Never share config.php: it contains passwords.
 */
return [
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'reignfashion',
        'user' => 'reign_app',
        'pass' => '',               // set by install.php
    ],

    // Random secret used to hash visitor IPs for rate limiting (set by install.php)
    'app_secret' => '',

    // Enquiry notification email. Both must be set for emails to be attempted.
    // 'mail_from' must be an address on the website's own domain on a live host.
    'mail_to'   => 'reignfashionempire@gmail.com',
    'mail_from' => '',

    // Uploads
    'max_files'      => 3,
    'max_file_bytes' => 5 * 1024 * 1024,

    // Spam protection: max enquiries per visitor in the window below
    'rate_limit_count'   => 5,
    'rate_limit_minutes' => 10,

    // Google reviews carousel on the home page (Google Places API "New").
    // Leave the key empty to hide the section. See README → Google reviews.
    'google_places_api_key'     => '',
    // Optional Place ID (starts "ChIJ…"). Empty = found once by searching google_place_query.
    'google_place_id'           => '',
    'google_place_query'        => 'Reign Fashion Empire, Asaba, Delta State',
    'google_review_url'         => 'https://g.page/r/CQWysc0SiqdZEAI/review',
    'google_reviews_min_rating' => 4,          // hide reviews below this many stars

    // Show PHP errors in responses. Keep false on a live site.
    'debug' => false,
];
