<?php
declare(strict_types=1);

function cms_homepage(PDO $pdo): ?array
{
    return cms_page($pdo, 'home');
}

function cms_page(PDO $pdo, string $slug): ?array
{
    $query = $pdo->prepare('SELECT * FROM cms_pages WHERE slug = :slug LIMIT 1');
    $query->execute(['slug' => $slug]);
    $page = $query->fetch();
    return $page ?: null;
}

function cms_pages(PDO $pdo): array
{
    return $pdo->query('SELECT slug, page_name, sort_order, is_active, show_in_nav FROM cms_pages ORDER BY sort_order, page_name, slug')->fetchAll();
}

function cms_site_settings(PDO $pdo): array
{
    $settings = [];
    foreach ($pdo->query('SELECT setting_key, setting_value FROM cms_site_settings')->fetchAll() as $row) {
        $settings[(string)$row['setting_key']] = (string)$row['setting_value'];
    }
    return $settings;
}

function cms_social_links(PDO $pdo, bool $activeOnly = true): array
{
    $where = $activeOnly ? 'WHERE is_active = 1' : '';
    return $pdo->query("SELECT id, label, icon_class, url, sort_order, open_new_tab, is_active FROM cms_social_links {$where} ORDER BY sort_order, id")->fetchAll();
}

function cms_resolve_site_url(string $url): string
{
    $url = trim($url);
    if (preg_match('~^(?:https?://|mailto:|tel:|#)~i', $url)) return $url;
    return app_url('/' . ltrim($url, '/'));
}

