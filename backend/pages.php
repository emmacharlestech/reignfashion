<?php
/**
 * Page editor: the owner changes text and photos on the website's pages from
 * admin/pages.php without touching the HTML files.
 *
 * Each editable element carries data-edit="key" (added by tools/mark-editable.js).
 * The HTML file holds the original content; changes are saved in page_content
 * and applied by page.php when the page is served. Because edits live in the
 * database, uploading a new version of the site never overwrites them.
 *
 * Text is edited as plain text: *word* shows in italic (gold in headings),
 * **word** in bold, and a new line becomes a line break.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const PAGE_ROOT = __DIR__ . '/..';
const PAGE_MAX_TEXT = 2000;

/** Pages the owner can edit, in menu order: slug (file name without .html) => name. */
const EDITABLE_PAGES = [
    'index'       => 'Home',
    'about'       => 'About',
    'collections' => 'Collections',
    'women'       => "Women's Collection",
    'men'         => "Men's Collection",
    'kids'        => "Kiddies' Collection",
    'bridal'      => 'Bridal',
    'aso-ebi'     => 'Aso-Ebi',
    'academy'     => 'Academy',
    'contact'     => 'Contact',
    'privacy'     => 'Privacy Policy',
];

/** What each kind of element is called in the admin. */
const PAGE_ELEMENT_LABELS = [
    'h1' => 'Page title', 'h2' => 'Heading', 'h3' => 'Subheading', 'h4' => 'Small heading',
    'p' => 'Paragraph', 'li' => 'List item', 'a' => 'Button / link text', 'figcaption' => 'Photo caption',
    'summary' => 'Question', 'dt' => 'Label', 'dd' => 'Detail', 'img' => 'Photo',
];

/** Elements whose text stays on one line (no line breaks). */
const PAGE_SINGLE_LINE = ['h1', 'h2', 'h3', 'h4', 'a', 'summary', 'dt'];

