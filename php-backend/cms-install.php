<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/private/database.php';
$pdo = database();
$pdo->exec("CREATE TABLE IF NOT EXISTS cms_pages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    page_name VARCHAR(200) NOT NULL DEFAULT '',
    banner_title VARCHAR(300) NOT NULL DEFAULT '',
    banner_image_key VARCHAR(190) NOT NULL DEFAULT '',
    banner_image_alt VARCHAR(255) NOT NULL DEFAULT '',
    meta_title VARCHAR(300) NOT NULL DEFAULT '',
    meta_description VARCHAR(500) NOT NULL DEFAULT '',
    meta_keywords VARCHAR(4000) NOT NULL DEFAULT '',
    meta_tags VARCHAR(4000) NOT NULL DEFAULT '',
    canonical_url VARCHAR(1000) NOT NULL DEFAULT '',
    banner_heading_tag VARCHAR(3) NOT NULL DEFAULT 'H1',
    service_list_heading_tag VARCHAR(3) NOT NULL DEFAULT 'H2',
    sort_order INT NOT NULL DEFAULT 0,
    template_name VARCHAR(100) NOT NULL DEFAULT 'Default',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    show_in_nav TINYINT(1) NOT NULL DEFAULT 1,
    backlinks VARCHAR(4000) NOT NULL DEFAULT '',
    document_html MEDIUMTEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Add marketer settings to existing installations without overwriting page content.
$pageColumns = [
    'page_name' => "VARCHAR(200) NOT NULL DEFAULT ''",
    'banner_title' => "VARCHAR(300) NOT NULL DEFAULT ''",
    'banner_image_key' => "VARCHAR(190) NOT NULL DEFAULT ''",
    'banner_image_alt' => "VARCHAR(255) NOT NULL DEFAULT ''",
    'meta_title' => "VARCHAR(300) NOT NULL DEFAULT ''",
    'meta_description' => "VARCHAR(500) NOT NULL DEFAULT ''",
    'meta_keywords' => "VARCHAR(4000) NOT NULL DEFAULT ''",
    'meta_tags' => "VARCHAR(4000) NOT NULL DEFAULT ''",
    'canonical_url' => "VARCHAR(1000) NOT NULL DEFAULT ''",
    'banner_heading_tag' => "VARCHAR(3) NOT NULL DEFAULT 'H1'",
    'service_list_heading_tag' => "VARCHAR(3) NOT NULL DEFAULT 'H2'",
    'sort_order' => 'INT NOT NULL DEFAULT 0',
    'template_name' => "VARCHAR(100) NOT NULL DEFAULT 'Default'",
    'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1',
    'show_in_nav' => 'TINYINT(1) NOT NULL DEFAULT 1',
    'backlinks' => "VARCHAR(4000) NOT NULL DEFAULT ''",
];
$existingPageColumns = array_column($pdo->query('SHOW COLUMNS FROM cms_pages')->fetchAll(), 'Field');
foreach ($pageColumns as $column => $definition) {
    if (!in_array($column, $existingPageColumns, true)) {
        $pdo->exec("ALTER TABLE cms_pages ADD COLUMN `{$column}` {$definition}");
    }
}
$pdo->exec("CREATE TABLE IF NOT EXISTS cms_media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    media_key VARCHAR(190) NOT NULL UNIQUE,
    file_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    alt_text VARCHAR(255) NOT NULL DEFAULT '',
    data LONGBLOB NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("CREATE TABLE IF NOT EXISTS cms_site_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value LONGTEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("CREATE TABLE IF NOT EXISTS cms_social_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    icon_class VARCHAR(160) NOT NULL,
    url VARCHAR(1000) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    open_new_tab TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$defaultSiteSettings = [
    'site_base_url' => 'https://extekprofiles.com',
    'order_button_label' => 'Get a Quote',
    'order_button_url' => 'contact.html',
    'contact_phone' => '+91 96769 10662',
    'contact_email' => 'info@extekprofiles.com',
    'contact_address' => 'Survey No. 21, Mahadevpur Village, Bibinagar - 508126, Yadadri Bhuvanagiri District, Telangana, India.',
    'contact_map_embed_url' => 'https://www.google.com/maps?output=embed&q=Survey+No.+21,+Mahadevpur+Village,+Bibinagar,+Telangana+508126,+India',
    'owner_whatsapp_number' => '',
    'robots_txt_content' => "User-agent: *\nAllow: /",
];
$settingInsert = $pdo->prepare('INSERT IGNORE INTO cms_site_settings (setting_key, setting_value) VALUES (:setting_key, :setting_value)');
foreach ($defaultSiteSettings as $key => $value) $settingInsert->execute(['setting_key' => $key, 'setting_value' => $value]);
$root = dirname(__DIR__);
$documents = [];
$imageNames = [];
$imageAltTexts = [];
$excluded = ['login.html', 'reference-client-login.html'];
foreach (glob($root . '/*.html') ?: [] as $path) {
    $fileName = basename($path);
    if (in_array($fileName, $excluded, true)) continue;
    $html = file_get_contents($path);
    if ($html === false) continue;
    $slug = $fileName === 'index.html' ? 'home' : pathinfo($fileName, PATHINFO_FILENAME);
    $documents[$slug] = $html;
    preg_match_all("~assets/([^\"'?#]+\\.(?:webp|png|jpe?g|gif))~i", $html, $matches);
    $imageNames = array_merge($imageNames, $matches[1]);
    $previousLibxmlSetting = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    foreach ($dom->getElementsByTagName('img') as $image) {
        $src = (string)$image->getAttribute('src');
        if (str_starts_with($src, 'assets/')) {
            $key = substr($src, strlen('assets/'));
            $alt = trim((string)$image->getAttribute('alt'));
            if ($alt !== '' && !isset($imageAltTexts[$key])) $imageAltTexts[$key] = $alt;
        }
    }
    libxml_clear_errors();
    libxml_use_internal_errors($previousLibxmlSetting);
}
$stylesheet = file_get_contents($root . '/styles.css') ?: '';
preg_match_all("~assets/([^)'\"\\s]+\\.(?:webp|png|jpe?g|gif))~i", $stylesheet, $cssMatches);
$imageNames = array_merge($imageNames, $cssMatches[1]);

$mediaInsert = $pdo->prepare(
    'INSERT INTO cms_media (media_key, file_name, mime_type, alt_text, data) VALUES (:media_key, :file_name, :mime_type, :alt_text, :data) '
    . 'ON DUPLICATE KEY UPDATE file_name = VALUES(file_name), mime_type = VALUES(mime_type), alt_text = VALUES(alt_text), data = VALUES(data)'
);
$imported = 0;
foreach (array_unique($imageNames) as $fileName) {
    $path = $root . '/assets/' . $fileName;
    if (!is_file($path)) {
        fwrite(STDERR, "Skipping missing image: {$fileName}\n");
        continue;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!is_string($mime) || !in_array($mime, ['image/webp', 'image/png', 'image/jpeg', 'image/gif'], true)) continue;
    $data = file_get_contents($path);
    if ($data === false) continue;
    $mediaInsert->bindValue(':media_key', $fileName);
    $mediaInsert->bindValue(':file_name', $fileName);
    $mediaInsert->bindValue(':mime_type', $mime);
    $mediaInsert->bindValue(':alt_text', $imageAltTexts[$fileName] ?? '');
    $mediaInsert->bindValue(':data', $data, PDO::PARAM_LOB);
    $mediaInsert->execute();
    $imported++;
}

$insertPage = $pdo->prepare('INSERT IGNORE INTO cms_pages (slug, document_html) VALUES (:slug, :document_html)');
$newPages = 0;
foreach ($documents as $slug => $html) {
    foreach (array_unique($imageNames) as $fileName) {
        $html = str_replace('assets/' . $fileName, 'media.php?key=' . $fileName, $html);
    }
    $insertPage->execute(['slug' => $slug, 'document_html' => $html]);
    $newPages += $insertPage->rowCount();
}
$pdo->exec("UPDATE cms_pages SET document_html = REPLACE(document_html, 'href=\"styles.css\"', 'href=\"styles.php\"')");
echo "CMS ready: {$newPages} new pages imported; existing edits preserved; {$imported} referenced images stored/refreshed.\n";