function cms_render_html(string $html, ?array $settings = null, array $allPages = [], array $siteSettings = [], array $socialLinks = []): string
{
    $basePath = app_base_path();
    if ($basePath !== '') {
        $basePattern = preg_quote($basePath, '~');
        $html = preg_replace_callback(
            '~(?:' . $basePattern . '/+)+media\.php\?key=([A-Za-z0-9._-]+)~',
            static fn(array $match): string => $basePath . '/media.php?key=' . rawurlencode($match[1]),
            $html
        ) ?? $html;
        $html = preg_replace_callback(
            '~(?<!/)media\.php\?key=([A-Za-z0-9._-]+)~',
            static fn(array $match): string => $basePath . '/media.php?key=' . rawurlencode($match[1]),
            $html
        ) ?? $html;
    }

    if ($settings === null) return $html;
    $previousLibxmlSetting = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    $xpath = new DOMXPath($dom);
    $head = $dom->getElementsByTagName('head')->item(0);
    $body = $dom->getElementsByTagName('body')->item(0);
    if ($head) {
        $setMeta = static function (string $selector, string $attribute, string $value) use ($dom, $xpath, $head): void {
            $node = $xpath->query($selector)->item(0);
            if ($value === '') return;
            if (!$node) {
                $node = $dom->createElement('meta');
                $head->appendChild($node);
            }
            $node->setAttribute($attribute, $value);
        };
        if (($settings['meta_title'] ?? '') !== '') {
            $title = $dom->getElementsByTagName('title')->item(0);
            if (!$title) { $title = $dom->createElement('title'); $head->appendChild($title); }
            $title->textContent = (string)$settings['meta_title'];
        }
        $setMeta('//meta[translate(@name,"DESCRIPTION","description")="description"]', 'content', (string)($settings['meta_description'] ?? ''));
        $setMeta('//meta[translate(@name,"KEYWORDS","keywords")="keywords"]', 'content', (string)($settings['meta_keywords'] ?? ''));
        foreach (preg_split('/\r\n|\r|\n/', (string)($settings['meta_tags'] ?? '')) ?: [] as $tagLine) {
            if (!str_contains($tagLine, '=')) continue;
            [$tagName, $tagContent] = array_map('trim', explode('=', $tagLine, 2));
            $attribute = 'name';
            if (str_starts_with(strtolower($tagName), 'property:')) {
                $attribute = 'property';
                $tagName = substr($tagName, 9);
            } elseif (str_starts_with(strtolower($tagName), 'name:')) {
                $tagName = substr($tagName, 5);
            }
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9:_-]{0,100}$/', $tagName) || $tagContent === '') continue;
            $existing = $xpath->query('//meta[@' . $attribute . '="' . $tagName . '"]')->item(0);
            if (!$existing) { $existing = $dom->createElement('meta'); $head->appendChild($existing); }
            $existing->setAttribute($attribute, $tagName);
            $existing->setAttribute('content', $tagContent);
        }
        $canonical = $xpath->query('//link[translate(@rel,"CANONICAL","canonical")="canonical"]')->item(0);
        $canonicalUrl = trim((string)($settings['canonical_url'] ?? ''));
        if ($canonicalUrl === '' && !empty($siteSettings['site_base_url'])) {
            $siteBase = rtrim(trim((string)$siteSettings['site_base_url']), '/');
            $slug = (string)($settings['slug'] ?? 'home');
            $canonicalUrl = $siteBase . ($slug === 'home' ? '/' : '/' . rawurlencode($slug) . '.html');
        }
        if ($canonicalUrl !== '') {
            if (!$canonical) { $canonical = $dom->createElement('link'); $canonical->setAttribute('rel', 'canonical'); $head->appendChild($canonical); }
            $canonical->setAttribute('href', $canonicalUrl);
        }
    }
    if ($body) {
        $ownerWhatsapp = preg_replace('/\D+/', '', (string)($siteSettings['owner_whatsapp_number'] ?? '')) ?? '';
        $contactEmail = trim((string)($siteSettings['contact_email'] ?? ''));
        $root = $dom->documentElement;
        if ($root instanceof DOMElement) {
            $root->setAttribute('data-owner-whatsapp', $ownerWhatsapp);
            $root->setAttribute('data-contact-email', $contactEmail);
        }
        $phone = trim((string)($siteSettings['contact_phone'] ?? ''));
        $phoneHref = preg_replace('/[^0-9+]/', '', $phone) ?? '';
        if ($phone !== '') {
            foreach ($xpath->query('//a[starts-with(@href,"tel:")]') as $phoneLink) {
                $phoneLink->setAttribute('href', 'tel:' . $phoneHref);
                foreach ($xpath->query('.//text()', $phoneLink) as $textNode) {
                    if (strpbrk((string)$textNode->nodeValue, '0123456789') !== false) $textNode->nodeValue = $phone;
                }
            }
        }
        if ($contactEmail !== '') {
            foreach ($xpath->query('//a[starts-with(@href,"mailto:")]') as $emailLink) {
                $emailLink->setAttribute('href', 'mailto:' . $contactEmail);
                foreach ($xpath->query('.//text()', $emailLink) as $textNode) {
                    if (str_contains($textNode->nodeValue, '@')) {
                        $textNode->nodeValue = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $contactEmail, $textNode->nodeValue) ?? $contactEmail;
                    }
                }
            }
        } elseif (array_key_exists('contact_email', $siteSettings)) {
            foreach (iterator_to_array($xpath->query('//a[starts-with(@href,"mailto:")]')) as $emailLink) {
                $emailLink->parentNode?->removeChild($emailLink);
            }
        }
        $contactAddress = trim((string)($siteSettings['contact_address'] ?? ''));
        if ($contactAddress !== '') {
            foreach ($xpath->query('//footer//strong[normalize-space(.)="Our Address"]') as $addressLabel) {
                $addressSpan = $addressLabel->parentNode;
                while ($addressLabel->nextSibling) $addressSpan->removeChild($addressLabel->nextSibling);
                $addressSpan->appendChild($dom->createTextNode($contactAddress));
            }
            foreach ($xpath->query('//footer//address') as $addressNode) {
                while ($addressNode->firstChild) $addressNode->removeChild($addressNode->firstChild);
                foreach (preg_split('/\r\n|\r|\n/', $contactAddress) ?: [] as $line => $addressLine) {
                    if ($line > 0) $addressNode->appendChild($dom->createElement('br'));
                    $addressNode->appendChild($dom->createTextNode($addressLine));
                }
            }
            foreach ($xpath->query('//main//a[contains(@class,"contact-info-card")]') as $contactCard) {
                if (str_contains(strtolower($contactCard->textContent), 'visit us')) {
                    $paragraph = $xpath->query('.//p', $contactCard)->item(0);
                    if ($paragraph) $paragraph->textContent = $contactAddress;
                }
            }
        }
        $mapUrl = trim((string)($siteSettings['contact_map_embed_url'] ?? ''));
        if ($mapUrl !== '') {
            foreach ($xpath->query('//iframe[contains(@src,"google.com/maps")]') as $mapFrame) $mapFrame->setAttribute('src', $mapUrl);
            foreach ($xpath->query('//main//a[contains(@href,"google.com/maps")]') as $mapLink) {
                $mapLink->setAttribute('href', $mapUrl);
                $mapLink->setAttribute('target', '_blank');
                $mapLink->setAttribute('rel', 'noopener noreferrer');
            }
        } elseif (array_key_exists('contact_map_embed_url', $siteSettings)) {
            foreach ($xpath->query('//main//section[contains(@class,"contact-map-section")]') as $mapSection) {
                $mapSection->setAttribute('hidden', 'hidden');
            }
        }
        $orderLabel = trim((string)($siteSettings['order_button_label'] ?? ''));
        $orderUrl = trim((string)($siteSettings['order_button_url'] ?? ''));
        if ($orderLabel !== '' && $orderUrl !== '') {
            $orderHref = cms_resolve_site_url($orderUrl);
            foreach ($xpath->query('//a[contains(concat(" ",normalize-space(@class)," ")," button ")]') as $orderLink) {
                if (preg_match('/\b(order|quote)\b/i', $orderLink->textContent)) {
                    $orderLink->textContent = $orderLabel;
                    $orderLink->setAttribute('href', $orderHref);
                }
            }
        }
        $bannerTitle = trim((string)($settings['banner_title'] ?? ''));
        $bannerHeading = $xpath->query('(//main//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6])[1]')->item(0);
        $headingTag = strtoupper((string)($settings['banner_heading_tag'] ?? 'H1'));
        if (!in_array($headingTag, ['H1', 'H2', 'H3', 'H4', 'H5', 'H6'], true)) $headingTag = 'H1';
        if ($bannerHeading && $bannerTitle !== '') {
            $replacement = $dom->createElement(strtolower($headingTag));
            if ($bannerHeading->hasAttributes()) {
                foreach ($bannerHeading->attributes as $attribute) $replacement->setAttribute($attribute->name, $attribute->value);
            }
            $replacement->textContent = $bannerTitle;
            $bannerHeading->parentNode?->replaceChild($replacement, $bannerHeading);
        } elseif ($bannerHeading && $bannerHeading->tagName !== strtolower($headingTag)) {
            $replacement = $dom->createElement(strtolower($headingTag));
            foreach ($bannerHeading->attributes as $attribute) $replacement->setAttribute($attribute->name, $attribute->value);
            while ($bannerHeading->firstChild) $replacement->appendChild($bannerHeading->firstChild);
            $bannerHeading->parentNode?->replaceChild($replacement, $bannerHeading);
        }
        $serviceTag = strtoupper((string)($settings['service_list_heading_tag'] ?? 'H2'));
        if (!in_array($serviceTag, ['H1', 'H2', 'H3', 'H4', 'H5', 'H6'], true)) $serviceTag = 'H2';
        $firstHeading = $xpath->query('(//main//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6])[1]')->item(0);
        foreach ($xpath->query('//main//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]') as $serviceHeading) {
            if ($firstHeading && $serviceHeading->isSameNode($firstHeading)) continue;
            if (preg_match('/\bservices?\b/i', $serviceHeading->textContent)) {
                $replacement = $dom->createElement(strtolower($serviceTag));
                foreach ($serviceHeading->attributes as $attribute) $replacement->setAttribute($attribute->name, $attribute->value);
                while ($serviceHeading->firstChild) $replacement->appendChild($serviceHeading->firstChild);
                $serviceHeading->parentNode?->replaceChild($replacement, $serviceHeading);
                break;
            }
        }
        $imageKey = trim((string)($settings['banner_image_key'] ?? ''));
        $bannerImage = $xpath->query('(//main//img)[1]')->item(0);
        if ($bannerImage && $imageKey !== '') {
            $bannerImage->setAttribute('src', app_url('/media.php?key=' . rawurlencode($imageKey)));
            $bannerImage->setAttribute('alt', (string)($settings['banner_image_alt'] ?? ''));
        }
        if (array_key_exists('is_active', $settings) && !(int)$settings['is_active']) {
            return '';
        }
        $hiddenSlugs = [];
        foreach ($allPages as $knownPage) {
            if (empty($knownPage['show_in_nav']) || empty($knownPage['is_active'])) $hiddenSlugs[(string)$knownPage['slug']] = true;
        }
        $navigationLinks = iterator_to_array($xpath->query('//nav//a[@href]'));
        foreach ($navigationLinks as $anchor) {
            $href = (string)$anchor->getAttribute('href');
            if (preg_match('~(?:^|/)([a-z0-9-]+)\.html(?:[?#].*)?$~i', $href, $match)) {
                $linkedSlug = strtolower($match[1]) === 'index' ? 'home' : strtolower($match[1]);
                if (isset($hiddenSlugs[$linkedSlug])) $anchor->parentNode?->removeChild($anchor);
            } elseif (preg_match('~^(?:/|#home)$~i', $href) && isset($hiddenSlugs['home'])) {
                $anchor->parentNode?->removeChild($anchor);
            }
        }
        $sortOrders = [];
        foreach ($allPages as $knownPage) $sortOrders[(string)$knownPage['slug']] = (int)($knownPage['sort_order'] ?? 0);
        foreach ($xpath->query('//*[@id="navigation"]') as $navigation) {
            $items = [];
            foreach ($navigation->childNodes as $position => $child) {
                if (!$child instanceof DOMElement) continue;
                $anchor = $child->tagName === 'a' ? $child : $xpath->query('.//a[@href]', $child)->item(0);
                $order = 100000;
                if ($anchor instanceof DOMElement && preg_match('~(?:^|/)([a-z0-9-]+)\.html(?:[?#].*)?$~i', (string)$anchor->getAttribute('href'), $match)) {
                    $linkedSlug = strtolower($match[1]) === 'index' ? 'home' : strtolower($match[1]);
                    $order = $sortOrders[$linkedSlug] ?? $order;
                }
                $items[] = ['node' => $child, 'order' => $order, 'position' => $position];
            }
            usort($items, static fn(array $a, array $b): int => ($a['order'] <=> $b['order']) ?: ($a['position'] <=> $b['position']));
            foreach ($items as $item) $navigation->appendChild($item['node']);
        }
        $socialLinks = array_values(array_filter($socialLinks, static fn(array $link): bool => !empty($link['is_active'])));
        if ($socialLinks) {
            $footer = $xpath->query('//footer')->item(0);
            if ($footer) {
                $socialBar = $dom->createElement('div');
                $socialBar->setAttribute('class', 'container footer-social-links');
                $socialBar->setAttribute('aria-label', 'Social media and ordering links');
                foreach ($socialLinks as $social) {
                    $link = $dom->createElement('a');
                    $link->setAttribute('href', (string)$social['url']);
                    if (!empty($social['open_new_tab'])) { $link->setAttribute('target', '_blank'); $link->setAttribute('rel', 'noopener noreferrer'); }
                    $icon = $dom->createElement('i');
                    $icon->setAttribute('class', (string)$social['icon_class']);
                    $icon->setAttribute('aria-hidden', 'true');
                    $link->appendChild($icon);
                    $label = $dom->createElement('span');
                    $label->textContent = (string)$social['label'];
                    $link->appendChild($label);
                    $socialBar->appendChild($link);
                }
                $footerBottom = $xpath->query('.//*[contains(concat(" ",normalize-space(@class)," ")," footer-bottom ")]', $footer)->item(0);
                if ($footerBottom) $footer->insertBefore($socialBar, $footerBottom);
                else $footer->appendChild($socialBar);
                if ($head && !$xpath->query('//link[contains(@href,"font-awesome")]')->length) {
                    $fontAwesome = $dom->createElement('link');
                    $fontAwesome->setAttribute('rel', 'stylesheet');
                    $fontAwesome->setAttribute('href', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css');
                    $head->appendChild($fontAwesome);
                }
            }
        }
    }
    $result = $dom->saveHTML();
    libxml_clear_errors();
    libxml_use_internal_errors($previousLibxmlSetting);
    return preg_replace('/<\?xml encoding="utf-8" \?>/i', '', $result ?: $html) ?? $html;
}
