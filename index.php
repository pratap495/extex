<?php
declare(strict_types=1);

require_once __DIR__ . '/php-backend/private/database.php';
require_once __DIR__ . '/php-backend/private/cms.php';

try {
    $pdo = database();
    $page = cms_homepage($pdo);
    if (!$page) {
        http_response_code(503);
        exit('Homepage content is not installed yet. Run php-backend/cms-install.php from the XAMPP shell.');
    }
    if (!(int)($page['is_active'] ?? 1)) {
        http_response_code(404);
        exit('Page not found.');
    }
    header('Content-Type: text/html; charset=utf-8');
    echo cms_render_html((string)$page['document_html'], $page, cms_pages($pdo), cms_site_settings($pdo), cms_social_links($pdo));
} catch (Throwable $exception) {
    error_log('CMS homepage failed: ' . $exception->getMessage());
    http_response_code(503);
    echo 'The homepage is temporarily unavailable.';
}
