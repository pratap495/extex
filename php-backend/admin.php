<?php
declare(strict_types=1);

require_once __DIR__ . '/private/auth.php';
require_admin();
?><!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Admin | Extek Profiles</title><link rel="stylesheet" href="<?= h(app_url('/fonts.css')) ?>"><link rel="stylesheet" href="<?= h(app_url('/styles.css')) ?>"></head>
<body class="login-route"><main class="login-route-main"><section class="login-route-card"><p class="eyebrow"><span></span> ADMIN AREA</p><h1>Welcome, <?= h((string)$_SESSION['username']) ?></h1><p>Your admin account is signed in.</p><form method="post" action="<?= h(app_url('/logout')) ?>"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button class="button" type="submit">Sign out</button></form></section></main></body>
</html>