function ensure_page_table(): void
{
    static $done = false;
    if ($done) return;
    db()->exec('CREATE TABLE IF NOT EXISTS page_content (
        page        VARCHAR(40)  NOT NULL,
        edit_key    VARCHAR(60)  NOT NULL,
        value       TEXT         NOT NULL,
        updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (page, edit_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $done = true;
}

function page_html(string $slug): string
{
    if (!isset(EDITABLE_PAGES[$slug])) {
        throw new RuntimeException('Unknown page.');
    }
    return (string) file_get_contents(PAGE_ROOT . "/$slug.html");
}

/** Saved changes for one page: key => value. Text values are plain text; photos are JSON. */
function page_overrides(string $slug): array
{
    ensure_page_table();
    $s = db()->prepare('SELECT edit_key, value FROM page_content WHERE page = ?');
    $s->execute([$slug]);
    return $s->fetchAll(PDO::FETCH_KEY_PAIR);
}

/** Number of saved changes per page: slug => count. */
function page_override_counts(): array
{
    ensure_page_table();
    return db()->query('SELECT page, COUNT(*) FROM page_content GROUP BY page')->fetchAll(PDO::FETCH_KEY_PAIR);
}

function attr_value(string $tag, string $name): ?string
{
    return preg_match('/\s' . $name . '="([^"]*)"/', $tag, $m) ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
}

function set_attr(string $tag, string $name, string $value): string
{
    $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $count = 0;
    $tag = preg_replace('/\s' . $name . '="[^"]*"/', " $name=\"$value\"", $tag, 1, $count) ?? $tag;
    return $count ? $tag : preg_replace('/\s*(\/?)>$/', " $name=\"$value\"$1>", $tag, 1);
}

/**
 * Every editable element in a page, in document order, with its position in the
 * HTML, the section it belongs to and its original content.
 */
function page_elements(string $html): array
{
    // Top-level sections, for grouping in the admin: [offset, id]. Nested sections stay with their parent.
    preg_match_all('/<(\/?)section\b[^>]*>/', $html, $secs, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    $sections = [];
    $depth = 0;
    foreach ($secs as $s) {
        if ($s[1][0] === '/') { $depth--; continue; }
        if ($depth++ === 0) $sections[] = [$s[0][1], attr_value($s[0][0], 'id') ?? 'part-' . count($sections)];
    }

    preg_match_all('/<([a-z][a-z0-9]*)\b[^>]*\sdata-edit="([a-z0-9-]+)"[^>]*>/', $html, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    $elements = [];
    foreach ($found as $m) {
        [$open, $start] = $m[0];
        $tag = $m[1][0];
        $el = ['key' => $m[2][0], 'tag' => $tag, 'start' => $start, 'open' => $open, 'section' => 'page'];
        foreach ($sections as [$offset, $id]) {
            if ($offset < $start) $el['section'] = $id;
        }

        if ($tag === 'img') {
            $el['end'] = $start + strlen($open);
            $el['default'] = [
                'src' => attr_value($open, 'src') ?? '', 'alt' => attr_value($open, 'alt') ?? '',
                'width' => (int) attr_value($open, 'width'), 'height' => (int) attr_value($open, 'height'),
            ];
        } else {
            // Matching close tag (the same tag can be nested, e.g. a list in a list item)
            preg_match_all('/<(\/?)' . $tag . '\b[^>]*>/', $html, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE, $start + strlen($open));
            $depth = 1;
            foreach ($tags as $t) {
                $depth += $t[1][0] === '/' ? -1 : 1;
                if ($depth === 0) {
                    $el['innerEnd'] = $t[0][1];
                    $el['end'] = $t[0][1] + strlen($t[0][0]);
                    break;
                }
            }
            if (!isset($el['end'])) continue;
            $el['default'] = html_to_editable(substr($html, $start + strlen($open), $el['innerEnd'] - $start - strlen($open)));
        }
        $elements[$el['key']] = $el;
    }
    return $elements;
}

/** Inline HTML from the page → the plain text the owner edits. */
function html_to_editable(string $inner): string
{
    $text = preg_replace('/\s+/', ' ', $inner) ?? '';
    $text = preg_replace('/\s*<br\s*\/?>\s*/i', "\n", $text) ?? '';
    $text = preg_replace(['/<strong>(.*?)<\/strong>/s', '/<em>(.*?)<\/em>/s'], ['**$1**', '*$1*'], $text) ?? '';
    $text = strip_tags($text);
    return trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

/** The owner's plain text → safe inline HTML. Only <em>, <strong> and <br> are ever produced. */
function editable_to_html(string $text): string
{
    $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html) ?? $html;
    $html = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $html) ?? $html;
    return str_replace("\n", '<br>', $html);
}

/** Apply saved changes to a page's HTML. Keys no longer in the page are ignored. */
function render_page(string $html, array $overrides): string
{
    if (!$overrides) return $html;
    $elements = page_elements($html);
    // Replace from the end so earlier offsets stay valid
    foreach (array_reverse($elements) as $el) {
        if (!isset($overrides[$el['key']])) continue;
        $value = $overrides[$el['key']];
        if ($el['tag'] === 'img') {
            $img = json_decode($value, true);
            if (!is_array($img) || !isset($img['src'], $img['alt'])) continue;
            $tag = set_attr($el['open'], 'src', $img['src']);
            $tag = set_attr($tag, 'alt', $img['alt']);
            if (!empty($img['width']) && !empty($img['height'])) {
                $tag = set_attr(set_attr($tag, 'width', (string) $img['width']), 'height', (string) $img['height']);
            }
            $tag = preg_replace('/\s(srcset|sizes)="[^"]*"/', '', $tag) ?? $tag;
            $html = substr_replace($html, $tag, $el['start'], strlen($el['open']));
        } else {
            $innerStart = $el['start'] + strlen($el['open']);
            $html = substr_replace($html, editable_to_html($value), $innerStart, $el['innerEnd'] - $innerStart);
        }
    }
    return $html;
}

/**
 * Sections of a page for the admin, in order: id => ['label' => ..., 'elements' => [...]].
 * A section is named after its first heading.
 */
function page_sections(array $elements): array
{
    $sections = [];
    foreach ($elements as $el) {
        $sections[$el['section']]['elements'][] = $el;
    }
    foreach ($sections as $id => &$s) {
        // Main heading first, then any heading, then the first piece of text
        $label = '';
        foreach ([['h1', 'h2'], ['h3', 'h4'], array_keys(PAGE_ELEMENT_LABELS)] as $tags) {
            foreach ($s['elements'] as $el) {
                if ($el['tag'] !== 'img' && in_array($el['tag'], $tags, true)) { $label = $el['default']; break 2; }
            }
        }
        $label = str_replace('*', '', $label);
        $s['label'] = $label === '' ? 'Photos' : (mb_strlen($label) > 70 ? mb_substr($label, 0, 68) . '…' : $label);
    }
    return $sections;
}

function save_page_override(string $slug, string $key, ?string $value): void
{
    ensure_page_table();
    if ($value === null) {
        db()->prepare('DELETE FROM page_content WHERE page = ? AND edit_key = ?')->execute([$slug, $key]);
    } else {
        db()->prepare('INSERT INTO page_content (page, edit_key, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)')
            ->execute([$slug, $key, $value]);
    }
}

/** Clean one text field from the admin form. Throws RuntimeException with a message for the owner. */
function clean_page_text(string $tag, string $value): string
{
    $value = clean_text($value, PAGE_MAX_TEXT + 1);
    if (in_array($tag, PAGE_SINGLE_LINE, true)) {
        $value = trim(preg_replace('/\s*\n\s*/', ' ', $value) ?? '');
    } else {
        $value = preg_replace("/[ \t]*\n[ \t]*/", "\n", $value) ?? '';
    }
    if ($value === '') throw new RuntimeException('Text can’t be left empty. To go back to the original wording, use “Restore original”.');
    if (mb_strlen($value) > PAGE_MAX_TEXT) throw new RuntimeException('Please keep each piece of text under ' . PAGE_MAX_TEXT . ' characters.');
    return $value;
}

/** True if an uploaded photo is still used by a page or the gallery (so it must not be deleted). */
function page_photo_in_use(string $src): bool
{
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], json_encode($src, JSON_UNESCAPED_SLASHES)) . '%';
    $s = db()->prepare('SELECT (SELECT COUNT(*) FROM page_content WHERE value LIKE ?) + (SELECT COUNT(*) FROM gallery_items WHERE src = ?)');
    $s->execute([$like, $src]);
    return (int) $s->fetchColumn() > 0;
}
