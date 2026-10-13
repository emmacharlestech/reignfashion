<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/admin-layout.php';

$admin = require_admin();
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();
    $current = (string) ($_POST['current'] ?? '');
    $new     = (string) ($_POST['new'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    $stmt = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
    $stmt->execute([$admin['id']]);
    $hash = (string) $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $errors[] = 'Your current password is not correct.';
    }
    if (strlen($new) < 10) {
        $errors[] = 'The new password must be at least 10 characters.';
    }
    if ($new !== $confirm) {
        $errors[] = 'The new passwords do not match.';
    }
    if (!$errors && password_verify($new, $hash)) {
        $errors[] = 'Please choose a password different from your current one.';
    }

    if (!$errors) {
        db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
        session_regenerate_id(true);
        flash('Your password has been changed.');
        redirect('password.php');
    }
}

admin_header('Account', $admin, 'account');
?>
    <div class="page__head">
      <div>
        <h1 class="page__title">Account</h1>
        <p class="page__sub"><?= e($admin['name']) ?> · <?= e($admin['email']) ?> · last login <?= e(format_date($admin['last_login_at'])) ?></p>
      </div>
    </div>

    <section class="card card--narrow" aria-labelledby="pw-h">
      <h2 id="pw-h" class="card__title">Change password</h2>
<?php if ($errors): ?>
      <div class="flash flash--error" role="alert"><?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?></div>
<?php endif; ?>
      <form method="post" class="form">
        <?= csrf_field() ?>
        <label class="field"><span>Current password</span>
          <input type="password" name="current" autocomplete="current-password" required></label>
        <label class="field"><span>New password <small>(at least 10 characters)</small></span>
          <input type="password" name="new" autocomplete="new-password" minlength="10" required></label>
        <label class="field"><span>Confirm new password</span>
          <input type="password" name="confirm" autocomplete="new-password" minlength="10" required></label>
        <button type="submit" class="btn btn--primary">Change password</button>
      </form>
    </section>
<?php
admin_footer();
