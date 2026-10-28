<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/private/database.php';

$username = getenv('ADMIN_USERNAME') ?: 'admin';
$password = getenv('ADMIN_PASSWORD');
if ($password === false || $password === '') {
    fwrite(STDERR, "Set ADMIN_PASSWORD in the environment before provisioning the admin.\n");
    exit(1);
}

$pdo = database();
$hash = password_hash($password, PASSWORD_DEFAULT);
$query = $pdo->prepare(
    'INSERT INTO users (username, password_hash, role, is_active) VALUES (:username, :password_hash, \'admin\', 1) '
    . 'ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = \'admin\', is_active = 1'
);
$query->execute(['username' => $username, 'password_hash' => $hash]);
fwrite(STDOUT, "Admin account provisioned for username: {$username}\n");
