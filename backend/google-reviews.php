<?php
/**
 * Google reviews for the home page carousel, from the Google Places API (New).
 *
 * Needs 'google_places_api_key' in config.php; without it the section stays
 * hidden. Results are cached in backend/storage so Google is asked a few times
 * a day, not on every visit. If Google can't be reached, the last good copy
 * keeps showing (for up to GOOGLE_REVIEWS_MAX_STALE).
 *
 * Note: Google returns at most 5 reviews per business (its "most relevant").
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const GOOGLE_REVIEWS_CACHE     = __DIR__ . '/storage/google-reviews.json';
const GOOGLE_REVIEWS_TTL       = 6 * 3600;        // refresh every 6 hours
const GOOGLE_REVIEWS_RETRY     = 15 * 60;         // after a failure, wait before asking again
const GOOGLE_REVIEWS_MAX_STALE = 30 * 86400;      // never show a copy older than 30 days
const GOOGLE_REVIEW_URL        = 'https://g.page/r/CQWysc0SiqdZEAI/review';
const GOOGLE_PLACES_BASE       = 'https://places.googleapis.com/v1/';

function google_reviews_config(): array
{
    global $CONFIG;
    return [
        'key'        => trim((string) ($CONFIG['google_places_api_key'] ?? '')),
        'place_id'   => trim((string) ($CONFIG['google_place_id'] ?? '')),
        'query'      => trim((string) ($CONFIG['google_place_query'] ?? 'Reign Fashion Empire, Asaba, Delta State')),
        'review_url' => trim((string) ($CONFIG['google_review_url'] ?? GOOGLE_REVIEW_URL)),
        'min_rating' => max(1, min(5, (int) ($CONFIG['google_reviews_min_rating'] ?? 4))),
    ];
}

/**
 * Public data for the website, or null when not configured / nothing to show.
 * Shape: name, rating, count, mapsUrl, writeUrl, reviews[author, authorUrl, photo, rating, text, time, url]
 */
function google_reviews(): ?array
{
    $cfg = google_reviews_config();
    if ($cfg['key'] === '') {
        return null;
    }
    // A config change (other place, other minimum rating) starts a fresh cache
    $source = md5($cfg['place_id'] . '|' . $cfg['query'] . '|' . $cfg['min_rating'] . '|' . $cfg['review_url']);

    $cache = google_reviews_cache_read();
    if (($cache['source'] ?? '') !== $source) {
        $cache = [];
    }
    $stale = fn(array $c) => (time() - (int) ($c['fetched_at'] ?? 0)) < GOOGLE_REVIEWS_MAX_STALE ? ($c['data'] ?? null) : null;

    $age = time() - (int) ($cache['checked_at'] ?? 0);
    if ($cache && $age < (isset($cache['error']) ? GOOGLE_REVIEWS_RETRY : GOOGLE_REVIEWS_TTL)) {
        return $stale($cache);
    }

    // Only one visitor refreshes at a time; everyone else gets the cached copy
    $lock = @fopen(GOOGLE_REVIEWS_CACHE . '.lock', 'c');
    if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        return $stale($cache);
    }
    try {
        [$placeId, $data] = google_reviews_fetch($cfg, $cache['place_id'] ?? '');
        google_reviews_cache_write([
            'source' => $source, 'checked_at' => time(), 'fetched_at' => time(),
            'place_id' => $placeId, 'data' => $data,
        ]);
        return $data;
    } catch (Throwable $e) {
        error_log('Google reviews refresh failed: ' . $e->getMessage());
        google_reviews_cache_write([
            'source' => $source, 'checked_at' => time(), 'fetched_at' => (int) ($cache['fetched_at'] ?? 0),
            'place_id' => $cache['place_id'] ?? '', 'data' => $cache['data'] ?? null, 'error' => $e->getMessage(),
        ]);
        return $stale($cache);
    } finally {
        if ($lock) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}

