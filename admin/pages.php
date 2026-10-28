<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/gallery.php';
require __DIR__ . '/../backend/pages.php';
require __DIR__ . '/../backend/admin-layout.php';

$admin = require_admin();

$slug = (string) ($_GET['p'] ?? $_POST['page'] ?? '');
if ($slug !== '' && !isset(EDITABLE_PAGES[$slug])) {
    redirect('pages');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $slug !== '') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $section = (string) ($_POST['section'] ?? '');
    $elements = page_elements(page_html($slug));
    $saved = page_overrides($slug);
    $back = 'pages?p=' . rawurlencode($slug) . '&s=' . rawurlencode($section) . '#s-' . rawurlencode($section);
    $name = EDITABLE_PAGES[$slug];

    try {
        if ($action === 'save') {
            $changed = 0;
            foreach ((array) ($_POST['text'] ?? []) as $key => $value) {
                $el = $elements[$key] ?? null;
                if (!$el || $el['tag'] === 'img') continue;
                $text = clean_page_text($el['tag'], (string) $value);
                $new = $text === $el['default'] ? null : $text;
                if ($new !== ($saved[$key] ?? null)) {
                    save_page_override($slug, $key, $new);
                    $changed++;
                }
            }
            flash($changed ? "Saved $changed change" . ($changed > 1 ? 's' : '') . " to the $name page." : 'Nothing changed.');
        } elseif ($action === 'photo') {
            $key = (string) ($_POST['key'] ?? '');
            $el = $elements[$key] ?? null;
            if (!$el || $el['tag'] !== 'img') throw new RuntimeException('That photo is no longer on the page.');
            $old = isset($saved[$key]) ? json_decode($saved[$key], true) : null;
            $img = is_array($old) ? $old : $el['default'];
            if (($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $img = array_merge($img, store_gallery_upload($_FILES['photo']));
            }
            $img['alt'] = clean_line($_POST['alt'] ?? '', 250);
            if ($img['alt'] === '') throw new RuntimeException('Please describe the photo for people who can’t see it (and for Google).');
            $same = $img['src'] === $el['default']['src'] && $img['alt'] === $el['default']['alt'];
            save_page_override($slug, $key, $same ? null : json_encode($img, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            if (is_array($old) && $old['src'] !== $img['src'] && !page_photo_in_use($old['src'])) {
                delete_gallery_file($old['src']);
            }
            flash("Photo updated on the $name page.");
        } elseif ($action === 'reset') {
            $key = (string) ($_POST['key'] ?? '');
            if (isset($saved[$key])) {
                save_page_override($slug, $key, null);
                $old = json_decode($saved[$key], true);
                if (is_array($old) && isset($old['src']) && !page_photo_in_use($old['src'])) {
                    delete_gallery_file($old['src']);
                }
                flash('Restored the original.');
            }
        }
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'error');
    }
    redirect($back);
}

/* ---------- List of pages ---------- */
if ($slug === '') {
    $counts = page_override_counts();
    admin_header('Pages', $admin, 'pages');
    ?>
    <div class="page__head">
      <div>
        <h1 class="page__title">Pages</h1>
        <p class="page__sub">Change the wording and photos on your website. Your original text is kept, so any change can be undone.</p>
      </div>
    </div>

    <ul class="plist">
<?php foreach (EDITABLE_PAGES as $p => $label): $n = (int) ($counts[$p] ?? 0); ?>
      <li><a class="plist__link" href="pages?p=<?= e($p) ?>">
        <span class="plist__name"><?= e($label) ?></span>
        <span class="plist__meta"><?= $n ? "$n change" . ($n > 1 ? 's' : '') : 'Original' ?></span>
      </a></li>
<?php endforeach; ?>
    </ul>
<?php
    admin_footer();
    exit;
}

/* ---------- One page ---------- */
$elements = page_elements(page_html($slug));
$saved = page_overrides($slug);
$sections = page_sections($elements);
$openSection = (string) ($_GET['s'] ?? '');
$siteUrl = '../' . ($slug === 'index' ? '' : $slug);

admin_header(EDITABLE_PAGES[$slug] . ' page', $admin, 'pages');
?>
    <p class="crumbs"><a href="pages">← All pages</a></p>
    <div class="page__head">
      <div>
        <h1 class="page__title"><?= e(EDITABLE_PAGES[$slug]) ?></h1>
        <p class="page__sub"><?= $saved ? count($saved) . ' change' . (count($saved) > 1 ? 's' : '') . ' from the original.' : 'Showing the original content.' ?></p>
      </div>
      <a class="btn" href="<?= e($siteUrl) ?>" target="_blank" rel="noopener">View page ↗</a>
    </div>

    <p class="card ptips">Open a section, change the text and press <strong>Save section</strong>.
      Put <code>*stars*</code> around words to show them in <em>italic</em> (gold in headings) and
      <code>**two stars**</code> for <strong>bold</strong>.</p>

<?php foreach ($sections as $sid => $sec):
    $secChanges = count(array_filter($sec['elements'], fn($el) => isset($saved[$el['key']])));
    $texts = array_filter($sec['elements'], fn($el) => $el['tag'] !== 'img');
    $photos = array_filter($sec['elements'], fn($el) => $el['tag'] === 'img'); ?>
    <details class="card psec" id="s-<?= e((string) $sid) ?>" <?= $openSection === (string) $sid ? 'open' : '' ?>>
      <summary class="psec__summary">
        <span class="psec__title"><?= e($sec['label']) ?></span>
        <?php if ($secChanges): ?><span class="badge badge--new"><?= $secChanges ?> changed</span><?php endif; ?>
      </summary>

<?php foreach ($photos as $el):
        $img = isset($saved[$el['key']]) ? (json_decode($saved[$el['key']], true) ?: $el['default']) : $el['default'];
        $fid = 'f-' . $el['key']; ?>
      <form method="post" enctype="multipart/form-data" class="pphoto" data-resize>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="photo">
        <input type="hidden" name="page" value="<?= e($slug) ?>">
        <input type="hidden" name="section" value="<?= e((string) $sid) ?>">
        <input type="hidden" name="key" value="<?= e($el['key']) ?>">
        <img class="pphoto__current" src="../<?= e($img['src']) ?>" alt="" loading="lazy">
        <div class="pphoto__fields">
          <p class="pfield__label">Photo <?php if (isset($saved[$el['key']])): ?><span class="badge badge--new">Changed</span><?php endif; ?></p>
          <div class="field">
            <label for="<?= e($fid) ?>-file">Replace with a new photo</label>
            <input id="<?= e($fid) ?>-file" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
            <img class="upload__preview" alt="" hidden>
          </div>
          <div class="field">
            <label for="<?= e($fid) ?>-alt">Description</label>
            <input id="<?= e($fid) ?>-alt" name="alt" value="<?= e($img['alt']) ?>" maxlength="250" required>
            <small class="field__help">What the photo shows, for people who can’t see it and for Google.</small>
          </div>
          <div class="pphoto__actions">
            <button type="submit" class="btn btn--primary btn--sm">Save photo</button>
<?php if (isset($saved[$el['key']])): ?>
            <button type="submit" class="btn btn--sm" name="action" value="reset" formnovalidate>Restore original</button>
<?php endif; ?>
          </div>
        </div>
      </form>
<?php endforeach; ?>

<?php if ($texts): ?>
      <form method="post" class="form psec__form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="page" value="<?= e($slug) ?>">
        <input type="hidden" name="section" value="<?= e((string) $sid) ?>">
<?php foreach ($texts as $el):
          $value = $saved[$el['key']] ?? $el['default'];
          $fid = 'f-' . $el['key'];
          $single = in_array($el['tag'], PAGE_SINGLE_LINE, true); ?>
        <div class="field pfield<?= isset($saved[$el['key']]) ? ' is-changed' : '' ?>">
          <div class="pfield__head">
            <label for="<?= e($fid) ?>"><?= e(PAGE_ELEMENT_LABELS[$el['tag']] ?? 'Text') ?></label>
<?php if (isset($saved[$el['key']])): ?>
            <span class="badge badge--new">Changed</span>
            <button type="submit" class="pfield__reset" form="reset-<?= e($el['key']) ?>">Restore original</button>
<?php endif; ?>
          </div>
<?php if ($single): ?>
          <input id="<?= e($fid) ?>" name="text[<?= e($el['key']) ?>]" value="<?= e($value) ?>" maxlength="<?= PAGE_MAX_TEXT ?>" required>
<?php else: ?>
          <textarea id="<?= e($fid) ?>" name="text[<?= e($el['key']) ?>]" rows="<?= max(2, min(8, (int) ceil(mb_strlen($value) / 70))) ?>" maxlength="<?= PAGE_MAX_TEXT ?>" required><?= e($value) ?></textarea>
<?php endif; ?>
<?php if (isset($saved[$el['key']])): ?>
          <small class="field__help">Original: <?= e($el['default']) ?></small>
<?php endif; ?>
        </div>
<?php endforeach; ?>
        <div class="settings__actions"><button type="submit" class="btn btn--primary">Save section</button></div>
      </form>
<?php foreach ($texts as $el): if (!isset($saved[$el['key']])) continue; ?>
      <form method="post" id="reset-<?= e($el['key']) ?>" hidden>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset">
        <input type="hidden" name="page" value="<?= e($slug) ?>">
        <input type="hidden" name="section" value="<?= e((string) $sid) ?>">
        <input type="hidden" name="key" value="<?= e($el['key']) ?>">
      </form>
<?php endforeach; ?>
<?php endif; ?>
    </details>
<?php endforeach; ?>
    <script src="gallery.js" defer></script>
<?php
admin_footer();
