<?php
/**
 * Shared header/footer markup for admin pages.
 */
declare(strict_types=1);

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
      <a href="./" <?= $active === 'inbox' ? 'aria-current="page"' : '' ?>>Enquiries</a>
      <a href="academy" <?= $active === 'academy' ? 'aria-current="page"' : '' ?>>Academy</a>
      <a href="gallery" <?= $active === 'gallery' ? 'aria-current="page"' : '' ?>>Gallery</a>
      <a href="testimonials" <?= $active === 'testimonials' ? 'aria-current="page"' : '' ?>>Testimonials</a>
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
