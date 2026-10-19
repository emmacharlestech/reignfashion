<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/academy.php';
require __DIR__ . '/../backend/notify.php';
require __DIR__ . '/../backend/admin-layout.php';

$admin = require_admin();
$pdo = db();
$num = fn(string $sql) => (int) $pdo->query($sql)->fetchColumn();

$stats = [
    ['New enquiries', $num("SELECT COUNT(*) FROM enquiries WHERE status = 'new'"), 'enquiries?status=new', 'waiting for a reply'],
    ['New Academy registrations', $num("SELECT COUNT(*) FROM academy_registrations WHERE status = 'new'"), 'academy?status=new', 'waiting for a reply'],
    ['Enquiries this month', $num("SELECT COUNT(*) FROM enquiries WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"), 'enquiries', date('F Y')],
    ['Students enrolled', $num("SELECT COUNT(*) FROM academy_registrations WHERE status = 'enrolled'"), 'academy?status=enrolled', 'in total'],
];

$upcoming = $pdo->query("SELECT id, ref, name, service, event_date, status FROM enquiries
    WHERE event_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY AND status <> 'closed'
    ORDER BY event_date, id LIMIT 8")->fetchAll();
$latestEnq = $pdo->query('SELECT id, ref, name, service, status, created_at FROM enquiries ORDER BY created_at DESC, id DESC LIMIT 5')->fetchAll();
$latestReg = $pdo->query('SELECT id, ref, name, level, status, created_at FROM academy_registrations ORDER BY created_at DESC, id DESC LIMIT 5')->fetchAll();

// What the owner still needs to fill in before the site is complete
$s = get_settings();
$todo = [];
if ($s['hours'] === '') $todo[] = ['Add your opening hours', 'settings#g-contact'];
$missingSocial = array_filter(['Instagram' => 'instagram_url', 'Facebook' => 'facebook_url', 'TikTok' => 'tiktok_url'], fn($k) => $s[$k] === '');
if ($missingSocial) $todo[] = ['Add your ' . implode(', ', array_keys($missingSocial)) . ' link' . (count($missingSocial) > 1 ? 's' : ''), 'settings#g-social'];
if ($s['academy_duration'] === '' || $s['academy_fees'] === '' || $s['academy_intake'] === '') $todo[] = ['Add Academy duration, fees and next intake', 'settings#g-academy'];
if ($s['payment_methods'] === '') $todo[] = ['Choose how customers can pay', 'settings#g-payment'];
if (!bank_accounts($s)) $todo[] = ['Add your bank account details', 'settings#g-payment'];
if (($p = alert_problem($s)) !== '' && $s['alerts'] === 'on') $todo[] = ['Email alerts: ' . rtrim(lcfirst($p), '.'), 'settings#g-alerts'];
if ($num('SELECT COUNT(*) FROM testimonials WHERE is_visible = 1') === 0) $todo[] = ['Publish your first client testimonial', 'testimonials'];
if (($samples = $num('SELECT COUNT(*) FROM gallery_items WHERE is_visible = 1 AND is_sample = 1')) > 0) {
    $todo[] = ["Replace $samples sample photo" . ($samples > 1 ? 's' : '') . ' in the gallery with your own', 'gallery'];
}

admin_header('Dashboard', $admin, 'dashboard');
?>
    <div class="page__head">
      <div>
        <h1 class="page__title">Welcome back, <?= e(explode(' ', $admin['name'])[0]) ?></h1>
        <p class="page__sub"><?= e(date('l, j F Y')) ?></p>
      </div>
    </div>

    <ul class="stats">
<?php foreach ($stats as [$label, $value, $href, $note]): ?>
      <li><a class="stat<?= $value && str_starts_with($label, 'New') ? ' is-alert' : '' ?>" href="<?= e($href) ?>">
        <span class="stat__value"><?= $value ?></span>
        <span class="stat__label"><?= e($label) ?></span>
        <span class="stat__note"><?= e($note) ?></span>
      </a></li>
<?php endforeach; ?>
    </ul>

    <div class="dash">
      <section class="card" aria-labelledby="up-h">
        <h2 id="up-h" class="card__title">Occasions in the next 30 days</h2>
<?php if (!$upcoming): ?>
        <p class="hint">No open enquiries have an occasion date in the next 30 days.</p>
<?php else: ?>
        <ul class="minilist">
<?php foreach ($upcoming as $u):
          $days = (int) (new DateTime('today'))->diff(new DateTime($u['event_date']))->days; ?>
          <li><a href="enquiry?id=<?= (int) $u['id'] ?>">
            <span class="minilist__main"><strong><?= e($u['name']) ?></strong> · <?= e(SERVICES[$u['service']] ?? $u['service']) ?></span>
            <span class="minilist__side"><?= e(format_date($u['event_date'], 'D j M')) ?> · <?= $days === 0 ? 'today' : ($days === 1 ? 'tomorrow' : "in $days days") ?></span>
          </a></li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
      </section>

<?php if ($todo): ?>
      <section class="card" aria-labelledby="todo-h">
        <h2 id="todo-h" class="card__title">Finish setting up the website</h2>
        <ul class="todo">
<?php foreach ($todo as [$text, $href]): ?>
          <li><a href="<?= e($href) ?>"><?= e($text) ?> →</a></li>
<?php endforeach; ?>
        </ul>
      </section>
<?php endif; ?>

      <section class="card" aria-labelledby="le-h">
        <h2 id="le-h" class="card__title">Latest enquiries</h2>
<?php if (!$latestEnq): ?>
        <p class="hint">None yet. Enquiries from the website's contact form appear here.</p>
<?php else: ?>
        <ul class="minilist">
<?php foreach ($latestEnq as $r): ?>
          <li><a href="enquiry?id=<?= (int) $r['id'] ?>">
            <span class="minilist__main"><strong><?= e($r['name']) ?></strong> · <?= e(SERVICES[$r['service']] ?? $r['service']) ?></span>
            <span class="minilist__side"><span class="badge badge--<?= e($r['status']) ?>"><?= e(STATUSES[$r['status']]) ?></span> <?= e(format_date($r['created_at'], 'j M')) ?></span>
          </a></li>
<?php endforeach; ?>
        </ul>
        <p class="dash__more"><a href="enquiries">All enquiries →</a></p>
<?php endif; ?>
      </section>

      <section class="card" aria-labelledby="lr-h">
        <h2 id="lr-h" class="card__title">Latest Academy registrations</h2>
<?php if (!$latestReg): ?>
        <p class="hint">None yet. Registrations from the Academy form appear here.</p>
<?php else: ?>
        <ul class="minilist">
<?php foreach ($latestReg as $r): ?>
          <li><a href="academy#r-<?= (int) $r['id'] ?>">
            <span class="minilist__main"><strong><?= e($r['name']) ?></strong> · <?= e(ACADEMY_LEVELS[$r['level']]) ?></span>
            <span class="minilist__side"><span class="badge badge--<?= $r['status'] === 'enrolled' ? 'closed' : e($r['status']) ?>"><?= e(ACADEMY_STATUSES[$r['status']]) ?></span> <?= e(format_date($r['created_at'], 'j M')) ?></span>
          </a></li>
<?php endforeach; ?>
        </ul>
        <p class="dash__more"><a href="academy">All registrations →</a></p>
<?php endif; ?>
      </section>
    </div>
<?php
admin_footer();