/** Ask Google for the place's rating and reviews. Returns [placeId, publicData]. */
function google_reviews_fetch(array $cfg, string $knownPlaceId): array
{
    $placeId = $cfg['place_id'] !== '' ? $cfg['place_id'] : $knownPlaceId;
    if ($placeId === '') {
        // "IDs only" text search: free, and only needed once (the ID is cached)
        $found = google_places_request('POST', GOOGLE_PLACES_BASE . 'places:searchText', $cfg['key'], 'places.id', [
            'textQuery' => $cfg['query'],
            'pageSize'  => 1,
        ]);
        $placeId = (string) ($found['places'][0]['id'] ?? '');
        if ($placeId === '') {
            throw new RuntimeException('No Google listing found for "' . $cfg['query'] . '". Set google_place_id in config.php.');
        }
    }
    if (!preg_match('/^[A-Za-z0-9_-]{10,300}$/', $placeId)) {
        throw new RuntimeException('google_place_id in config.php does not look like a Place ID.');
    }

    $place = google_places_request(
        'GET',
        GOOGLE_PLACES_BASE . 'places/' . rawurlencode($placeId) . '?languageCode=en',
        $cfg['key'],
        'displayName,rating,userRatingCount,googleMapsUri,reviews'
    );

    $reviews = [];
    foreach ($place['reviews'] ?? [] as $r) {
        $text = trim((string) ($r['originalText']['text'] ?? $r['text']['text'] ?? ''));
        $rating = (int) ($r['rating'] ?? 0);
        if ($text === '' || $rating < $cfg['min_rating']) {
            continue;                                  // a star rating with no words says little in a carousel
        }
        $reviews[] = [
            'author'    => (string) ($r['authorAttribution']['displayName'] ?? 'Google user'),
            'authorUrl' => google_reviews_https($r['authorAttribution']['uri'] ?? ''),
            'photo'     => google_reviews_https($r['authorAttribution']['photoUri'] ?? ''),
            'rating'    => $rating,
            'text'      => $text,
            'time'      => (string) ($r['relativePublishTimeDescription'] ?? ''),
            'url'       => google_reviews_https($r['googleMapsUri'] ?? ''),
        ];
    }

    $data = $reviews ? [
        'name'     => (string) ($place['displayName']['text'] ?? 'Reign Fashion Empire'),
        'rating'   => round((float) ($place['rating'] ?? 0), 1),
        'count'    => (int) ($place['userRatingCount'] ?? 0),
        'mapsUrl'  => google_reviews_https($place['googleMapsUri'] ?? ''),
        'writeUrl' => google_reviews_https($cfg['review_url']) ?: 'https://search.google.com/local/writereview?placeid=' . rawurlencode($placeId),
        'reviews'  => $reviews,
    ] : null;
    return [$placeId, $data];
}

/** Only https links reach the page. */
function google_reviews_https(mixed $url): string
{
    $url = trim((string) $url);
    return str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
}

function google_places_request(string $method, string $url, string $key, string $fieldMask, ?array $body = null): array
{
    $headers = ['X-Goog-Api-Key: ' . $key, 'X-Goog-FieldMask: ' . $fieldMask, 'Accept: application/json'];
    $payload = $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE);
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 8,
        ]);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => $method, 'header' => implode("\r\n", $headers), 'content' => $payload ?? '',
            'timeout' => 8, 'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        $status = preg_match('#^HTTP/\S+\s+(\d{3})#', $http_response_header[0] ?? '', $m) ? (int) $m[1] : 0;
        $err = 'no response';
    }

    if (!is_string($raw) || $raw === '') {
        throw new RuntimeException("Google Places request failed ($err).");
    }
    $data = json_decode($raw, true);
    if ($status !== 200 || !is_array($data)) {
        $msg = is_array($data) ? (string) ($data['error']['message'] ?? '') : '';
        throw new RuntimeException("Google Places returned HTTP $status" . ($msg !== '' ? ": $msg" : '.'));
    }
    return $data;
}

function google_reviews_cache_read(): array
{
    $raw = is_file(GOOGLE_REVIEWS_CACHE) ? @file_get_contents(GOOGLE_REVIEWS_CACHE) : false;
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($data) ? $data : [];
}

function google_reviews_cache_write(array $cache): void
{
    $tmp = GOOGLE_REVIEWS_CACHE . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false || !@rename($tmp, GOOGLE_REVIEWS_CACHE)) {
        @unlink($tmp);
        error_log('Google reviews: could not write cache file in backend/storage.');
    }
}
