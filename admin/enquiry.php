<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/admin-layout.php';

$admin = require_admin();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM enquiries WHERE id = ?');
$stmt->execute([$id]);
$enq = $stmt->fetch();
if (!$enq) {
    http_response_code(404);
    admin_header('Not found', $admin, 'inbox');
    echo '<div class="empty"><p class="empty__title">That enquiry no longer exists.</p><p><a class="btn" href="enquiries">Back to enquiries</a></p></div>';
    admin_footer();
    exit;
}

/* ------------------------------------------------------------- Actions */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'status' && isset(STATUSES[$_POST['status'] ?? ''])) {
        db()->prepare('UPDATE enquiries SET status = ? WHERE id = ?')->execute([$_POST['status'], $id]);
        flash('Marked as ' . strtolower(STATUSES[$_POST['status']]) . '.');
        redirect("enquiry?id=$id");
    }

    if ($action === 'notes') {
        $notes = clean_text($_POST['admin_notes'] ?? '', 5000);
        db()->prepare('UPDATE enquiries SET admin_notes = ? WHERE id = ?')->execute([$notes !== '' ? $notes : null, $id]);
        flash('Notes saved.');
        redirect("enquiry?id=$id");
    }

    if ($action === 'delete') {
        $files = db()->prepare('SELECT stored_path FROM enquiry_files WHERE enquiry_id = ?');
        $files->execute([$id]);
        foreach ($files->fetchAll(PDO::FETCH_COLUMN) as $rel) {
            $path = realpath(UPLOAD_DIR . '/' . $rel);
            if ($path && str_starts_with($path, realpath(UPLOAD_DIR))) {
                @unlink($path);
            }
        }
        db()->prepare('DELETE FROM enquiries WHERE id = ?')->execute([$id]);   // files rows cascade
        flash("Enquiry {$enq['ref']} deleted.");
        redirect('enquiries');
    }

    flash('Unknown action.', 'error');
    redirect("enquiry?id=$id");
}

// Opening a new enquiry doesn't change its status: the owner decides when it's "contacted".
$fileStmt = db()->prepare('SELECT id, original_name, mime, size_bytes FROM enquiry_files WHERE enquiry_id = ? ORDER BY id');
$fileStmt->execute([$id]);
$files = $fileStmt->fetchAll();

$wa = $enq['phone'] ? whatsapp_digits($enq['phone']) : '';
$firstName = explode(' ', $enq['name'])[0];
$service = SERVICES[$enq['service']] ?? $enq['service'];
$waText = "Hello $firstName, thank you for your enquiry ({$enq['ref']}) about " . strtolower($service) . ' with Reign Fashion Empire.';
$mailSubject = "Your Reign Fashion enquiry {$enq['ref']}";

admin_header($enq['ref'] . ' · ' . $enq['name'], $admin, 'inbox');
?>
    <p class="crumbs"><a href="enquiries">← All enquiries</a></p>

    <div class="page__head">
      <div>
        <h1 class="page__title"><?= e($enq['name']) ?></h1>
        <p class="page__sub"><?= e($enq['ref']) ?> · received <?= e(format_date($enq['created_at'])) ?></p>
      </div>
      <span class="badge badge--<?= e($enq['status']) ?> badge--lg"><?= e(STATUSES[$enq['status']]) ?></span>
    </div>

    <div class="detail">
      <section class="card detail__main" aria-labelledby="msg-h">
        <h2 id="msg-h" class="card__title">Enquiry</h2>
        <dl class="facts">
          <div><dt>Service</dt><dd><?= e($service) ?></dd></div>
          <div><dt>Event date</dt><dd><?= e(format_date($enq['event_date'], 'l, j F Y')) ?></dd></div>
          <div><dt>Prefers</dt><dd><?= e(CONTACT_METHODS[$enq['contact_method']] ?? $enq['contact_method']) ?></dd></div>
          <div><dt>Email</dt><dd><a href="mailto:<?= e($enq['email']) ?>"><?= e($enq['email']) ?></a></dd></div>
          <div><dt>Phone</dt><dd><?= $enq['phone'] ? '<a href="tel:' . e(preg_replace('/[^\d+]/', '', $enq['phone'])) . '">' . e($enq['phone']) . '</a>' : '—' ?></dd></div>
        </dl>
        <h3 class="card__sub">Message</h3>
        <p class="message"><?= nl2br(e($enq['message'])) ?></p>

<?php if ($files): ?>
        <h3 class="card__sub">Inspiration images</h3>
        <ul class="thumbs">
<?php foreach ($files as $f): ?>
          <li>
            <a href="file?id=<?= (int) $f['id'] ?>" target="_blank" rel="noopener">
              <img src="file?id=<?= (int) $f['id'] ?>" alt="Inspiration image <?= e($f['original_name']) ?>" loading="lazy">
            </a>
            <span><?= e($f['original_name']) ?> · <?= number_format($f['size_bytes'] / 1048576, 1) ?> MB</span>
          </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
      </section>

      <aside class="detail__side">
        <section class="card" aria-labelledby="reply-h">
          <h2 id="reply-h" class="card__title">Reply</h2>
          <div class="stack">
<?php if ($wa): ?>
            <a class="btn btn--primary btn--block" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode($waText)) ?>" target="_blank" rel="noopener">WhatsApp <?= e($firstName) ?></a>
            <a class="btn btn--block" href="tel:<?= e(preg_replace('/[^\d+]/', '', $enq['phone'])) ?>">Call <?= e($enq['phone']) ?></a>
<?php endif; ?>
            <a class="btn btn--block" href="mailto:<?= e($enq['email']) ?>?subject=<?= e(rawurlencode($mailSubject)) ?>">Email <?= e($firstName) ?></a>
          </div>
        </section>

        <section class="card" aria-labelledby="pay-h">
          <h2 id="pay-h" class="card__title">Payment details</h2>
          <div class="stack">
            <?php payment_buttons($enq['ref'], $wa, $enq['email'], $firstName); ?>
          </div>
        </section>

        <section class="card" aria-labelledby="status-h">
          <h2 id="status-h" class="card__title">Status</h2>
          <form method="post" class="segmented">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <input type="hidden" name="action" value="status">
<?php foreach (STATUSES as $key => $label): ?>
            <button type="submit" name="status" value="<?= e($key) ?>" <?= $enq['status'] === $key ? 'aria-pressed="true" disabled' : 'aria-pressed="false"' ?>><?= e($label) ?></button>
<?php endforeach; ?>
          </form>
        </section>

        <section class="card" aria-labelledby="notes-h">
          <h2 id="notes-h" class="card__title">Private notes</h2>
          <form method="post" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <input type="hidden" name="action" value="notes">
            <label class="field">
              <span class="visually-hidden">Notes (only visible to admins)</span>
              <textarea name="admin_notes" rows="5" placeholder="Measurements, fabric choices, quote, fitting dates…"><?= e($enq['admin_notes']) ?></textarea>
            </label>
            <button type="submit" class="btn btn--block">Save notes</button>
          </form>
        </section>

        <section class="card card--danger" aria-labelledby="del-h">
          <h2 id="del-h" class="card__title">Delete</h2>
          <p class="hint">Permanently removes this enquiry and its images.</p>
          <form method="post" class="confirm-delete">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <input type="hidden" name="action" value="delete">
            <label class="check">
              <input type="checkbox" required>
              <span>Yes, delete <?= e($enq['ref']) ?></span>
            </label>
            <button type="submit" class="btn btn--danger btn--block">Delete enquiry</button>
          </form>
        </section>
      </aside>
    </div>
<?php
admin_footer();
