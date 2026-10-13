<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/settings.php';
require __DIR__ . '/../backend/admin-layout.php';

$admin  = require_admin();
$values = get_settings();
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();
    [$clean, $errors] = validate_settings($_POST);
    if (!$errors) {
        save_settings($clean);
        flash('Settings saved. The website shows the new details within a minute.');
        redirect('settings');
    }
    $values = $clean;   // keep what was typed so nothing is lost
}

admin_header('Settings', $admin, 'settings');
?>
    <div class="page__head">
      <div>
        <h1 class="page__title">Site settings</h1>
        <p class="page__sub">Update the details shown on the website. Leave a field empty to show “To be confirmed”.</p>
      </div>
    </div>

<?php if ($errors): ?>
    <div class="flash flash--error" role="alert">
      <p><strong>Please fix <?= count($errors) === 1 ? 'this' : 'these' ?> before saving:</strong></p>
<?php foreach ($errors as $err): ?>
      <p><?= e($err) ?></p>
<?php endforeach; ?>
    </div>
<?php endif; ?>

    <form method="post" class="settings" novalidate>
      <?= csrf_field() ?>
<?php foreach (SETTING_GROUPS as $group => $groupLabel): ?>
      <section class="card" aria-labelledby="g-<?= e($group) ?>">
        <h2 id="g-<?= e($group) ?>" class="card__title"><?= e($groupLabel) ?></h2>
        <div class="form form--grid">
<?php foreach (SETTING_DEFS as $key => [$g, $label, $type, $default, $max, $help]):
        if ($g !== $group) continue;
        $id = 's-' . $key;
        $invalid = isset($errors[$key]);
        $inputType = $type === 'email' ? 'email' : (str_starts_with($type, 'url:') ? 'url' : ($type === 'phone' ? 'tel' : 'text'));
?>
          <div class="field<?= $type === 'textarea' ? ' field--full' : '' ?><?= $invalid ? ' is-invalid' : '' ?>">
            <label for="<?= e($id) ?>"><?= e($label) ?></label>
<?php if ($type === 'textarea'): ?>
            <textarea id="<?= e($id) ?>" name="<?= e($key) ?>" rows="2" maxlength="<?= (int) $max ?>"<?= $help ? ' aria-describedby="' . e($id) . '-h"' : '' ?>><?= e($values[$key]) ?></textarea>
<?php else: ?>
            <input id="<?= e($id) ?>" type="<?= $inputType ?>" name="<?= e($key) ?>" value="<?= e($values[$key]) ?>" maxlength="<?= (int) $max ?>"
                   <?= $inputType === 'email' ? 'autocomplete="email"' : '' ?><?= $invalid ? ' aria-invalid="true"' : '' ?><?= $help ? ' aria-describedby="' . e($id) . '-h"' : '' ?>>
<?php endif; ?>
<?php if ($invalid): ?>
            <small class="field__error"><?= e($errors[$key]) ?></small>
<?php elseif ($help): ?>
            <small id="<?= e($id) ?>-h" class="field__help"><?= e($help) ?></small>
<?php endif; ?>
          </div>
<?php endforeach; ?>
        </div>
      </section>
<?php endforeach; ?>
      <div class="settings__actions">
        <button type="submit" class="btn btn--primary">Save settings</button>
        <a class="btn" href="../contact" target="_blank" rel="noopener">Preview on site ↗</a>
      </div>
    </form>
<?php
admin_footer();
