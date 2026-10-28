<?php
declare(strict_types=1);

require_once __DIR__ . '/php-backend/private/database.php';
require_once __DIR__ . '/php-backend/private/cms.php';

$slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string)($_GET['slug'] ?? '')));
if ($slug === '' || $slug === 'home') {
    http_response_code(404);
    exit('Page not found.');
}

try {
    $pdo = database();
    $page = cms_page($pdo, $slug);
    if (!$page) {
        http_response_code(404);
        exit('Page not found.');
    }
    if (!(int)($page['is_active'] ?? 1)) {
        http_response_code(404);
        exit('Page not found.');
    }
    header('Content-Type: text/html; charset=utf-8');
    echo cms_render_html((string)$page['document_html'], $page, cms_pages($pdo), cms_site_settings($pdo), cms_social_links($pdo));
} catch (Throwable $exception) {
    error_log('CMS page failed: ' . $exception->getMessage());
    http_response_code(503);
    echo 'This page is temporarily unavailable.';
}
