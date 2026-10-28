<?php
declare(strict_types=1);

require_once __DIR__ . '/php-backend/private/database.php';
require_once __DIR__ . '/php-backend/private/cms.php';

try {
    $settings = cms_site_settings(database());
    $baseUrl = rtrim((string)($settings['site_base_url'] ?? ''), '/');
    $content = trim((string)($settings['robots_txt_content'] ?? 'User-agent: *' . "\n" . 'Allow: /'));
    $content = preg_replace('/^\s*Sitemap\s*:\s*.*(?:\r?\n|$)/mi', '', $content) ?? $content;
    if ($baseUrl !== '') $content = rtrim($content) . "\n\nSitemap: {$baseUrl}/sitemap.xml";
    header('Content-Type: text/plain; charset=utf-8');
    echo rtrim($content) . "\n";
} catch (Throwable $exception) {
    error_log('robots.txt generation failed: ' . $exception->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nDisallow: /";
}
