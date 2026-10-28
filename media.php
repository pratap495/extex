<?php
declare(strict_types=1);

require_once __DIR__ . '/php-backend/private/database.php';

$key = (string)($_GET['key'] ?? '');
if (!preg_match('/^[A-Za-z0-9._-]{1,190}$/', $key)) {
    http_response_code(404);
    exit;
}

try {
    $query = database()->prepare('SELECT mime_type, data FROM cms_media WHERE media_key = :media_key LIMIT 1');
    $query->execute(['media_key' => $key]);
    $media = $query->fetch();
    if (!$media || !str_starts_with((string)$media['mime_type'], 'image/')) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $media['mime_type']);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=3600');
    echo $media['data'];
} catch (Throwable $exception) {
    error_log('CMS media request failed: ' . $exception->getMessage());
    http_response_code(503);
}
