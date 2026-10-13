<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/testimonials.php';
require __DIR__ . '/../backend/admin-layout.php';

$admin = require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    try {
        if ($action === 'add') {
            $f = testimonial_fields($_POST);
            $last = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) FROM testimonials')->fetchColumn();
            db()->prepare('INSERT INTO testimonials (quote, client_name, service, consent_confirmed, sort_order) VALUES (?, ?, ?, ?, ?)')
                ->execute([$f['quote'], $f['client_name'], $f['service'], $f['consent'], $last + 10]);
            flash("Testimonial from {$f['client_name']} published.");
        } elseif ($action === 'update') {
            $f = testimonial_fields($_POST);
            db()->prepare('UPDATE testimonials SET quote = ?, client_name = ?, service = ?, consent_confirmed = ? WHERE id = ?')
                ->execute([$f['quote'], $f['client_name'], $f['service'], $f['consent'], $id]);
            flash("Testimonial from {$f['client_name']} updated.");
        } elseif ($action === 'toggle') {
            db()->prepare('UPDATE testimonials SET is_visible = 1 - is_visible WHERE id = ?')->execute([$id]);
            flash('Visibility updated.');
        } elseif ($action === 'move') {
            $ids = array_map('intval', array_column(testimonials(), 'id'));
            $pos = array_search($id, $ids, true);
            $swap = ($_POST['dir'] ?? '') === 'up' ? $pos - 1 : $pos + 1;
            if ($pos !== false && isset($ids[$swap])) {
                [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
                $upd = db()->prepare('UPDATE testimonials SET sort_order = ? WHERE id = ?');
                foreach ($ids as $i => $tid) $upd->execute([($i + 1) * 10, $tid]);
            }
            redirect('testimonials#t-' . $id);
        } elseif ($action === 'delete') {
            db()->prepare('DELETE FROM testimonials WHERE id = ?')->execute([$id]);
            flash('Testimonial removed.');
        }
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'error');
    }
    redirect('testimonials');
}

$items = testimonials();
$published = count(array_filter($items, fn($t) => $t['is_visible'] && $t['consent_confirmed']));

function service_options(string $current): string
{
    $html = '<option value="">Choose…</option>';
    foreach (TESTIMONIAL_SERVICES as $k => $label) {
        $html .= '<option value="' . e($k) . '"' . ($k === $current ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $html;
}

admin_header('Testimonials', $admin, 'testimonials');
?>
    <div class="page__head">
      <div>
        <h1 class="page__title">Testimonials</h1>
        <p class="page__sub"><?= $published
            ? $published . ' published in “What Our Clients Say”.'
            : 'None published yet: the website shows its placeholder slides until you add one.' ?></p>
      </div>
      <a class="btn" href="../#testimonials" target="_blank" rel="noopener">View on site ↗</a>
    </div>

    <details class="card upload" <?= $items ? '' : 'open' ?>>
      <summary class="upload__summary">+ Add a testimonial</summary>
      <form method="post" class="form form--grid upload__form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="field field--full">
          <label for="t-quote">What the client said</label>
          <textarea id="t-quote" name="quote" rows="4" maxlength="<?= TESTIMONIAL_MAX_QUOTE ?>" required aria-describedby="t-quote-h"></textarea>
          <small id="t-quote-h" class="field__help">Use their own words (lightly trimmed is fine). Up to <?= TESTIMONIAL_MAX_QUOTE ?> characters; 1–3 sentences reads best.</small>
        </div>
        <div class="field"><label for="t-name">Client name, as they'd like it shown</label>
          <input id="t-name" name="client_name" maxlength="80" required placeholder="e.g. Adaeze O. or Mrs. Okafor"></div>
        <div class="field"><label for="t-service">Service</label>
          <select id="t-service" name="service" required><?= service_options('') ?></select></div>
        <label class="check field--full">
          <input type="checkbox" name="consent" value="1" required>
          <span>The client agreed to have these words and their name shown on the website.</span>
        </label>
        <div class="field--full"><button type="submit" class="btn btn--primary">Publish testimonial</button></div>
      </form>
    </details>

<?php if (!$items): ?>
    <div class="empty"><p class="empty__title">No testimonials yet.</p>
      <p>Ask happy clients for a sentence or two about their outfit or training, and their permission to share it.</p></div>
<?php else: ?>
    <ol class="tlist">
<?php foreach ($items as $n => $t): ?>
      <li class="card tcard<?= $t['is_visible'] ? '' : ' is-hidden' ?>" id="t-<?= (int) $t['id'] ?>">
        <blockquote class="tcard__quote">“<?= e($t['quote']) ?>”</blockquote>
        <p class="tcard__who"><strong><?= e($t['client_name']) ?></strong> · <?= e(TESTIMONIAL_SERVICES[$t['service']] ?? $t['service']) ?>
          <?php if (!$t['is_visible']): ?><span class="badge badge--closed">Hidden</span><?php endif; ?></p>
        <div class="gcard__actions">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <button class="btn btn--icon" name="dir" value="up" <?= $n === 0 ? 'disabled' : '' ?> aria-label="Move testimonial from <?= e($t['client_name']) ?> up">↑</button>
            <button class="btn btn--icon" name="dir" value="down" <?= $n === count($items) - 1 ? 'disabled' : '' ?> aria-label="Move testimonial from <?= e($t['client_name']) ?> down">↓</button>
          </form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <button class="btn btn--sm"><?= $t['is_visible'] ? 'Hide' : 'Show' ?></button>
          </form>
        </div>
        <details class="gcard__edit">
          <summary>Edit</summary>
          <form method="post" class="form">
            <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <label class="field"><span>Quote</span><textarea name="quote" rows="4" maxlength="<?= TESTIMONIAL_MAX_QUOTE ?>" required><?= e($t['quote']) ?></textarea></label>
            <label class="field"><span>Client name</span><input name="client_name" value="<?= e($t['client_name']) ?>" maxlength="80" required></label>
            <label class="field"><span>Service</span><select name="service" required><?= service_options($t['service']) ?></select></label>
            <label class="check"><input type="checkbox" name="consent" value="1" required <?= $t['consent_confirmed'] ? 'checked' : '' ?>> <span>Client agreed to be shown</span></label>
            <button class="btn btn--primary btn--block">Save changes</button>
          </form>
          <form method="post" class="confirm-delete">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <label class="check"><input type="checkbox" required> <span>Yes, remove this testimonial</span></label>
            <button class="btn btn--danger btn--block">Remove</button>
          </form>
        </details>
      </li>
<?php endforeach; ?>
    </ol>
<?php endif; ?>
<?php
admin_footer();
