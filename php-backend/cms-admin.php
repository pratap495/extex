<?php
declare(strict_types=1);

require_once __DIR__ . '/private/auth.php';
require_once __DIR__ . '/private/database.php';
require_once __DIR__ . '/private/cms.php';
require_admin();

$pdo = database();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Reload and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_homepage') {
            $html = (string)($_POST['document_html'] ?? '');
            $slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string)($_POST['slug'] ?? 'home')));
            if (!$slug || !cms_page($pdo, $slug)) {
                $error = 'Select a page that exists in the CMS.';
            } elseif ($html === '' || strlen($html) > 3_000_000) {
                $error = 'Page HTML must be present and smaller than 3 MB.';
            } else {
                $headingTag = strtoupper((string)($_POST['banner_heading_tag'] ?? 'H1'));
                $serviceHeadingTag = strtoupper((string)($_POST['service_list_heading_tag'] ?? 'H2'));
                $imageKey = trim((string)($_POST['banner_image_key'] ?? ''));
                $bannerUpload = $_FILES['banner_image_upload'] ?? null;
                $hasBannerUpload = $bannerUpload && $bannerUpload['error'] !== UPLOAD_ERR_NO_FILE;
                $bannerUploadMime = null;
                $uploadError = '';
                if ($hasBannerUpload) {
                    if ($bannerUpload['error'] !== UPLOAD_ERR_OK || $bannerUpload['size'] > 8 * 1024 * 1024) {
                        $uploadError = 'Choose a header image smaller than 8 MB.';
                    } else {
                        $bannerUploadMime = (new finfo(FILEINFO_MIME_TYPE))->file($bannerUpload['tmp_name']);
                        if (!in_array($bannerUploadMime, ['image/webp', 'image/png', 'image/jpeg', 'image/gif'], true)
                            || @getimagesize($bannerUpload['tmp_name']) === false) {
                            $uploadError = 'Use a valid WEBP, PNG, JPEG, or GIF header image.';
                        }
                    }
                }
                $imageExists = true;
                if ($imageKey !== '' && !$hasBannerUpload) {
                    $imageCheck = $pdo->prepare('SELECT 1 FROM cms_media WHERE media_key = :media_key');
                    $imageCheck->execute(['media_key' => $imageKey]);
                    $imageExists = (bool)$imageCheck->fetchColumn();
                }
                $canonicalUrl = trim((string)($_POST['canonical_url'] ?? ''));
                if (!in_array($headingTag, ['H1', 'H2', 'H3', 'H4', 'H5', 'H6'], true)
                    || !in_array($serviceHeadingTag, ['H1', 'H2', 'H3', 'H4', 'H5', 'H6'], true)
                    || $uploadError !== ''
                    || !$imageExists
                    || ($canonicalUrl !== '' && (!filter_var($canonicalUrl, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($canonicalUrl, PHP_URL_SCHEME)), ['http', 'https'], true)))) {
                    $error = $uploadError !== '' ? $uploadError : 'Check the heading levels, selected image, and canonical URL.';
                } else {
                    if ($hasBannerUpload) {
                        $original = basename((string)$bannerUpload['name']);
                        $safeBase = strtolower((string)preg_replace('/[^a-zA-Z0-9]+/', '-', pathinfo($original, PATHINFO_FILENAME)));
                        $extensions = ['image/webp' => 'webp', 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif'];
                        $imageKey = trim($safeBase, '-') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$bannerUploadMime];
                        $insertImage = $pdo->prepare('INSERT INTO cms_media (media_key, file_name, mime_type, alt_text, data) VALUES (:media_key, :file_name, :mime_type, :alt_text, :data)');
                        $insertImage->bindValue(':media_key', $imageKey);
                        $insertImage->bindValue(':file_name', $original);
                        $insertImage->bindValue(':mime_type', $bannerUploadMime);
                        $insertImage->bindValue(':alt_text', mb_substr(trim((string)($_POST['banner_image_alt'] ?? '')), 0, 255));
                        $insertImage->bindValue(':data', file_get_contents($bannerUpload['tmp_name']), PDO::PARAM_LOB);
                        $insertImage->execute();
                    }
                    $backlinks = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)($_POST['backlinks'] ?? '')) ?: []));
                    $fields = [
                        'page_name' => mb_substr(trim((string)($_POST['page_name'] ?? '')), 0, 200),
                        'banner_title' => mb_substr(trim((string)($_POST['banner_title'] ?? '')), 0, 300),
                        'banner_image_key' => $imageKey,
                        'banner_image_alt' => mb_substr(trim((string)($_POST['banner_image_alt'] ?? '')), 0, 255),
                        'meta_title' => mb_substr(trim((string)($_POST['meta_title'] ?? '')), 0, 300),
                        'meta_description' => mb_substr(trim((string)($_POST['meta_description'] ?? '')), 0, 500),
                        'meta_keywords' => mb_substr(trim((string)($_POST['meta_keywords'] ?? '')), 0, 4000),
                        'meta_tags' => mb_substr(trim((string)($_POST['meta_tags'] ?? '')), 0, 4000),
                        'canonical_url' => $canonicalUrl,
                        'banner_heading_tag' => $headingTag,
                        'service_list_heading_tag' => $serviceHeadingTag,
                        'sort_order' => max(-100000, min(100000, (int)($_POST['sort_order'] ?? 0))),
                        'template_name' => mb_substr(trim((string)($_POST['template_name'] ?? 'Default')), 0, 100),
                        'is_active' => isset($_POST['is_active']) ? 1 : 0,
                        'show_in_nav' => isset($_POST['show_in_nav']) ? 1 : 0,
                        'backlinks' => mb_substr(implode("\n", $backlinks), 0, 4000),
                    ];
                    $update = $pdo->prepare('UPDATE cms_pages SET document_html = :html, page_name = :page_name, banner_title = :banner_title, banner_image_key = :banner_image_key, banner_image_alt = :banner_image_alt, meta_title = :meta_title, meta_description = :meta_description, meta_keywords = :meta_keywords, meta_tags = :meta_tags, canonical_url = :canonical_url, banner_heading_tag = :banner_heading_tag, service_list_heading_tag = :service_list_heading_tag, sort_order = :sort_order, template_name = :template_name, is_active = :is_active, show_in_nav = :show_in_nav, backlinks = :backlinks WHERE slug = :slug');
                    try {
                        $update->execute($fields + ['html' => $html, 'slug' => $slug]);
                        if ($update->rowCount() === 0 && !cms_page($pdo, $slug)) {
                            throw new RuntimeException('The page could not be found while saving.');
                        }
                        header('Location: ' . app_url('/admin?page=' . rawurlencode($slug) . '&saved=1'), true, 303);
                        exit;
                    } catch (Throwable $exception) {
                        error_log('CMS page save failed for slug ' . $slug . ': ' . $exception->getMessage());
                        $error = 'The page could not be saved. Check the database connection and server logs, then try again.';
                    }
                }
            }
        } elseif ($action === 'upload_image') {
            $file = $_FILES['image'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 8 * 1024 * 1024) {
                $error = 'Choose an image smaller than 8 MB.';
            } else {
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                $allowed = ['image/webp', 'image/png', 'image/jpeg', 'image/gif'];
                $dimensions = @getimagesize($file['tmp_name']);
                if (!in_array($mime, $allowed, true) || $dimensions === false) {
                    $error = 'Use a valid WEBP, PNG, JPEG, or GIF image.';
                } else {
                    $original = basename((string)$file['name']);
                    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
                    $safeBase = strtolower((string)preg_replace('/[^a-zA-Z0-9]+/', '-', pathinfo($original, PATHINFO_FILENAME)));
                    $key = trim($safeBase, '-') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
                    $insert = $pdo->prepare('INSERT INTO cms_media (media_key, file_name, mime_type, alt_text, data) VALUES (:media_key, :file_name, :mime_type, :alt_text, :data)');
                    $insert->bindValue(':media_key', $key);
                    $insert->bindValue(':file_name', $original);
                    $insert->bindValue(':mime_type', $mime);
                    $insert->bindValue(':alt_text', mb_substr(trim((string)($_POST['alt_text'] ?? '')), 0, 255));
                    $insert->bindValue(':data', file_get_contents($file['tmp_name']), PDO::PARAM_LOB);
                    $insert->execute();
                    $returnPage = preg_replace('/[^a-z0-9-]/', '', strtolower((string)($_POST['page'] ?? 'home'))) ?: 'home';
                    header('Location: ' . app_url('/admin?page=' . rawurlencode($returnPage) . '&uploaded=1'), true, 303);
                    exit;
                }
            }
        }
    }
}

