<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/gallery.php';
require __DIR__ . '/../backend/admin-layout.php';

$admin = require_admin();

/** Read and validate the shared fields of the upload and edit forms. */
function gallery_fields(array $in): array
{
    $f = [
        'title'     => clean_line($in['title'] ?? '', 120),
        'alt'       => clean_line($in['alt'] ?? '', 250),
        'category'  => (string) ($in['category'] ?? ''),
        'size'      => (string) ($in['size'] ?? 'standard'),
        'focus'     => (string) ($in['focus'] ?? '50% 30%'),
        'is_sample' => !empty($in['is_sample']) ? 1 : 0,
    ];
    if ($f['title'] === '') throw new RuntimeException('Please give the design a title.');
    if (!isset(GALLERY_CATEGORIES[$f['category']])) throw new RuntimeException('Please choose a category.');
    if (!isset(GALLERY_SIZES[$f['size']])) $f['size'] = 'standard';
    if (!isset(GALLERY_FOCUS[$f['focus']])) $f['focus'] = '50% 30%';
    if ($f['alt'] === '') $f['alt'] = $f['title'] . ' by Reign Fashion Empire';
    return $f;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    try {
        if ($action === 'upload') {
            $f = gallery_fields($_POST);
            $img = store_gallery_upload($_FILES['photo'] ?? []);
            $top = (int) db()->query('SELECT COALESCE(MIN(sort_order), 10) FROM gallery_items')->fetchColumn();
            db()->prepare('INSERT INTO gallery_items (title, alt, category, size, src, width, height, focus, is_sample, sort_order)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$f['title'], $f['alt'], $f['category'], $f['size'], $img['src'], $img['width'], $img['height'], $f['focus'], $f['is_sample'], $top - 10]);
            flash("“{$f['title']}” added to the top of the gallery.");
        } elseif ($action === 'update') {
            $f = gallery_fields($_POST);
            db()->prepare('UPDATE gallery_items SET title = ?, alt = ?, category = ?, size = ?, focus = ?, is_sample = ? WHERE id = ?')
                ->execute([$f['title'], $f['alt'], $f['category'], $f['size'], $f['focus'], $f['is_sample'], $id]);
            flash("“{$f['title']}” updated.");
        } elseif ($action === 'toggle') {
            db()->prepare('UPDATE gallery_items SET is_visible = 1 - is_visible WHERE id = ?')->execute([$id]);
            flash('Visibility updated.');
        } elseif ($action === 'move') {
            $dir = ($_POST['dir'] ?? '') === 'up' ? 'up' : 'down';
            $cur = db()->prepare('SELECT id, sort_order FROM gallery_items WHERE id = ?');
            $cur->execute([$id]);
            if ($row = $cur->fetch()) {
                $nb = db()->prepare($dir === 'up'
                    ? 'SELECT id, sort_order FROM gallery_items WHERE (sort_order, id) < (?, ?) ORDER BY sort_order DESC, id DESC LIMIT 1'
                    : 'SELECT id, sort_order FROM gallery_items WHERE (sort_order, id) > (?, ?) ORDER BY sort_order, id LIMIT 1');
                $nb->execute([$row['sort_order'], $row['id']]);
                if ($other = $nb->fetch()) {
                    // Renumber everything cleanly, swapping the two items
                    $ids = array_column(gallery_items(), 'id');
                    $a = array_search($row['id'], $ids);
                    $b = array_search($other['id'], $ids);
                    [$ids[$a], $ids[$b]] = [$ids[$b], $ids[$a]];
                    $upd = db()->prepare('UPDATE gallery_items SET sort_order = ? WHERE id = ?');
                    foreach ($ids as $i => $itemId) $upd->execute([($i + 1) * 10, $itemId]);
                }
            }
            redirect('gallery.php#item-' . $id);
        } elseif ($action === 'delete') {
            $s = db()->prepare('SELECT src, title FROM gallery_items WHERE id = ?');
            $s->execute([$id]);
            if ($row = $s->fetch()) {
                db()->prepare('DELETE FROM gallery_items WHERE id = ?')->execute([$id]);
                delete_gallery_file($row['src']);
                flash("“{$row['title']}” removed from the gallery.");
            }
        }
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'error');
    }
    redirect('gallery.php');
}

$items = gallery_items();
$visible = count(array_filter($items, fn($i) => $i['is_visible']));

