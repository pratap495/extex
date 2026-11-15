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

/** Copy the edited shared navigation to all CMS pages so one admin edit updates the whole site. */
function cms_sync_navigation(PDO $pdo, string $sourceHtml): void
{
    $extract = static function (string $html): ?string {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);
        $node = $xpath->query('//*[@id="navigation"]')->item(0);
        if (!$node instanceof DOMElement) return null;
        $cta = $xpath->query('following-sibling::*[1][self::a[contains(concat(" ", normalize-space(@class), " "), " nav-cta ")]]', $node)->item(0);
        return $dom->saveHTML($node) . ($cta instanceof DOMElement ? $dom->saveHTML($cta) : '');
    };
    $navigation = $extract($sourceHtml);
    if (!$navigation) return;
    $replace = static function (string $html, string $slug) use ($navigation): string {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);
        $current = $xpath->query('//*[@id="navigation"]')->item(0);
        if (!$current || !$current->parentNode) return $html;
        $fragment = new DOMDocument('1.0', 'UTF-8');
        $fragment->loadHTML('<?xml encoding="UTF-8"><div id="fragment">' . $navigation . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $fragmentXPath = new DOMXPath($fragment);
        $newNode = $fragmentXPath->query('//*[@id="navigation"]')->item(0);
        if (!$newNode) return $html;
        $imported = $dom->importNode($newNode, true);
        $newCta = $fragmentXPath->query('following-sibling::*[1][self::a[contains(concat(" ", normalize-space(@class), " "), " nav-cta ")]]', $newNode)->item(0);
        $importedCta = $newCta instanceof DOMElement ? $dom->importNode($newCta, true) : null;
        $activeLinks = [
            'home' => 'index#home', 'about' => 'about#story-title', 'products' => 'products',
            'blogs' => 'blogs', 'gallery' => 'gallery',
            'quality' => 'quality', 'industries' => 'industries', 'careers' => 'careers',
            'profile-cutting' => 'profile-cutting', 'fabrication' => 'fabrication',
            'profile-design' => 'profile-design', 'partners-procurement' => 'partners-procurement',
            'sales-marketing' => 'sales-marketing', 'contact' => 'contact',
        ];
        $activeHref = $activeLinks[$slug] ?? '';
        if ($activeHref !== '') {
            $newXPath = new DOMXPath($dom);
            foreach ($newXPath->query('.//a[@href]', $imported) as $link) {
                if ($link->getAttribute('href') !== $activeHref) continue;
                $classes = preg_split('/\s+/', trim($link->getAttribute('class'))) ?: [];
                if (!in_array('active', $classes, true)) $classes[] = 'active';
                $link->setAttribute('class', trim(implode(' ', $classes)));
                $link->setAttribute('aria-current', 'page');
            }
        }
        $parent = $current->parentNode;
        $oldCta = $xpath->query('following-sibling::*[1][self::a[contains(concat(" ", normalize-space(@class), " "), " nav-cta ")]]', $current)->item(0);
        $parent->replaceChild($imported, $current);
        if ($oldCta) $parent->removeChild($oldCta);
        if ($importedCta) $parent->insertBefore($importedCta, $imported->nextSibling);
        return preg_replace('/^<\?xml[^>]*>/', '', $dom->saveHTML()) ?? $html;
    };
    $pages = $pdo->query('SELECT slug, document_html FROM cms_pages')->fetchAll();
    $update = $pdo->prepare('UPDATE cms_pages SET document_html = :html WHERE slug = :slug');
    foreach ($pages as $page) {
        $updated = $replace((string)$page['document_html'], (string)$page['slug']);
        if ($updated !== (string)$page['document_html']) $update->execute(['html' => $updated, 'slug' => $page['slug']]);
    }
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
    $url = preg_replace('~\.html(?=[?#]|$)~i', '', $url) ?? $url;
    return app_url('/' . ltrim($url, '/'));
}

