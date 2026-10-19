<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/notify.php';
require __DIR__ . '/../backend/admin-layout.php';

$admin  = require_admin();
$values = get_settings();
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();
    if (($_POST['action'] ?? '') === 'test_alert') {
        $problem = alert_problem();
        if ($problem !== '') {
            flash($problem, 'error');
        } elseif (send_admin_alert('Test alert from the Reign Fashion admin',
            "This is a test. Alerts for new enquiries and Academy registrations will arrive at this address.\n\n" . admin_link(''))) {
            flash('Test email sent to ' . alert_recipient() . '. If it is not in the inbox within a few minutes, check the spam folder.');
        } else {
            flash('The server could not send the email. Ask the hosting company to check that PHP mail is enabled.', 'error');
        }
        redirect('settings#g-alerts');
    }

    [$clean, $errors] = validate_settings($_POST);
    if (!$errors) {
        save_settings($clean);
        flash('Settings saved. The website shows the new details within a minute.');
        redirect('settings');
    }
    $values = $clean;   // keep what was typed so nothing is lost
}

$alertProblem = alert_problem($values);

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
<?php foreach (array_unique($errors) as $err): ?>
      <p><?= e($err) ?></p>
<?php endforeach; ?>
    </div>
<?php endif; ?>

    <form method="post" class="settings" id="settings-form" novalidate>
      <?= csrf_field() ?>
<?php foreach (SETTING_GROUPS as $group => $groupLabel): ?>
      <section class="card" aria-labelledby="g-<?= e($group) ?>-title" id="g-<?= e($group) ?>">
        <h2 id="g-<?= e($group) ?>-title" class="card__title"><?= e($groupLabel) ?></h2>
<?php if ($group === 'alerts'): ?>
        <p class="hint">
          <?= $alertProblem === '' ? 'Alerts are on. Each email links straight to the enquiry or registration.' : e($alertProblem) ?>
        </p>
<?php endif; ?>
        <div class="form form--grid">
<?php foreach (SETTING_DEFS as $key => [$g, $label, $type, $default, $max, $help]):
        if ($g !== $group) continue;
        $id = 's-' . $key;
        $invalid = isset($errors[$key]);
        $kind = strtok($type, ':');
        $inputType = match ($kind) { 'email' => 'email', 'url' => 'url', 'phone' => 'tel', default => 'text' };
        $describedBy = $help ? ' aria-describedby="' . e($id) . '-h"' : '';
        $full = in_array($kind, ['textarea', 'choices', 'select'], true);
?>
          <div class="field<?= $full ? ' field--full' : '' ?><?= $invalid ? ' is-invalid' : '' ?>">
<?php if ($kind === 'choices'):
          $picked = explode(',', $values[$key]); ?>
            <fieldset class="choices"<?= $describedBy ?>>
              <legend><?= e($label) ?></legend>
<?php foreach (setting_options($type) as $optKey => $optLabel): ?>
              <label class="check check--box"><input type="checkbox" name="<?= e($key) ?>[]" value="<?= e($optKey) ?>"<?= in_array($optKey, $picked, true) ? ' checked' : '' ?>> <span><?= e($optLabel) ?></span></label>
<?php endforeach; ?>
            </fieldset>
<?php elseif ($kind === 'select'): ?>
            <label for="<?= e($id) ?>"><?= e($label) ?></label>
            <select id="<?= e($id) ?>" name="<?= e($key) ?>"<?= $describedBy ?>>
<?php foreach (setting_options($type) as $optKey => $optLabel): ?>
              <option value="<?= e($optKey) ?>"<?= $values[$key] === $optKey ? ' selected' : '' ?>><?= e($optLabel) ?></option>
<?php endforeach; ?>
            </select>
<?php elseif ($kind === 'textarea'): ?>
            <label for="<?= e($id) ?>"><?= e($label) ?></label>
            <textarea id="<?= e($id) ?>" name="<?= e($key) ?>" rows="2" maxlength="<?= (int) $max ?>"<?= $describedBy ?>><?= e($values[$key]) ?></textarea>
<?php else: ?>
            <label for="<?= e($id) ?>"><?= e($label) ?></label>
            <input id="<?= e($id) ?>" type="<?= $inputType ?>" name="<?= e($key) ?>" value="<?= e($values[$key]) ?>" maxlength="<?= $kind === 'account' ? 13 : (int) $max ?>"
                   <?= $inputType === 'email' ? 'autocomplete="email"' : '' ?><?= $kind === 'account' ? 'inputmode="numeric" autocomplete="off"' : '' ?><?= $invalid ? ' aria-invalid="true"' : '' ?><?= $describedBy ?>>
<?php endif; ?>
<?php if ($invalid): ?>
            <small class="field__error"><?= e($errors[$key]) ?></small>
<?php elseif ($help): ?>
            <small id="<?= e($id) ?>-h" class="field__help"><?= e($help) ?></small>
<?php endif; ?>
          </div>
<?php endforeach; ?>
        </div>
<?php if ($group === 'alerts'): ?>
        <p class="settings__test">
          <button type="submit" form="test-alert" class="btn btn--sm">Send a test email</button>
          <small class="field__help">Uses the saved settings, so save any changes first.</small>
        </p>
<?php endif; ?>
      </section>
<?php endforeach; ?>
      <div class="settings__actions">
        <button type="submit" class="btn btn--primary">Save settings</button>
        <a class="btn" href="../contact" target="_blank" rel="noopener">Preview on site ↗</a>
      </div>
    </form>
    <form method="post" id="test-alert" hidden>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="test_alert">
    </form>
<?php
admin_footer();
