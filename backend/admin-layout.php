<?php
/**
 * Shared header/footer markup for admin pages.
 */
declare(strict_types=1);

require_once __DIR__ . '/settings.php';

/**
 * "Send payment details" buttons for one customer: WhatsApp (if a number),
 * email and copy. Points to Settings when no bank account is saved yet.
 */
function payment_buttons(string $ref, string $waDigits, string $email, string $firstName, bool $compact = false): void
{
    $text = payment_message($ref);
    $btn = $compact ? 'btn btn--sm' : 'btn btn--block';
    if ($text === '') {
        echo '<p class="hint">Add your bank details in <a href="settings#g-payment">Settings → Payment</a> to send them from here.</p>';
        return;
    }
    $greeting = "Hello $firstName,\n\n";
    if ($waDigits !== '') {
        echo '<a class="' . $btn . '" href="https://wa.me/' . e($waDigits) . '?text=' . e(rawurlencode($greeting . $text)) . '" target="_blank" rel="noopener">Send on WhatsApp</a>';
    }
    echo '<a class="' . $btn . '" href="mailto:' . e($email) . '?subject=' . e(rawurlencode("Payment details · $ref")) . '&amp;body=' . e(rawurlencode($greeting . $text)) . '">Send by email</a>';
    echo '<button type="button" class="' . $btn . '" data-copy="' . e($text) . '">Copy details</button>';
}

function admin_header(string $title, ?array $admin = null, string $active = ''): void
{
    $flash = $admin ? flash() : null;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($title) ?> · Reign Fashion Admin</title>
  <link rel="icon" href="../assets/favicon-48.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500&family=Manrope:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="admin.css">
</head>
<body class="<?= $admin ? 'is-auth' : 'is-guest' ?>">
<?php if ($admin): ?>
  <header class="topbar">
    <a href="./" class="topbar__brand">
      <img src="../assets/images/brand/logo-mark-mask.png" alt="" width="40" height="29">
      <span>Reign Fashion <em>Admin</em></span>
    </a>
    <button type="button" class="topbar__toggle" aria-expanded="false" aria-controls="admin-nav">
      <span class="topbar__toggle-bars" aria-hidden="true"></span>
      <span class="topbar__toggle-label">Menu</span>
    </button>
    <nav class="topbar__nav" id="admin-nav" aria-label="Admin">
      <a href="./" <?= $active === 'dashboard' ? 'aria-current="page"' : '' ?>>Dashboard</a>
      <a href="enquiries" <?= $active === 'inbox' ? 'aria-current="page"' : '' ?>>Enquiries</a>
      <a href="academy" <?= $active === 'academy' ? 'aria-current="page"' : '' ?>>Academy</a>
      <a href="gallery" <?= $active === 'gallery' ? 'aria-current="page"' : '' ?>>Gallery</a>
      <a href="testimonials" <?= $active === 'testimonials' ? 'aria-current="page"' : '' ?>>Testimonials</a>
      <a href="videos" <?= $active === 'videos' ? 'aria-current="page"' : '' ?>>Videos</a>
      <a href="settings" <?= $active === 'settings' ? 'aria-current="page"' : '' ?>>Settings</a>
      <a href="password" <?= $active === 'account' ? 'aria-current="page"' : '' ?>>Account</a>
      <a href="../" target="_blank" rel="noopener">View site ↗</a>
      <form method="post" action="logout" class="topbar__logout">
        <?= csrf_field() ?>
        <button type="submit">Log out</button>
      </form>
    </nav>
  </header>
<?php endif; ?>
  <main class="page">
<?php if ($flash): ?>
    <p class="flash flash--<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p>
<?php endif;
}

function admin_footer(): void
{
    ?>
  </main>
  <script src="admin.js" defer></script>
</body>
</html>
<?php
}