function cms_render_html(string $html, ?array $settings = null, array $allPages = [], array $siteSettings = [], array $socialLinks = [], ?PDO $pdo = null): string
{
    $mediaUrl = app_url('/media.php?key=');
    $html = preg_replace_callback(
        '~(?:https?://[^/"\'\s]+(?:/[A-Za-z0-9._\~-]+)*/|/(?:[A-Za-z0-9._\~-]+/)*)media\.php\?key=(?<absoluteKey>[A-Za-z0-9._-]+)|(?<![A-Za-z0-9/])(?:\.\.?/)*(?:[A-Za-z0-9._\~-]+/)*media\.php\?key=(?<relativeKey>[A-Za-z0-9._-]+)~i',
        static fn(array $match): string => $mediaUrl . rawurlencode((string)($match['absoluteKey'] !== '' ? $match['absoluteKey'] : $match['relativeKey'])),
        $html
    ) ?? $html;

    if ($settings === null) return $html;
    $previousLibxmlSetting = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    $xpath = new DOMXPath($dom);
    $head = $dom->getElementsByTagName('head')->item(0);
    $body = $dom->getElementsByTagName('body')->item(0);
    if ($head) {
        if (!$xpath->query('//link[contains(concat(" ",normalize-space(@rel)," ")," icon ")]')->length) {
            $icon = $dom->createElement('link');
            $icon->setAttribute('rel', 'icon');
            $icon->setAttribute('type', 'image/x-icon');
            $icon->setAttribute('href', app_url('/assets/favicon.ico'));
            $head->appendChild($icon);
        }
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
            $canonicalUrl = $siteBase . ($slug === 'home' ? '/index' : '/' . rawurlencode($slug));
        }
        if ($canonicalUrl !== '') {
            if (!$canonical) { $canonical = $dom->createElement('link'); $canonical->setAttribute('rel', 'canonical'); $head->appendChild($canonical); }
            $canonical->setAttribute('href', $canonicalUrl);
        }
    }
    if ($body) {
        $homeProductImages = [
            'railing' => ['product-railing.webp', 'Aluminium railing installed on a modern terrace'],
            'floor-strips' => ['product-floor-strips.webp', 'Aluminium floor transition strips and edge profiles'],
            'solar' => ['product-solar-sections.webp', 'Aluminium solar panel mounting rails and sections'],
        ];
        foreach ($homeProductImages as $productId => [$imageFile, $imageAlt]) {
            $illustration = $xpath->query('//*[@id="home-product-' . $productId . '"]//*[contains(concat(" ",normalize-space(@class)," ")," product-illustration ")]')->item(0);
            if (!$illustration || !$illustration->parentNode) continue;
            $imageWrap = $dom->createElement('div');
            $imageWrap->setAttribute('class', 'product-image');
            $image = $dom->createElement('img');
            $image->setAttribute('src', app_url('/assets/' . $imageFile));
            $image->setAttribute('alt', $imageAlt);
            $image->setAttribute('loading', 'lazy');
            $image->setAttribute('width', '1200');
            $image->setAttribute('height', '848');
            $imageWrap->appendChild($image);
            $illustration->parentNode->replaceChild($imageWrap, $illustration);
        }
        foreach ($xpath->query('//*[@href or @action]') as $urlNode) {
            $attribute = $urlNode->hasAttribute('href') ? 'href' : 'action';
            $url = trim($urlNode->getAttribute($attribute));
            if ($url !== '' && !preg_match('~^(?:[a-z][a-z0-9+.-]*:|//|#)~i', $url)) {
                $urlNode->setAttribute($attribute, preg_replace('~\.html(?=([?#]|$))~i', '', $url) ?? $url);
            }
        }
        foreach ($xpath->query('//form[@data-inquiry] | //form[@id="quote-form"]') as $inquiryForm) {
            $note = $xpath->query('.//*[contains(concat(" ",normalize-space(@class)," ")," form-note ")]', $inquiryForm)->item(0);
            if ($note) $note->textContent = 'Your submission will be emailed to our team.';
            $submitButton = $xpath->query('.//button[@type="submit"]', $inquiryForm)->item(0);
            if ($submitButton) $submitButton->textContent = 'Send inquiry';
            foreach ($xpath->query('.//a[contains(concat(" ",normalize-space(@class)," ")," email-draft ")]', $inquiryForm) as $draftLink) $draftLink->setAttribute('hidden', 'hidden');
        }
        $slug = (string)($settings['slug'] ?? '');
        if (in_array($slug, ['blogs', 'gallery'], true)) {
            try {
                if ($pdo instanceof PDO) {
                    if ($slug === 'blogs' && isset($_GET['post'])) {
                        $article = $pdo->prepare('SELECT title,image_key,alt_text,article_body FROM cms_content_items WHERE section="blogs" AND article_slug=:slug LIMIT 1');
                        $article->execute(['slug'=>(string)$_GET['post']]);
                        $post = $article->fetch();
                        $main = $xpath->query('//main')->item(0);
                        if ($post && $main) {
                            while ($main->firstChild) $main->removeChild($main->firstChild);
                            $section = $dom->createElement('section'); $section->setAttribute('class','section container blog-article');
                            $back = $dom->createElement('a','← All blogs'); $back->setAttribute('href',app_url('/blogs')); $back->setAttribute('class','text-link'); $section->appendChild($back);
                            $heading = $dom->createElement('h1'); $heading->textContent=(string)$post['title']; $section->appendChild($heading);
                            if ($post['image_key'] !== '') { $image=$dom->createElement('img'); $image->setAttribute('src',app_url('/media.php?key='.rawurlencode((string)$post['image_key']))); $image->setAttribute('alt',(string)$post['alt_text']); $image->setAttribute('loading','eager'); $section->appendChild($image); }
                            foreach (preg_split('/\r\n|\r|\n/', (string)$post['article_body']) ?: [] as $line) { if (trim($line)==='') continue; $paragraph=$dom->createElement('p'); $paragraph->textContent=$line; $section->appendChild($paragraph); }
                            $main->appendChild($section);
                        }
                    }
                    $items = $pdo->prepare('SELECT title,description,image_key,alt_text,article_slug FROM cms_content_items WHERE section=:section ORDER BY sort_order,id');
                    $items->execute(['section'=>$slug]);
                    $gridClass = $slug === 'blogs' ? 'blog-card-grid' : 'gallery-grid';
                    $grid = $xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," ' . $gridClass . ' ")]')->item(0);
                    if ($grid) foreach ($items->fetchAll() as $item) {
                        $card = $dom->createElement($slug === 'blogs' ? 'article' : 'figure');
                        $card->setAttribute('class', $slug === 'blogs' ? 'blog-card reveal' : 'gallery-card reveal');
                        if ($slug === 'blogs' && $item['image_key'] !== '') { $image=$dom->createElement('img'); $image->setAttribute('src',app_url('/media.php?key='.rawurlencode((string)$item['image_key']))); $image->setAttribute('alt',(string)$item['alt_text']); $image->setAttribute('loading','lazy'); $card->appendChild($image); }
                        if ($slug === 'gallery' && $item['image_key'] !== '') {
                            $image = $dom->createElement('img'); $image->setAttribute('src', app_url('/media.php?key=' . rawurlencode($item['image_key']))); $image->setAttribute('alt', (string)$item['alt_text']); $image->setAttribute('loading','lazy'); $card->appendChild($image);
                        }
                        if ($slug === 'blogs') { $eyebrow=$dom->createElement('p','BLOG'); $eyebrow->setAttribute('class','eyebrow'); $card->appendChild($eyebrow); }
                        $title=$dom->createElement('h3'); $title->textContent=(string)$item['title']; $card->appendChild($title);
                        $description=$dom->createElement($slug === 'gallery' ? 'figcaption' : 'p'); $description->textContent=(string)$item['description']; $card->appendChild($description);
                        if ($slug === 'blogs' && $item['article_slug'] !== '') { $link=$dom->createElement('a','Read article →'); $link->setAttribute('class','text-link'); $link->setAttribute('href',app_url('/blogs?post='.rawurlencode((string)$item['article_slug']))); $card->appendChild($link); }
                        $grid->appendChild($card);
                    }
                }
            } catch (Throwable $exception) { error_log('CMS content items unavailable: ' . $exception->getMessage()); }
        }
        $ownerWhatsapp = preg_replace('/\D+/', '', (string)($siteSettings['owner_whatsapp_number'] ?? '')) ?? '';
        $contactEmail = trim((string)($siteSettings['contact_email'] ?? ''));
        $root = $dom->documentElement;
        if ($root instanceof DOMElement) {
            $root->setAttribute('data-owner-whatsapp', $ownerWhatsapp);
            $root->setAttribute('data-contact-email', $contactEmail);
            $root->setAttribute('data-inquiry-endpoint', app_url('/php-backend/submit-inquiry.php'));
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
            if (preg_match('~(?:^|/)([a-z0-9-]+)(?:\.html)?(?:[?#].*)?$~i', $href, $match)) {
                $linkedSlug = strtolower($match[1]) === 'index' ? 'home' : strtolower($match[1]);
                if (isset($hiddenSlugs[$linkedSlug])) $anchor->parentNode?->removeChild($anchor);
            } elseif (preg_match('~^(?:/|#home)$~i', $href) && isset($hiddenSlugs['home'])) {
                $anchor->parentNode?->removeChild($anchor);
            }
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
