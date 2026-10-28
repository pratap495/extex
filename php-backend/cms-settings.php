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
        if ($action === 'save_settings') {
            $values = [
                'site_base_url' => rtrim(trim((string)($_POST['site_base_url'] ?? '')), '/'),
                'order_button_label' => trim((string)($_POST['order_button_label'] ?? '')),
                'order_button_url' => trim((string)($_POST['order_button_url'] ?? '')),
                'contact_phone' => trim((string)($_POST['contact_phone'] ?? '')),
                'contact_email' => trim((string)($_POST['contact_email'] ?? '')),
                'contact_address' => trim((string)($_POST['contact_address'] ?? '')),
                'contact_map_embed_url' => trim((string)($_POST['contact_map_embed_url'] ?? '')),
                'owner_whatsapp_number' => preg_replace('/\D+/', '', (string)($_POST['owner_whatsapp_number'] ?? '')) ?? '',
                'robots_txt_content' => trim((string)($_POST['robots_txt_content'] ?? '')),
            ];
            $baseParts = parse_url($values['site_base_url']);
            $baseValid = filter_var($values['site_base_url'], FILTER_VALIDATE_URL)
                && in_array(strtolower((string)($baseParts['scheme'] ?? '')), ['http', 'https'], true)
                && isset($baseParts['host']);
            $validOptionalUrl = static function (string $url): bool {
                if ($url === '') return true;
                if (preg_match('~^https?://~i', $url)) return (bool)filter_var($url, FILTER_VALIDATE_URL);
                return !str_starts_with($url, '//') && !preg_match('/\s|^(javascript|data|vbscript):/i', $url);
            };
            $mapParts = $values['contact_map_embed_url'] !== '' ? parse_url($values['contact_map_embed_url']) : [];
            $mapValid = $values['contact_map_embed_url'] === '' || (
                filter_var($values['contact_map_embed_url'], FILTER_VALIDATE_URL)
                && strtolower((string)($mapParts['scheme'] ?? '')) === 'https'
                && preg_match('/(^|\.)google\.com$/i', (string)($mapParts['host'] ?? ''))
                && str_contains(strtolower((string)($mapParts['path'] ?? '')), '/maps')
            );
            if (!$baseValid || !$validOptionalUrl($values['order_button_url']) || !$mapValid
                || ($values['contact_email'] !== '' && !filter_var($values['contact_email'], FILTER_VALIDATE_EMAIL))
                || ($values['owner_whatsapp_number'] !== '' && !preg_match('/^[0-9]{8,15}$/', $values['owner_whatsapp_number']))
                || strlen($values['robots_txt_content']) > 20000) {
                $error = 'Please check the website URL, social URLs, email, Google Maps embed URL, WhatsApp number, and robots.txt length.';
            } else {
                $upsert = $pdo->prepare('INSERT INTO cms_site_settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
                foreach ($values as $key => $value) $upsert->execute(['setting_key' => $key, 'setting_value' => $value]);
                header('Location: ' . app_url('/admin/settings?saved=1'), true, 303);
                exit;
            }
        } elseif ($action === 'save_social') {
            $id = max(0, (int)($_POST['id'] ?? 0));
            $label = mb_substr(trim((string)($_POST['label'] ?? '')), 0, 100);
            $iconClass = trim((string)($_POST['icon_class'] ?? ''));
            $url = trim((string)($_POST['url'] ?? ''));
            $sortOrder = max(-100000, min(100000, (int)($_POST['sort_order'] ?? 0)));
            $parts = parse_url($url);
            $urlValid = filter_var($url, FILTER_VALIDATE_URL)
                && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true);
            if ($label === '' || !preg_match('/^[a-zA-Z0-9 _-]{1,160}$/', $iconClass) || !$urlValid) {
                $error = 'Enter a label, Font Awesome class (for example fab fa-instagram), and a valid HTTPS or HTTP link.';
            } else {
                $values = [
                    'label' => $label,
                    'icon_class' => $iconClass,
                    'url' => $url,
                    'sort_order' => $sortOrder,
                    'open_new_tab' => isset($_POST['open_new_tab']) ? 1 : 0,
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                ];
                if ($id > 0) {
                    $values['id'] = $id;
                    $save = $pdo->prepare('UPDATE cms_social_links SET label = :label, icon_class = :icon_class, url = :url, sort_order = :sort_order, open_new_tab = :open_new_tab, is_active = :is_active WHERE id = :id');
                } else {
                    $save = $pdo->prepare('INSERT INTO cms_social_links (label, icon_class, url, sort_order, open_new_tab, is_active) VALUES (:label, :icon_class, :url, :sort_order, :open_new_tab, :is_active)');
                }
                $save->execute($values);
                header('Location: ' . app_url('/admin/settings?social_saved=1'), true, 303);
                exit;
            }
        } elseif ($action === 'delete_social') {
            $delete = $pdo->prepare('DELETE FROM cms_social_links WHERE id = :id');
            $delete->execute(['id' => max(0, (int)($_POST['id'] ?? 0))]);
            header('Location: ' . app_url('/admin/settings?social_deleted=1'), true, 303);
            exit;
        }
    }
}

