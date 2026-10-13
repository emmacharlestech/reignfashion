<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/academy.php';
require __DIR__ . '/../backend/admin-layout.php';

$admin = require_admin();

/* ---------------------------------------------------------------- actions */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $back = 'academy.php' . (!empty($_POST['return']) && preg_match('/^[\w=&%-]*$/', $_POST['return']) ? '?' . $_POST['return'] : '');

    if ($action === 'status' && isset(ACADEMY_STATUSES[$_POST['status'] ?? ''])) {
        db()->prepare('UPDATE academy_registrations SET status = ? WHERE id = ?')->execute([$_POST['status'], $id]);
        flash('Marked as ' . strtolower(ACADEMY_STATUSES[$_POST['status']]) . '.');
    } elseif ($action === 'notes') {
        $notes = clean_text($_POST['admin_notes'] ?? '', 5000);
        db()->prepare('UPDATE academy_registrations SET admin_notes = ? WHERE id = ?')->execute([$notes !== '' ? $notes : null, $id]);
        flash('Notes saved.');
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM academy_registrations WHERE id = ?')->execute([$id]);
        flash('Registration deleted.');
    }
    redirect($back . '#r-' . $id);
}

/* ---------------------------------------------------------------- list */
$status = (string) ($_GET['status'] ?? 'all');
if ($status !== 'all' && !isset(ACADEMY_STATUSES[$status])) $status = 'all';
$q = clean_line($_GET['q'] ?? '', 100);

$counts = ['all' => 0] + array_fill_keys(array_keys(ACADEMY_STATUSES), 0);
foreach (db()->query('SELECT status, COUNT(*) n FROM academy_registrations GROUP BY status') as $row) {
    $counts[$row['status']] = (int) $row['n'];
    $counts['all'] += (int) $row['n'];
}

$where = []; $args = [];
if ($status !== 'all') { $where[] = 'status = ?'; $args[] = $status; }
if ($q !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR ref LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($args, $like, $like, $like, $like);
}
$stmt = db()->prepare('SELECT * FROM academy_registrations' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC, id DESC LIMIT 200');
$stmt->execute($args);
$rows = $stmt->fetchAll();
$returnQs = http_build_query(array_filter(['status' => $status !== 'all' ? $status : '', 'q' => $q]));

admin_header('Academy', $admin, 'academy');
?>
    <div class="page__head">
      <div>
        <h1 class="page__title">Academy registrations</h1>
        <p class="page__sub"><?= $counts['new'] ? '<strong>' . $counts['new'] . ' new</strong> ' . ($counts['new'] === 1 ? 'registration needs' : 'registrations need') . ' a reply.' : 'You are all caught up.' ?>
          Registrations come from the form on the home page.</p>
      </div>
      <form class="search" method="get" role="search">
<?php if ($status !== 'all'): ?>
        <input type="hidden" name="status" value="<?= e($status) ?>">
<?php endif; ?>
        <label class="visually-hidden" for="q">Search registrations</label>
        <input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email, phone or ref">
        <button type="submit" class="btn btn--primary">Search</button>
      </form>
    </div>

    <nav class="tabs" aria-label="Filter by status">
<?php foreach (['all' => 'All'] + ACADEMY_STATUSES as $key => $label): ?>
      <a href="academy.php<?= $key === 'all' ? '' : '?status=' . e($key) ?>" class="tabs__tab" <?= $status === $key ? 'aria-current="page"' : '' ?>>
        <?= e($label) ?> <span class="tabs__count"><?= $counts[$key] ?></span>
      </a>
<?php endforeach; ?>
    </nav>

<?php if (!$rows): ?>
    <div class="empty">
      <p class="empty__title"><?= $q !== '' ? 'No registrations match “' . e($q) . '”.' : 'No registrations here yet.' ?></p>
      <p>New registrations from the website's Academy form appear here automatically.</p>
    </div>
<?php else: ?>
    <ul class="reglist">
<?php foreach ($rows as $r):
        $first = explode(' ', $r['name'])[0];
        $wa = whatsapp_digits($r['phone']);
        $waText = "Hello $first, thank you for registering with the Reign Fashion Academy ({$r['ref']}).";
?>
      <li class="card regitem<?= $r['status'] === 'new' ? ' is-new' : '' ?>" id="r-<?= (int) $r['id'] ?>">
        <div class="regitem__head">
          <div>
            <p class="regitem__name"><?= e($r['name']) ?></p>
            <p class="regitem__meta"><?= e(ACADEMY_LEVELS[$r['level']]) ?> · <?= e(ACADEMY_SCHEDULES[$r['schedule']]) ?> · <?= e(format_date($r['created_at'], 'j M Y, g:ia')) ?> · <?= e($r['ref']) ?></p>
          </div>
          <span class="badge badge--<?= $r['status'] === 'enrolled' ? 'closed' : e($r['status']) ?>"><?= e(ACADEMY_STATUSES[$r['status']]) ?></span>
        </div>

        <dl class="facts">
          <div><dt>Phone</dt><dd><a href="tel:<?= e(preg_replace('/[^\d+]/', '', $r['phone'])) ?>"><?= e($r['phone']) ?></a></dd></div>
          <div><dt>Email</dt><dd><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></dd></div>
<?php if ($r['experience']): ?>
          <div><dt>Experience</dt><dd><?= nl2br(e($r['experience'])) ?></dd></div>
<?php endif; ?>
        </dl>

        <div class="regitem__actions">
          <a class="btn btn--primary btn--sm" href="https://wa.me/<?= e($wa) ?>?text=<?= e(rawurlencode($waText)) ?>" target="_blank" rel="noopener">WhatsApp <?= e($first) ?></a>
          <a class="btn btn--sm" href="tel:<?= e(preg_replace('/[^\d+]/', '', $r['phone'])) ?>">Call</a>
          <a class="btn btn--sm" href="mailto:<?= e($r['email']) ?>?subject=<?= e(rawurlencode("Reign Fashion Academy registration {$r['ref']}")) ?>">Email</a>
        </div>

        <form method="post" class="segmented segmented--4">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="return" value="<?= e($returnQs) ?>">
<?php foreach (ACADEMY_STATUSES as $key => $label): ?>
          <button type="submit" name="status" value="<?= e($key) ?>" <?= $r['status'] === $key ? 'aria-pressed="true" disabled' : 'aria-pressed="false"' ?>><?= e($label) ?></button>
<?php endforeach; ?>
        </form>

        <details class="gcard__edit">
          <summary>Notes &amp; delete</summary>
          <form method="post" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <input type="hidden" name="action" value="notes">
            <input type="hidden" name="return" value="<?= e($returnQs) ?>">
            <label class="field"><span>Private notes</span>
              <textarea name="admin_notes" rows="3" placeholder="Class allocated, payment, start date…"><?= e($r['admin_notes']) ?></textarea></label>
            <button type="submit" class="btn btn--block">Save notes</button>
          </form>
          <form method="post" class="confirm-delete">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <input type="hidden" name="action" value="delete">
            <label class="check"><input type="checkbox" required> <span>Yes, delete <?= e($r['ref']) ?></span></label>
            <button type="submit" class="btn btn--danger btn--block">Delete registration</button>
          </form>
        </details>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php
admin_footer();
