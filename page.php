<?php
/**
 * Serves a website page with the owner's edits from admin/pages.php applied.
 * .htaccess sends clean addresses here (/about → page.php?p=about).
 *
 * If the database can't be reached, the page is served exactly as the HTML
 * file is, so the website never goes down because of the editor.
 */
declare(strict_types=1);

$slug = (string) ($_GET['p'] ?? 'index');
$file = __DIR__ . "/$slug.html";
if (!preg_match('/^[a-z0-9-]{1,40}$/', $slug) || !is_file($file)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Page not found.');
}

$html = (string) file_get_contents($file);

if (is_file(__DIR__ . '/backend/config.php')) {
    try {
        require __DIR__ . '/backend/pages.php';
        if (isset(EDITABLE_PAGES[$slug])) {
            $html = render_page($html, page_overrides($slug));
        }
    } catch (Throwable $e) {
        error_log('Page edits not applied to ' . $slug . ': ' . $e->getMessage());
    }
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');   // edits show straight away
echo $html;