/** Render <option>s for a select. */
function options(array $choices, string $current): string
{
    $html = '';
    foreach ($choices as $value => $label) {
        $html .= '<option value="' . e($value) . '"' . ($value === $current ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $html;
}

admin_header('Gallery', $admin, 'gallery');
?>
    <div class="page__head">
      <div>
        <h1 class="page__title">Gallery</h1>
        <p class="page__sub"><?= $visible ?> of <?= count($items) ?> designs showing in “Selected Creations”. The order here is the order on the website.</p>
      </div>
      <a class="btn" href="../collections#gallery" target="_blank" rel="noopener">View gallery ↗</a>
    </div>

    <details class="card upload" <?= $items ? '' : 'open' ?>>
      <summary class="upload__summary">+ Add a design</summary>
      <form method="post" enctype="multipart/form-data" class="form form--grid upload__form" data-resize>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload">
        <div class="field field--full">
          <label for="u-photo">Photo</label>
          <input id="u-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required aria-describedby="u-photo-h">
          <small id="u-photo-h" class="field__help">JPG, PNG or WebP. Large photos are resized automatically before uploading. Only upload Reign Fashion's own work.</small>
          <img class="upload__preview" alt="" hidden>
        </div>
        <div class="field"><label for="u-title">Title</label>
          <input id="u-title" name="title" maxlength="120" required placeholder="e.g. Emerald Lace Aso-Ebi"></div>
        <div class="field"><label for="u-cat">Category</label>
          <select id="u-cat" name="category" required><option value="">Choose…</option><?= options(GALLERY_CATEGORIES, '') ?></select></div>
        <div class="field"><label for="u-size">Tile shape</label>
          <select id="u-size" name="size"><?= options(GALLERY_SIZES, 'tall') ?></select></div>
        <div class="field"><label for="u-focus">Keep in view when cropped</label>
          <select id="u-focus" name="focus"><?= options(GALLERY_FOCUS, '50% 30%') ?></select></div>
        <div class="field field--full"><label for="u-alt">Description for screen readers <small>(optional)</small></label>
          <input id="u-alt" name="alt" maxlength="250" placeholder="e.g. Emerald lace Aso-Ebi gown with beaded corset and matching gele"></div>
        <label class="check field--full"><input type="checkbox" name="is_sample" value="1"> <span>This is a stock or sample photo (shows a “Sample image” tag)</span></label>
        <div class="field--full"><button type="submit" class="btn btn--primary">Upload design</button></div>
      </form>
    </details>

<?php if (!$items): ?>
    <div class="empty"><p class="empty__title">No designs yet.</p><p>Add your first design above.</p></div>
<?php else: ?>
    <ol class="glist">
<?php foreach ($items as $n => $it): ?>
      <li class="gcard<?= $it['is_visible'] ? '' : ' is-hidden' ?>" id="item-<?= (int) $it['id'] ?>">
        <div class="gcard__thumb">
          <img src="../<?= e($it['src']) ?>" alt="" loading="lazy" style="object-position: <?= e($it['focus']) ?>">
          <span class="gcard__pos"><?= $n + 1 ?></span>
        </div>
        <div class="gcard__body">
          <p class="gcard__title"><?= e($it['title']) ?></p>
          <p class="gcard__meta">
            <?= e(GALLERY_CATEGORIES[$it['category']]) ?> · <?= e(explode(' ', GALLERY_SIZES[$it['size']])[0]) ?>
            <?php if ($it['is_sample']): ?><span class="badge badge--new">Sample</span><?php endif; ?>
            <?php if (!$it['is_visible']): ?><span class="badge badge--closed">Hidden</span><?php endif; ?>
          </p>
          <div class="gcard__actions">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
              <button class="btn btn--icon" name="dir" value="up" <?= $n === 0 ? 'disabled' : '' ?> aria-label="Move <?= e($it['title']) ?> up">↑</button>
              <button class="btn btn--icon" name="dir" value="down" <?= $n === count($items) - 1 ? 'disabled' : '' ?> aria-label="Move <?= e($it['title']) ?> down">↓</button>
            </form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
              <button class="btn btn--sm"><?= $it['is_visible'] ? 'Hide' : 'Show' ?></button>
            </form>
          </div>
          <details class="gcard__edit">
            <summary>Edit</summary>
            <form method="post" class="form">
              <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
              <label class="field"><span>Title</span><input name="title" value="<?= e($it['title']) ?>" maxlength="120" required></label>
              <label class="field"><span>Category</span><select name="category"><?= options(GALLERY_CATEGORIES, $it['category']) ?></select></label>
              <label class="field"><span>Tile shape</span><select name="size"><?= options(GALLERY_SIZES, $it['size']) ?></select></label>
              <label class="field"><span>Keep in view</span><select name="focus"><?= options(GALLERY_FOCUS + [$it['focus'] => 'Custom (' . $it['focus'] . ')'], $it['focus']) ?></select></label>
              <label class="field"><span>Description</span><input name="alt" value="<?= e($it['alt']) ?>" maxlength="250"></label>
              <label class="check"><input type="checkbox" name="is_sample" value="1" <?= $it['is_sample'] ? 'checked' : '' ?>> <span>Sample / stock photo</span></label>
              <button class="btn btn--primary btn--block">Save changes</button>
            </form>
            <form method="post" class="confirm-delete">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
              <label class="check"><input type="checkbox" required> <span>Yes, remove this design</span></label>
              <button class="btn btn--danger btn--block">Remove from gallery</button>
            </form>
          </details>
        </div>
      </li>
<?php endforeach; ?>
    </ol>
<?php endif; ?>
    <script src="gallery.js" defer></script>
<?php
admin_footer();
