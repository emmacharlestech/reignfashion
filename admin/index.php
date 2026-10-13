<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/admin-layout.php';

$admin = require_admin();

const PER_PAGE = 20;

$status = (string) ($_GET['status'] ?? 'all');
if ($status !== 'all' && !isset(STATUSES[$status])) {
    $status = 'all';
}
$q    = clean_line($_GET['q'] ?? '', 100);
$page = max(1, (int) ($_GET['page'] ?? 1));

// Counts for the tabs
$counts = ['all' => 0] + array_fill_keys(array_keys(STATUSES), 0);
foreach (db()->query('SELECT status, COUNT(*) n FROM enquiries GROUP BY status') as $row) {
    $counts[$row['status']] = (int) $row['n'];
    $counts['all'] += (int) $row['n'];
}

// Filtered list
$where = [];
$args  = [];
if ($status !== 'all') {
    $where[] = 'e.status = ?';
    $args[]  = $status;
}
if ($q !== '') {
    $where[] = '(e.name LIKE ? OR e.email LIKE ? OR e.phone LIKE ? OR e.ref LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($args, $like, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = db()->prepare("SELECT COUNT(*) FROM enquiries e $whereSql");
$countStmt->execute($args);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / PER_PAGE));
$page  = min($page, $pages);

$listStmt = db()->prepare("SELECT e.id, e.ref, e.name, e.service, e.contact_method, e.event_date, e.status, e.created_at,
        (SELECT COUNT(*) FROM enquiry_files f WHERE f.enquiry_id = e.id) AS files
    FROM enquiries e $whereSql
    ORDER BY e.created_at DESC, e.id DESC
    LIMIT " . PER_PAGE . ' OFFSET ' . (($page - 1) * PER_PAGE));
$listStmt->execute($args);
$rows = $listStmt->fetchAll();

/** Build an inbox URL keeping the current filters. */
function inbox_url(array $changes): string
{
    global $status, $q, $page;
    $params = array_filter(array_merge(['status' => $status, 'q' => $q, 'page' => $page], $changes),
        fn($v) => $v !== '' && $v !== 'all' && $v !== 1);
    return './' . ($params ? '?' . http_build_query($params) : '');
}

admin_header('Enquiries', $admin, 'inbox');
?>
    <div class="page__head">
      <div>
        <h1 class="page__title">Enquiries</h1>
        <p class="page__sub">Welcome back, <?= e($admin['name']) ?>.
          <?= $counts['new'] ? '<strong>' . $counts['new'] . ' new</strong> ' . ($counts['new'] === 1 ? 'enquiry needs' : 'enquiries need') . ' a reply.' : 'You are all caught up.' ?></p>
      </div>
      <form class="search" method="get" role="search">
<?php if ($status !== 'all'): ?>
        <input type="hidden" name="status" value="<?= e($status) ?>">
<?php endif; ?>
        <label class="visually-hidden" for="q">Search enquiries</label>
        <input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email, phone or ref">
        <button type="submit" class="btn btn--primary">Search</button>
      </form>
    </div>

    <nav class="tabs" aria-label="Filter by status">
<?php foreach (['all' => 'All'] + STATUSES as $key => $label): ?>
      <a href="<?= e(inbox_url(['status' => $key, 'page' => 1])) ?>" class="tabs__tab" <?= $status === $key ? 'aria-current="page"' : '' ?>>
        <?= e($label) ?> <span class="tabs__count"><?= $counts[$key] ?></span>
      </a>
<?php endforeach; ?>
    </nav>

<?php if (!$rows): ?>
    <div class="empty">
      <p class="empty__title"><?= $q !== '' ? 'No enquiries match “' . e($q) . '”.' : 'No enquiries here yet.' ?></p>
      <p>New enquiries from the website's contact form will appear here automatically.</p>
    </div>
<?php else: ?>
    <ul class="inbox">
<?php foreach ($rows as $r): ?>
      <li class="inbox__item<?= $r['status'] === 'new' ? ' is-new' : '' ?>">
        <a href="enquiry?id=<?= (int) $r['id'] ?>" class="inbox__link">
          <span class="inbox__main">
            <span class="inbox__name"><?= e($r['name']) ?></span>
            <span class="inbox__meta"><?= e(SERVICES[$r['service']] ?? $r['service']) ?>
              <?php if ($r['event_date']): ?> · Event <?= e(format_date($r['event_date'], 'j M Y')) ?><?php endif; ?>
              <?php if ($r['files']): ?> · <?= (int) $r['files'] ?> image<?= $r['files'] > 1 ? 's' : '' ?><?php endif; ?></span>
          </span>
          <span class="inbox__side">
            <span class="badge badge--<?= e($r['status']) ?>"><?= e(STATUSES[$r['status']]) ?></span>
            <span class="inbox__date"><?= e(format_date($r['created_at'], 'j M, g:ia')) ?></span>
            <span class="inbox__ref"><?= e($r['ref']) ?></span>
          </span>
        </a>
      </li>
<?php endforeach; ?>
    </ul>

<?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Pages">
      <?php if ($page > 1): ?><a class="btn" href="<?= e(inbox_url(['page' => $page - 1])) ?>">← Newer</a><?php endif; ?>
      <span>Page <?= $page ?> of <?= $pages ?></span>
      <?php if ($page < $pages): ?><a class="btn" href="<?= e(inbox_url(['page' => $page + 1])) ?>">Older →</a><?php endif; ?>
    </nav>
<?php endif; ?>
<?php endif; ?>
<?php
admin_footer();
