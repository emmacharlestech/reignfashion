<?php
/**
 * Rebuilds the built-in gallery tiles (the fallback shown before the live
 * gallery loads, or if it can't) in every page that has a gallery, from the
 * gallery_items table managed in admin/gallery.php.
 *
 *   C:\xampp\php\php.exe tools\build-gallery.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/backend/gallery.php';

$h = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$items = gallery_items(true);

$lis = '';
foreach ($items as $it) {
    $size = $it['size'] === 'standard' ? '' : ' g-item--' . $it['size'];
    $alt = ($it['is_sample'] ? 'Sample image: ' : '') . $it['alt'];
    $lis .= "\n          <li class=\"g-item$size\" data-category=\"{$h($it['category'])}\">"
        . "\n            <button type=\"button\" class=\"g-item__btn\">"
        . "\n              <span class=\"img-slot" . ($it['is_sample'] ? ' is-sample' : '') . "\"><img src=\"{$h($it['src'])}\" alt=\"{$h($alt)}\" width=\"{$it['width']}\" height=\"{$it['height']}\" loading=\"lazy\" decoding=\"async\" style=\"object-position: {$h($it['focus'])}\"></span>"
        . "\n              <span class=\"g-item__caption\"><span class=\"g-item__cat\">{$h(GALLERY_CATEGORIES[$it['category']])}</span><span class=\"g-item__title\">{$h($it['title'])}</span></span>"
        . "\n            </button>"
        . "\n          </li>";
}

$open = '<ul class="gallery__grid" id="gallery-grid">';
foreach (glob($root . '/*.html') as $file) {
    $html = file_get_contents($file);
    $a = strpos($html, $open);
    if ($a === false) continue;
    $b = strpos($html, '</ul>', $a);
    $html = substr($html, 0, $a + strlen($open)) . $lis . "\n        " . substr($html, $b);
    file_put_contents($file, $html);
    echo basename($file) . ': ' . count($items) . " tiles\n";
}
