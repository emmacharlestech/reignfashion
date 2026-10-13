<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';

admin_headers();
require_csrf();          // logout only via the POST button, never a plain link
logout_admin();
redirect('login.php');