$pages = cms_pages($pdo);
$pageSlug = preg_replace('/[^a-z0-9-]/', '', strtolower((string)($_GET['page'] ?? 'home')));
$page = cms_page($pdo, $pageSlug ?: 'home');
if (!$page) {
    http_response_code(503);
    exit('CMS has not been initialized. Run php-backend/cms-install.php from the XAMPP shell.');
}
$media = $pdo->query('SELECT media_key, file_name, mime_type, alt_text FROM cms_media ORDER BY uploaded_at DESC, id DESC')->fetchAll();
$siteSettings = cms_site_settings($pdo);
$socialLinks = cms_social_links($pdo);
$previewSettings = $page;
$previewSettings['is_active'] = 1;
$preview = cms_render_html((string)$page['document_html'], $previewSettings, $pages, $siteSettings, $socialLinks);
$token = csrf_token();
$message = isset($_GET['saved']) ? 'Page saved.' : (isset($_GET['uploaded']) ? 'Image stored in the database.' : '');
$metaDoc = new DOMDocument();
$previousLibxmlSetting = libxml_use_internal_errors(true);
@$metaDoc->loadHTML((string)$page['document_html']);
$pageTitle = trim((string)($page['meta_title'] ?: ($metaDoc->getElementsByTagName('title')->item(0)?->textContent ?? ucwords(str_replace('-', ' ', $page['slug'])))));
$pageDescription = '';
foreach ($metaDoc->getElementsByTagName('meta') as $metaTag) {
    if (strtolower($metaTag->getAttribute('name')) === 'description') {
        $pageDescription = $metaTag->getAttribute('content');
        break;
    }
}
$metaKeywords = '';
$metaTags = '';
foreach ($metaDoc->getElementsByTagName('meta') as $metaTag) {
    $metaName = strtolower($metaTag->getAttribute('name'));
    if ($metaName === 'keywords') $metaKeywords = $metaTag->getAttribute('content');
    if ($metaName === 'tags') $metaTags = $metaTag->getAttribute('content');
}
$firstMainHeading = $metaDoc->getElementsByTagName('main')->item(0);
$bannerTitle = trim((string)($page['banner_title'] ?? ''));
if ($bannerTitle === '' && $firstMainHeading) {
    foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $headingName) {
        $heading = $firstMainHeading->getElementsByTagName($headingName)->item(0);
        if ($heading) { $bannerTitle = trim($heading->textContent); break; }
    }
}
$bannerImageKey = trim((string)($page['banner_image_key'] ?? ''));
$bannerImageAlt = (string)($page['banner_image_alt'] ?? '');
if ($bannerImageKey === '' && $firstMainHeading) {
    $firstImage = $firstMainHeading->getElementsByTagName('img')->item(0);
    if ($firstImage) {
        $imageSource = (string)$firstImage->getAttribute('src');
        if (preg_match('~media\.php\?key=([A-Za-z0-9._-]+)~', $imageSource, $imageMatch)) $bannerImageKey = $imageMatch[1];
        $bannerImageAlt = (string)$firstImage->getAttribute('alt');
    }
}
$canonicalUrl = (string)($page['canonical_url'] ?? '');
libxml_clear_errors();
libxml_use_internal_errors($previousLibxmlSetting);
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Site CMS | Extek Profiles</title>
  <link rel="stylesheet" href="<?= h(app_url('/fonts.css')) ?>"><link rel="stylesheet" href="<?= h(app_url('/styles.css')) ?>">
  <style>
    .cms-image-editor{display:flex;align-items:end;gap:12px;flex-wrap:wrap;margin:10px 0 16px}.cms-image-editor[hidden]{display:none!important}.cms-image-editor label{display:grid;gap:6px;font-weight:600}.cms-image-editor input{padding:10px;border:1px solid #d9e1d4;border-radius:7px;min-width:min(420px,80vw)}.cms-settings{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.cms-settings h3{grid-column:1/-1;margin:12px 0 0}.cms-settings label{display:grid;gap:6px;font-weight:600}.cms-settings input,.cms-settings select,.cms-settings textarea{width:100%;padding:10px;border:1px solid #d9e1d4;border-radius:7px;font:inherit}.cms-settings textarea{min-height:100px;resize:vertical}.cms-check{display:flex!important;align-items:center;gap:9px!important}.cms-check input{width:auto}.cms-wide{grid-column:1/-1}@media(max-width:700px){.cms-settings{grid-template-columns:1fr}.cms-wide,.cms-settings h3{grid-column:auto}}
    .cms-format-toolbar{display:flex;align-items:end;gap:8px;flex-wrap:wrap;padding:12px;border:1px solid #e5e9e1;border-radius:10px;margin:12px 0}.cms-format-toolbar label{display:grid;gap:5px;font-weight:600}.cms-format-toolbar select,.cms-format-toolbar button{padding:8px 11px;border:1px solid #d9e1d4;border-radius:7px;background:#fff;cursor:pointer}.cms-format-toolbar button:hover{background:#f3f7ef}
    .cms-page{min-height:100vh;background:#f3f7ef;padding:34px 0 80px}.cms-wrap{width:min(1500px,calc(100% - 40px));margin:auto}.cms-head{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:25px}.cms-head p{margin-top:8px}.cms-actions{display:flex;gap:12px;align-items:center}.cms-card{background:#fff;border:1px solid #e5e9e1;border-radius:16px;padding:24px;margin-bottom:22px}.cms-toolbar{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:16px}.cms-editor-frame{display:block;width:100%;height:70vh;border:1px solid #d9e1d4;border-radius:10px;background:white}.cms-notice{padding:12px 16px;border-radius:8px;background:#e5f2dc;margin-bottom:18px}.cms-error{background:#fde8e7;color:#842c26}.cms-upload{display:flex;align-items:end;gap:14px;flex-wrap:wrap}.cms-upload label,.cms-page-fields label{display:grid;gap:6px;font-weight:600}.cms-upload input,.cms-page-fields input{padding:10px;border:1px solid #d9e1d4;border-radius:7px}.cms-page-fields{display:grid;grid-template-columns:1fr 2fr;gap:14px;margin-bottom:18px}.cms-page-fields[hidden]{display:none}.cms-page-fields input{width:100%}.cms-section-list{display:grid;gap:9px;margin-top:16px}.cms-section-row{display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid #e5e9e1;border-radius:10px;padding:12px 14px}.cms-section-row span{font-weight:600}.cms-section-row[aria-hidden="true"]{opacity:.55}.cms-section-row button{border:1px solid #d9e1d4;border-radius:7px;background:#fff;padding:7px 10px;cursor:pointer}.cms-section-actions{display:flex;gap:6px;flex-wrap:wrap}.cms-media-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;margin-top:20px}.cms-media-item{border:1px solid #e5e9e1;border-radius:12px;padding:12px;min-width:0}.cms-media-item img{width:100%;height:130px;object-fit:cover;border-radius:8px;background:#f3f7ef}.cms-media-item strong{display:block;margin-top:8px;overflow-wrap:anywhere}.cms-media-item small{display:block;color:#757f8b;margin:4px 0 10px;overflow-wrap:anywhere}.cms-note{margin-top:12px;font-size:14px}.cms-help{max-width:950px}.cms-help li{margin:6px 0;color:#59666e}@media(max-width:700px){.cms-head,.cms-toolbar{align-items:flex-start;flex-direction:column}.cms-editor-frame{height:65vh;min-height:520px}.cms-section-row{align-items:flex-start;flex-direction:column}.cms-card{padding:16px}.cms-wrap{width:calc(100% - 24px)}.cms-page-fields{grid-template-columns:1fr}}
  </style>
</head>
<body class="cms-page">
<main class="cms-wrap">
  <header class="cms-head"><div><p class="eyebrow"><span></span> EXTEK PROFILES CMS</p><h1>Website editor</h1><p>Update page details, search settings, banner, and visibility. Changes are saved in the website database.</p><form method="get" action="<?= h(app_url('/admin')) ?>"><label for="page-select">Choose a page</label> <select id="page-select" name="page" onchange="this.form.submit()"><?php foreach ($pages as $available): $label = $available['page_name'] ?: ucwords(str_replace('-', ' ', $available['slug'])); ?><option value="<?= h($available['slug']) ?>" <?= $available['slug'] === $page['slug'] ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></form></div><div class="cms-actions"><a class="button outline" href="<?= h(app_url('/admin/settings')) ?>">SEO &amp; Settings</a><a class="button outline" href="<?= h($page['slug'] === 'home' ? app_url('/') : app_url('/' . $page['slug'] . '.html')) ?>" target="_blank" rel="noopener">View page</a><form method="post" action="<?= h(app_url('/logout')) ?>"><input type="hidden" name="csrf_token" value="<?= h($token) ?>"><button class="button" type="submit">Sign out</button></form></div></header>
  <?php if ($message !== ''): ?><p class="cms-notice" role="status"><?= h($message) ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="cms-notice cms-error" role="alert"><?= h($error) ?></p><?php endif; ?>
  <form class="cms-card" id="homepage-form" method="post" enctype="multipart/form-data" action="<?= h(app_url('/admin')) ?>">
    <input type="hidden" name="csrf_token" value="<?= h($token) ?>"><input type="hidden" name="action" value="save_homepage"><input type="hidden" name="slug" value="<?= h($page['slug']) ?>">
    <section class="cms-settings" aria-label="Page settings">
      <h3 class="cms-wide">Page details</h3>
      <label>Page Name<input name="page_name" value="<?= h((string)($page['page_name'] ?: ucwords(str_replace('-', ' ', $page['slug'])))) ?>" maxlength="200" required></label>
      <label>Slug<input value="<?= h($page['slug']) ?>" readonly aria-describedby="slug-help"><small id="slug-help">This is the page URL and cannot be changed here.</small></label>
      <h3 class="cms-wide">Banner</h3>
      <label>Banner Title<input id="banner-title" name="banner_title" value="<?= h($bannerTitle) ?>" maxlength="300" required></label>
      <label>Banner Heading Tag<select id="banner-heading-tag" name="banner_heading_tag"><?php foreach (['H1','H2','H3','H4','H5','H6'] as $tag): ?><option value="<?= $tag ?>" <?= strtoupper((string)($page['banner_heading_tag'] ?? 'H1')) === $tag ? 'selected' : '' ?>><?= $tag ?></option><?php endforeach; ?></select></label>
      <label>Header Image<input id="banner-image-upload" type="file" name="banner_image_upload" accept="image/webp,image/png,image/jpeg,image/gif"><small>Choose a new image to upload it directly into the database. Leave empty to keep the current image.</small></label>
      <label>Choose an existing header image<select id="banner-image-key" name="banner_image_key"><option value="">Keep the current image</option><?php foreach ($media as $image): ?><option value="<?= h($image['media_key']) ?>" <?= $bannerImageKey === $image['media_key'] ? 'selected' : '' ?>><?= h($image['file_name']) ?></option><?php endforeach; ?></select></label>
      <label>Header Image Alt Text<input id="banner-image-alt" name="banner_image_alt" value="<?= h($bannerImageAlt) ?>" maxlength="255"></label>
      <h3 class="cms-wide">Search engine settings</h3>
      <label>Meta Title<input id="page-title" name="meta_title" value="<?= h($pageTitle) ?>" maxlength="300"></label>
      <label>Canonical URL<input name="canonical_url" value="<?= h($canonicalUrl) ?>" maxlength="1000" placeholder="https://example.com/page"></label>
      <label class="cms-wide">Meta Description<textarea id="page-description" name="meta_description" maxlength="500"><?= h((string)($page['meta_description'] ?: $pageDescription)) ?></textarea></label>
      <label class="cms-wide">Meta Keywords<input name="meta_keywords" value="<?= h((string)($page['meta_keywords'] ?: $metaKeywords)) ?>" maxlength="4000"></label>
      <label class="cms-wide">Meta Tags<textarea name="meta_tags" maxlength="4000" placeholder="One tag per line, for example: robots=index,follow or property:og:type=website"><?= h((string)($page['meta_tags'] ?: $metaTags)) ?></textarea><small>Use name=value, or property:name=value, one per line.</small></label>
      <h3 class="cms-wide">Page options</h3>
      <label>Service List Heading Tag<select name="service_list_heading_tag"><?php foreach (['H1','H2','H3','H4','H5','H6'] as $tag): ?><option value="<?= $tag ?>" <?= strtoupper((string)($page['service_list_heading_tag'] ?? 'H2')) === $tag ? 'selected' : '' ?>><?= $tag ?></option><?php endforeach; ?></select></label>
      <label>Sort Order<input type="number" name="sort_order" value="<?= (int)($page['sort_order'] ?? 0) ?>"></label>
      <label>Template<select name="template_name"><option value="Default">Default</option><?php if (($page['template_name'] ?? 'Default') !== 'Default'): ?><option value="<?= h($page['template_name']) ?>" selected><?= h($page['template_name']) ?></option><?php endif; ?></select></label>
      <label class="cms-check"><input type="checkbox" name="is_active" value="1" <?= (int)($page['is_active'] ?? 1) ? 'checked' : '' ?>> Active</label>
      <label class="cms-check"><input type="checkbox" name="show_in_nav" value="1" <?= (int)($page['show_in_nav'] ?? 1) ? 'checked' : '' ?>> Show in Nav</label>
      <label class="cms-wide">Backlinks (one per line)<textarea name="backlinks" maxlength="4000" placeholder="https://example.com/page"><?= h((string)($page['backlinks'] ?? '')) ?></textarea></label>
    </section>
    <div class="cms-toolbar"><div><h2><?= h(ucwords(str_replace('-', ' ', $page['slug']))) ?> page</h2><p class="cms-note">Click any text in the preview to edit it. Click an image to select it, then choose a replacement below.</p></div><strong>Visual editor</strong></div>
    <div class="cms-format-toolbar" aria-label="Text formatting tools">
      <label>Change selected text to<select id="cms-format-choice"><option value="p">Paragraph</option><option value="h2">Heading 2</option><option value="h3">Heading 3</option><option value="h4">Heading 4</option><option value="h5">Heading 5</option><option value="h6">Heading 6</option></select></label>
      <button id="cms-format-apply" type="button">Apply style</button><button id="cms-format-bold" type="button"><strong>Bold</strong></button><button id="cms-format-italic" type="button"><em>Italic</em></button><button id="cms-format-list" type="button">Bulleted list</button><button id="cms-add-link" type="button">Link selected words</button>
    </div>
    <p class="cms-note">Select words in the preview, then use these tools. Paragraph or heading styles apply to the selected text block; a link can be added to selected words inside a paragraph.</p>
    <iframe id="homepage-preview" class="cms-editor-frame" title="Editable page preview" sandbox="allow-same-origin" srcdoc="<?= h($preview) ?>"></iframe>
    <p class="cms-note" id="cms-image-status" role="status" aria-live="polite">Click text to edit it or select an image.</p>
    <div id="cms-link-editor" class="cms-page-fields" hidden><label>Link text<input id="cms-link-text" maxlength="300"></label><label>Link destination<input id="cms-link-url" maxlength="500" placeholder="Page, website, email, or phone"></label><button class="button" id="cms-link-save" type="button">Update link</button></div>
    <div id="cms-image-editor" class="cms-image-editor" hidden><label>Image description<input id="cms-image-alt" maxlength="255" placeholder="Describe this image"></label><button class="button" id="cms-image-alt-save" type="button">Update description</button></div>
    <textarea id="homepage-content" name="document_html" hidden><?= h((string)$page['document_html']) ?></textarea>
    <button class="button" type="submit">Save page to database</button>
  </form>
  <section class="cms-card" aria-labelledby="sections-title"><div class="cms-toolbar"><div><h2 id="sections-title">Page sections</h2><p class="cms-note">Show, hide, move, duplicate, or remove sections. Save the page to publish changes.</p></div><button class="button" id="cms-add-section" type="button">Add a section</button></div><div id="cms-section-list" class="cms-section-list"></div></section>
  <section class="cms-card" aria-labelledby="media-title"><h2 id="media-title">Image library <small>(<?= count($media) ?> stored in MySQL)</small></h2><p class="cms-note">Upload images here, then select a page image and choose a replacement.</p>
    <form class="cms-upload" method="post" enctype="multipart/form-data" action="<?= h(app_url('/admin')) ?>"><input type="hidden" name="csrf_token" value="<?= h($token) ?>"><input type="hidden" name="action" value="upload_image"><input type="hidden" name="page" value="<?= h($page['slug']) ?>"><label>Image file<input type="file" name="image" accept="image/webp,image/png,image/jpeg,image/gif" required></label><label>Alt text<input type="text" name="alt_text" maxlength="255" placeholder="Describe the image"></label><button class="button" type="submit">Upload image</button></form>
    <div class="cms-media-list">
    <?php foreach ($media as $image): $imageUrl = app_url('/media.php?key=' . rawurlencode($image['media_key'])); ?>
      <article class="cms-media-item"><img src="<?= h($imageUrl) ?>" alt="<?= h($image['alt_text']) ?>" loading="lazy"><strong><?= h($image['file_name']) ?></strong><small><?= h($image['alt_text'] ?: 'No alt text set') ?></small><button class="cms-copy" type="button" data-key="<?= h($image['media_key']) ?>" data-use="<?= h('media.php?key=' . rawurlencode($image['media_key'])) ?>" data-alt="<?= h($image['alt_text']) ?>">Use this image</button></article>
    <?php endforeach; ?>
    </div>
  </section>
  <section class="cms-card cms-help"><h2>Easy editing</h2><ul><li>Click text in the preview and type your change.</li><li>Select words to change their paragraph or heading style, make them bold or italic, create a list, or turn them into a link.</li><li>Click an image, then choose a new one from the image library.</li><li>Use Page sections to show, hide, or reorder sections. Save the page to publish your changes.</li></ul></section>
</main>
<script>
(() => {
  const frame = document.getElementById('homepage-preview');
  const content = document.getElementById('homepage-content');
  const imageStatus = document.getElementById('cms-image-status');
  const sectionList = document.getElementById('cms-section-list');
  const linkPanel = document.getElementById('cms-link-editor');
  const linkText = document.getElementById('cms-link-text');
  const linkUrl = document.getElementById('cms-link-url');
  const imagePanel = document.getElementById('cms-image-editor');
  const imageAlt = document.getElementById('cms-image-alt');
  const bannerImageSelect = document.getElementById('banner-image-key');
  const bannerImageUpload = document.getElementById('banner-image-upload');
  let selectedImage = null;
  let selectedLink = null;
  let savedRange = null;
  let addingNewLink = false;

  const getSections = () => Array.from(frame.contentDocument.querySelectorAll('main > section'));
  const button = (label, action, disabled = false) => {
    const element = document.createElement('button');
    element.type = 'button';
    element.textContent = label;
    element.disabled = disabled;
    element.addEventListener('click', action);
    return element;
  };
  const refreshSections = () => {
    const sections = getSections();
    sectionList.replaceChildren();
    sections.forEach((section, index) => {
      const row = document.createElement('div');
      row.className = 'cms-section-row';
      row.setAttribute('aria-hidden', section.hidden ? 'true' : 'false');
      const heading = section.querySelector('h1,h2,h3');
      const label = document.createElement('span');
      label.textContent = (index + 1) + '. ' + ((heading?.innerText || section.getAttribute('aria-label') || 'Section').trim().replace(/\s+/g, ' ').slice(0, 72));
      const actions = document.createElement('div');
      actions.className = 'cms-section-actions';
      actions.append(
        button('Move up', () => { section.parentNode.insertBefore(section, sections[index - 1]); refreshSections(); }, index === 0),
        button('Move down', () => { section.parentNode.insertBefore(sections[index + 1], section); refreshSections(); }, index === sections.length - 1),
        button(section.hidden ? 'Show' : 'Hide', () => { section.hidden = !section.hidden; refreshSections(); }),
        button('Duplicate', () => { const copy = section.cloneNode(true); copy.hidden = false; copy.removeAttribute('id'); copy.querySelectorAll('[id]').forEach(element => element.removeAttribute('id')); section.parentNode.insertBefore(copy, section.nextSibling); refreshSections(); }),
        button('Remove', () => { if (confirm('Remove this section? Save the page to publish the change.')) { section.remove(); refreshSections(); } })
      );
      row.append(label, actions);
      sectionList.append(row);
    });
    if (!sections.length) sectionList.textContent = 'No sections yet. Use Add a section to create one.';
  };

  const setupPreview = () => {
    try {
      const doc = frame.contentDocument;
      doc.body.contentEditable = 'true';
      const rememberSelection = () => {
        const selection = doc.getSelection();
        if (!selection || !selection.rangeCount || selection.isCollapsed) {
          savedRange = null;
          return;
        }
        const range = selection.getRangeAt(0);
        if (doc.body.contains(range.commonAncestorContainer)) savedRange = range.cloneRange();
      };
      doc.addEventListener('mouseup', rememberSelection);
      doc.addEventListener('keyup', rememberSelection);
      doc.body.addEventListener('input', () => {
        const firstHeading = doc.querySelector('main h1,main h2,main h3,main h4,main h5,main h6');
        if (firstHeading) document.getElementById('banner-title').value = firstHeading.innerText.trim();
      });
      doc.body.addEventListener('paste', event => {
        const text = event.clipboardData?.getData('text/plain');
        if (text === undefined) return;
        event.preventDefault();
        doc.execCommand('insertText', false, text);
      });
      doc.body.addEventListener('click', event => {
        const image = event.target.closest('img');
        if (image) {
          if (selectedImage) selectedImage.style.outline = '';
          selectedImage = image;
          if (image === doc.querySelector('main img')) {
            const mediaKey = image.src.match(/media\.php\?key=([A-Za-z0-9._-]+)/)?.[1] || '';
            bannerImageSelect.value = mediaKey;
            document.getElementById('banner-image-alt').value = image.alt || '';
          }
          selectedLink = null;
          addingNewLink = false;
          linkText.readOnly = false;
          linkPanel.hidden = true;
          imagePanel.hidden = false;
          imageAlt.value = image.alt || '';
          image.style.outline = '3px solid #527a33';
          imageStatus.textContent = 'Image selected. Replace it below or update its description.';
          return;
        }
        const link = event.target.closest('a');
        if (link) {
          event.preventDefault();
          selectedLink = link;
          addingNewLink = false;
          linkText.readOnly = false;
          linkPanel.hidden = false;
          imagePanel.hidden = true;
          linkText.value = link.innerText.trim();
          linkUrl.value = link.getAttribute('href') || '';
          imageStatus.textContent = 'Edit the selected link below.';
        } else {
          selectedLink = null;
          addingNewLink = false;
          linkText.readOnly = false;
          linkPanel.hidden = true;
          imagePanel.hidden = true;
        }
      });
      refreshSections();
    } catch (_) { }
  };
  frame.addEventListener('load', setupPreview, { once: true });
  frame.srcdoc = frame.srcdoc;

  document.getElementById('cms-link-save').addEventListener('click', () => {
    const destination = linkUrl.value.trim();
    if (/^(javascript|data|vbscript):/i.test(destination) || destination === '') {
      imageStatus.textContent = 'Enter a page, website, email, or phone destination.';
      return;
    }
    if (addingNewLink && savedRange) {
      const doc = frame.contentDocument;
      const selection = doc.getSelection();
      frame.contentWindow.focus();
      selection.removeAllRanges();
      selection.addRange(savedRange);
      doc.execCommand('createLink', false, destination);
      selectedLink = null;
      savedRange = null;
      addingNewLink = false;
      linkText.readOnly = false;
      linkPanel.hidden = true;
    } else if (selectedLink) {
      selectedLink.innerText = linkText.value;
      selectedLink.setAttribute('href', destination);
    } else {
      imageStatus.textContent = 'Select words in the preview before adding a link.';
      return;
    }
    imageStatus.textContent = 'Link updated. Save the page to keep the change.';
  });
  document.getElementById('cms-format-apply').addEventListener('click', () => {
    if (!savedRange) { imageStatus.textContent = 'Select some text in the preview first.'; return; }
    const doc = frame.contentDocument;
    const selection = doc.getSelection();
    frame.contentWindow.focus();
    selection.removeAllRanges();
    selection.addRange(savedRange);
    doc.execCommand('formatBlock', false, '<' + document.getElementById('cms-format-choice').value + '>');
    savedRange = null;
    refreshSections();
    imageStatus.textContent = 'Text style updated. Save the page to keep the change.';
  });
  [['cms-format-bold', 'bold'], ['cms-format-italic', 'italic'], ['cms-format-list', 'insertUnorderedList']].forEach(([id, command]) => {
    document.getElementById(id).addEventListener('click', () => {
      if (!savedRange) { imageStatus.textContent = 'Select some text in the preview first.'; return; }
      const doc = frame.contentDocument;
      const selection = doc.getSelection();
      frame.contentWindow.focus();
      selection.removeAllRanges();
      selection.addRange(savedRange);
      doc.execCommand(command, false, null);
      savedRange = null;
      imageStatus.textContent = 'Formatting updated. Save the page to keep the change.';
    });
  });
  document.getElementById('cms-add-link').addEventListener('click', () => {
    if (!savedRange) { imageStatus.textContent = 'Select the words you want to link in the preview first.'; return; }
    const selectedNode = savedRange.commonAncestorContainer.nodeType === Node.ELEMENT_NODE
      ? savedRange.commonAncestorContainer
      : savedRange.commonAncestorContainer.parentElement;
    const existingAnchor = selectedNode?.closest('a');
    addingNewLink = !existingAnchor;
    selectedLink = existingAnchor || null;
    linkText.readOnly = addingNewLink;
    linkText.value = existingAnchor ? existingAnchor.innerText.trim() : savedRange.toString();
    linkUrl.value = existingAnchor?.getAttribute('href') || '';
    linkPanel.hidden = false;
    imageStatus.textContent = existingAnchor ? 'Edit the selected link below.' : 'Enter a destination for the selected words.';
    linkUrl.focus();
  });
  document.getElementById('cms-image-alt-save').addEventListener('click', () => {
    if (!selectedImage) return;
    selectedImage.alt = imageAlt.value.trim();
    if (selectedImage === frame.contentDocument.querySelector('main img')) {
      document.getElementById('banner-image-alt').value = selectedImage.alt;
      const mediaKey = selectedImage.src.match(/media\.php\?key=([A-Za-z0-9._-]+)/)?.[1] || '';
      bannerImageSelect.value = mediaKey;
    }
    imageStatus.textContent = 'Image description updated. Save the page to keep the change.';
  });
  document.getElementById('cms-add-section').addEventListener('click', () => {
    const section = frame.contentDocument.createElement('section');
    section.className = 'section container';
    section.innerHTML = '<p class="eyebrow"><span></span> NEW SECTION</p><h2>New section</h2><p>Click here to add your content.</p>';
    frame.contentDocument.querySelector('main').append(section);
    refreshSections();
  });
  document.getElementById('homepage-form').addEventListener('submit', () => {
    const doc = frame.contentDocument;
    if (selectedImage) selectedImage.style.outline = '';
    doc.body.removeAttribute('contenteditable');
    doc.title = document.getElementById('page-title').value;
    let description = doc.querySelector('meta[name="description"]');
    if (!description) { description = doc.createElement('meta'); description.name = 'description'; doc.head.append(description); }
    description.content = document.getElementById('page-description').value;
    const bannerTitle = document.getElementById('banner-title').value.trim();
    const headingTag = document.getElementById('banner-heading-tag').value.toLowerCase();
    const currentHeading = doc.querySelector('main h1,main h2,main h3,main h4,main h5,main h6');
    if (currentHeading && bannerTitle) {
      const heading = doc.createElement(headingTag);
      for (const attribute of currentHeading.attributes) heading.setAttribute(attribute.name, attribute.value);
      while (currentHeading.firstChild) heading.appendChild(currentHeading.firstChild);
      if (heading.textContent.trim() !== bannerTitle) heading.textContent = bannerTitle;
      currentHeading.replaceWith(heading);
    }
    const bannerImageKey = document.getElementById('banner-image-key').value;
    const bannerImage = doc.querySelector('main img');
    if (bannerImage && bannerImageKey) {
      bannerImage.src = <?= json_encode(app_url('/media.php?key='), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> + encodeURIComponent(bannerImageKey);
      bannerImage.alt = document.getElementById('banner-image-alt').value.trim();
    }
    content.value = '<!doctype html>\n' + doc.documentElement.outerHTML;
  });
  document.querySelectorAll('[data-use]').forEach(item => item.addEventListener('click', () => {
    if (!selectedImage) {
      imageStatus.textContent = 'Select an image in the preview first.';
      return;
    }
    selectedImage.src = item.dataset.use;
    selectedImage.alt = item.dataset.alt || '';
    if (selectedImage === frame.contentDocument.querySelector('main img')) {
      bannerImageSelect.value = item.dataset.key;
      document.getElementById('banner-image-alt').value = selectedImage.alt;
    }
    selectedImage.style.outline = '';
    selectedImage = null;
    imagePanel.hidden = true;
    imageStatus.textContent = 'Image replaced. Save the page to keep the change.';
  }));
  bannerImageSelect.addEventListener('change', () => {
    bannerImageUpload.value = '';
    const image = frame.contentDocument.querySelector('main img');
    if (!image || !bannerImageSelect.value) return;
    image.src = <?= json_encode(app_url('/media.php?key='), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> + encodeURIComponent(bannerImageSelect.value);
    image.alt = document.getElementById('banner-image-alt').value;
  });
  bannerImageUpload.addEventListener('change', () => {
    const file = bannerImageUpload.files?.[0];
    const image = frame.contentDocument.querySelector('main img');
    if (!file || !image) return;
    bannerImageSelect.value = '';
    image.src = URL.createObjectURL(file);
    image.alt = document.getElementById('banner-image-alt').value;
  });
})();
</script>
</body>
</html>
