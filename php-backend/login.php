<?php
declare(strict_types=1);

require_once __DIR__ . '/private/auth.php';
require_once __DIR__ . '/private/database.php';

start_secure_session();
if (isset($_SESSION['user_id'], $_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: ' . app_url('/admin'), true, 303);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Reload the page and try again.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        try {
            $statement = database()->prepare(
                'SELECT id, username, password_hash, role FROM users WHERE username = :username AND is_active = 1 LIMIT 1'
            );
            $statement->execute(['username' => $username]);
            $user = $statement->fetch();
            if ($user && $user['role'] === 'admin' && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = 'admin';
                unset($_SESSION['csrf_token']);
                header('Location: ' . app_url('/admin'), true, 303);
                exit;
            }
            $error = 'The username or password is incorrect.';
        } catch (Throwable $exception) {
            error_log('Admin login failed: ' . $exception->getMessage());
            http_response_code(503);
            $error = 'Sign in is temporarily unavailable.';
        }
    }
}

$token = csrf_token();
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login | Extek Profiles</title>
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="stylesheet" href="<?= h(app_url('/fonts.css')) ?>"><link rel="stylesheet" href="<?= h(app_url('/styles.css')) ?>">
</head>
<body class="login-route">
  <main id="main" class="login-route-main">
    <a class="login-route-logo" href="<?= h(app_url('/')) ?>" aria-label="Extek Profiles home"><img src="<?= h(app_url('/assets/logo.webp')) ?>" alt="Extek Profiles" width="215"></a>
    <section class="login-route-card" aria-labelledby="login-title">
      <p class="eyebrow"><span></span> EXTEK PROFILES</p><h1 id="login-title">Admin sign in</h1>
      <?php if ($error !== ''): ?><p class="login-route-status" role="alert"><?= h($error) ?></p><?php endif; ?>
      <form method="post" action="<?= h(app_url('/login')) ?>" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
        <label for="login-username">Username</label><input id="login-username" name="username" autocomplete="username" required maxlength="100">
        <label for="login-password">Password</label><input id="login-password" name="password" type="password" autocomplete="current-password" required>
        <button class="button" type="submit">Sign in</button>
      </form>
    </section>
  </main>
</body>
</html>

