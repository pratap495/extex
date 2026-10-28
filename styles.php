<?php
declare(strict_types=1);

require_once __DIR__ . '/php-backend/private/config.php';

$css = file_get_contents(__DIR__ . '/styles.css');
if ($css === false) {
    http_response_code(503);
    exit;
}
$css = preg_replace_callback(
    "~assets/([^)'\"\\s]+\\.(?:webp|png|jpe?g|gif))~i",
    static fn(array $match): string => app_url('/media.php?key=' . rawurlencode($match[1])),
    $css
) ?? $css;
header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo $css;
