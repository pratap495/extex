<?php
declare(strict_types=1);

require_once __DIR__ . '/php-backend/private/database.php';
require_once __DIR__ . '/php-backend/private/cms.php';

try {
    $pdo = database();
    $settings = cms_site_settings($pdo);
    $baseUrl = rtrim((string)($settings['site_base_url'] ?? ''), '/');
    $pages = $pdo->query('SELECT slug, updated_at FROM cms_pages WHERE is_active = 1 ORDER BY sort_order, slug')->fetchAll();
    header('Content-Type: application/xml; charset=utf-8');
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    foreach ($pages as $page) {
        $path = $page['slug'] === 'home' ? '/' : '/' . rawurlencode((string)$page['slug']) . '.html';
        $location = htmlspecialchars($baseUrl . $path, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $lastModified = date('Y-m-d', strtotime((string)$page['updated_at']));
        echo "  <url><loc>{$location}</loc><lastmod>{$lastModified}</lastmod></url>\n";
    }
    echo "</urlset>\n";
} catch (Throwable $exception) {
    error_log('Sitemap generation failed: ' . $exception->getMessage());
    http_response_code(503);
    header('Content-Type: application/xml; charset=utf-8');
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?><urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\"></urlset>";
}