$settings = cms_site_settings($pdo);
$socialLinks = cms_social_links($pdo, false);
$editId = max(0, (int)($_GET['edit_social'] ?? 0));
$editingSocial = null;
foreach ($socialLinks as $social) if ((int)$social['id'] === $editId) $editingSocial = $social;
$token = csrf_token();
$message = isset($_GET['saved']) ? 'Site settings saved.' : (isset($_GET['social_saved']) ? 'Footer link saved.' : (isset($_GET['social_deleted']) ? 'Footer link removed.' : ''));
$field = static fn(string $key, string $fallback = ''): string => (string)($settings[$key] ?? $fallback);
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SEO &amp; Site Settings | Extek Profiles</title>
  <link rel="stylesheet" href="<?= h(app_url('/fonts.css')) ?>"><link rel="stylesheet" href="<?= h(app_url('/styles.css')) ?>">
  <style>
    .settings-page{background:#f3f7ef;color:#2a3e49}.settings-page .settings-card{box-shadow:0 12px 35px #2a3e490a}.settings-page .settings-error{background:#fde8e7}
    .settings-page{min-height:100vh;padding:34px 0 80px}.settings-wrap{width:min(1200px,calc(100% - 32px));margin:auto}.settings-head,.settings-actions{display:flex;align-items:center;justify-content:space-between;gap:16px}.settings-head{margin-bottom:24px}.settings-card{background:white;border:1px solid #e5e9e1;border-radius:16px;padding:24px;margin-bottom:22px}.settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.settings-grid label{display:grid;gap:6px;font-weight:600}.settings-grid input,.settings-grid textarea{width:100%;padding:10px;border:1px solid #d9e1d4;border-radius:7px;font:inherit}.settings-grid textarea{min-height:100px;resize:vertical}.settings-wide{grid-column:1/-1}.settings-hint{font-size:13px;color:#59666e}.settings-notice{padding:12px 16px;border-radius:8px;background:#e5f2dc;margin:0 0 18px}.settings-error{background:#fde8e7;color:#842c26}.settings-table-wrap{overflow-x:auto}.settings-table{width:100%;border-collapse:collapse;margin-top:14px}.settings-table th,.settings-table td{padding:12px;border-bottom:1px solid #e5e9e1;text-align:left;vertical-align:top}.settings-table td.url{max-width:370px;overflow-wrap:anywhere}.settings-inline{display:inline}.settings-inline button,.settings-edit{border:1px solid #d9e1d4;border-radius:7px;padding:7px 10px;background:white;cursor:pointer}.settings-form-actions{display:flex;gap:10px;margin-top:18px;flex-wrap:wrap}.settings-head nav{display:flex;gap:10px;flex-wrap:wrap}@media(max-width:700px){.settings-grid{grid-template-columns:1fr}.settings-wide{grid-column:auto}.settings-head{align-items:flex-start;flex-direction:column}.settings-card{padding:16px}}
  </style>
</head>
<body class="settings-page"><main class="settings-wrap">
  <header class="settings-head"><div><p class="eyebrow"><span></span> EXTEK PROFILES WEBSITE</p><h1>SEO &amp; Site Settings</h1><p>Manage business contact details, inquiry links, search visibility, and footer social links.</p><p class="settings-hint">Bibinagar | Telangana, India</p></div><nav><a class="button outline" href="<?= h(app_url('/admin')) ?>">Page editor</a><form method="post" action="<?= h(app_url('/logout')) ?>"><input type="hidden" name="csrf_token" value="<?= h($token) ?>"><button class="button" type="submit">Sign out</button></form></nav></header>
  <?php if ($message !== ''): ?><p class="settings-notice" role="status"><?= h($message) ?></p><?php endif; ?>
  <?php if ($error !== ''): ?><p class="settings-notice settings-error" role="alert"><?= h($error) ?></p><?php endif; ?>
  <p class="settings-notice">For page-specific SEO titles, descriptions, keywords, banners, and canonical URLs, open the Page editor and choose the page you want to update.</p>
  <form class="settings-card" method="post" action="<?= h(app_url('/admin/settings')) ?>">
    <input type="hidden" name="csrf_token" value="<?= h($token) ?>"><input type="hidden" name="action" value="save_settings">
    <h2>Business, Contact &amp; Search Settings</h2><p class="settings-hint">These settings are used by Extek Profiles contact links, the inquiry button, canonical URLs, sitemap, and robots.txt.</p>
    <div class="settings-grid">
      <label>Site Base URL<input name="site_base_url" type="url" required value="<?= h($field('site_base_url', 'https://extekprofiles.com')) ?>"><small class="settings-hint">Used for canonical tags, sitemap URLs, and the robots.txt sitemap link.</small></label>
      <label>Main Contact Button Label<input name="order_button_label" value="<?= h($field('order_button_label', 'Get a Quote')) ?>" maxlength="120" placeholder="Get a Quote"></label>
      <label>Main Contact Button URL<input name="order_button_url" value="<?= h($field('order_button_url', 'contact.html')) ?>" maxlength="1000" placeholder="contact.html or https://example.com/contact"><small class="settings-hint">Use a site path such as contact.html or a full URL.</small></label>
      <label>Contact Phone<input name="contact_phone" value="<?= h($field('contact_phone', '+91 96769 10662')) ?>" maxlength="100" placeholder="+91 96769 10662"></label>
      <label>Contact Email<input name="contact_email" type="email" value="<?= h($field('contact_email', 'info@extekprofiles.com')) ?>" maxlength="255" placeholder="info@extekprofiles.com"></label>
      <label class="settings-wide">Contact Address<textarea name="contact_address" maxlength="2000" placeholder="Survey No. 21, Mahadevpur Village, Bibinagar"><?= h($field('contact_address', 'Survey No. 21, Mahadevpur Village, Bibinagar – 508126, Yadadri Bhuvanagiri District, Telangana, India.')) ?></textarea></label>
      <label class="settings-wide">Contact Map Embed URL<input name="contact_map_embed_url" type="url" value="<?= h($field('contact_map_embed_url', 'https://www.google.com/maps?output=embed&q=Survey+No.+21,+Mahadevpur+Village,+Bibinagar,+Telangana+508126,+India')) ?>" maxlength="1500" placeholder="https://www.google.com/maps?output=embed&amp;q=..."><small class="settings-hint">Paste only the Google Maps iframe src URL for the Extek facility.</small></label>
      <label>Owner WhatsApp Number<input name="owner_whatsapp_number" inputmode="numeric" value="<?= h($field('owner_whatsapp_number')) ?>" maxlength="15" placeholder="Optional: country code and number"><small class="settings-hint">Optional. If set, contact form enquiries open WhatsApp with the form details.</small></label>
      <label class="settings-wide">robots.txt Content<textarea name="robots_txt_content" maxlength="20000"><?= h($field('robots_txt_content', "User-agent: *\nAllow: /")) ?></textarea></label>
    </div>
    <div class="settings-form-actions"><button class="button" type="submit">Save site settings</button></div>
  </form>

  <section class="settings-card"><h2><?= $editingSocial ? 'Edit Footer Social Link' : 'Add Footer Social Icon' ?></h2>
    <form method="post" action="<?= h(app_url('/admin/settings')) ?>">
      <input type="hidden" name="csrf_token" value="<?= h($token) ?>"><input type="hidden" name="action" value="save_social"><input type="hidden" name="id" value="<?= (int)($editingSocial['id'] ?? 0) ?>">
      <div class="settings-grid">
        <label>Label<input name="label" required maxlength="100" value="<?= h((string)($editingSocial['label'] ?? '')) ?>" placeholder="Instagram"></label>
        <label>Icon Class<input name="icon_class" required maxlength="160" value="<?= h((string)($editingSocial['icon_class'] ?? '')) ?>" placeholder="fab fa-instagram"><small class="settings-hint">Font Awesome 6 class, for example fab fa-instagram, fab fa-whatsapp, fab fa-youtube, fab fa-facebook-f.</small></label>
        <label class="settings-wide">URL<input name="url" type="url" required maxlength="1000" value="<?= h((string)($editingSocial['url'] ?? '')) ?>" placeholder="https://www.instagram.com/your-account/"></label>
        <label>Sort Order<input type="number" name="sort_order" value="<?= (int)($editingSocial['sort_order'] ?? count($socialLinks)) ?>"></label>
        <label class="settings-check"><input type="checkbox" name="open_new_tab" value="1" <?= !isset($editingSocial['open_new_tab']) || (int)$editingSocial['open_new_tab'] ? 'checked' : '' ?>> Open in new tab</label>
        <label class="settings-check"><input type="checkbox" name="is_active" value="1" <?= !isset($editingSocial['is_active']) || (int)$editingSocial['is_active'] ? 'checked' : '' ?>> Active</label>
      </div>
      <div class="settings-form-actions"><button class="button" type="submit"><?= $editingSocial ? 'Save social link' : 'Add social link' ?></button><?php if ($editingSocial): ?><a class="settings-edit" href="<?= h(app_url('/admin/settings')) ?>">Cancel edit</a><?php endif; ?></div>
    </form>
    <h3>Existing Social Links</h3>
    <div class="settings-table-wrap"><table class="settings-table"><thead><tr><th>Label</th><th>Icon Class</th><th>URL</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($socialLinks as $social): ?><tr><td><?= h($social['label']) ?></td><td><code><?= h($social['icon_class']) ?></code></td><td class="url"><a href="<?= h($social['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($social['url']) ?></a></td><td><?= (int)$social['sort_order'] ?></td><td><?= (int)$social['is_active'] ? 'Active' : 'Inactive' ?></td><td><a class="settings-edit" href="<?= h(app_url('/admin/settings?edit_social=' . (int)$social['id'])) ?>">Edit</a> <form class="settings-inline" method="post" action="<?= h(app_url('/admin/settings')) ?>" onsubmit="return confirm('Remove this footer link?')"><input type="hidden" name="csrf_token" value="<?= h($token) ?>"><input type="hidden" name="action" value="delete_social"><input type="hidden" name="id" value="<?= (int)$social['id'] ?>"><button type="submit">Delete</button></form></td></tr><?php endforeach; ?>
    <?php if (!$socialLinks): ?><tr><td colspan="6">No social links have been added.</td></tr><?php endif; ?>
    </tbody></table></div>
  </section>
</main></body></html>
