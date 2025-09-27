<?php
session_start();
const DATA_FILE = __DIR__ . '/data/data.json';

function read_data(): array {
    $fp = @fopen(DATA_FILE, 'r');
    if (!$fp) {
        return ['config' => [], 'pages' => [], 'media' => [], 'consents' => [], 'requests' => []];
    }
    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : ['config' => [], 'pages' => [], 'media' => [], 'consents' => [], 'requests' => []];
}

function render_template(string $html, array $vars = []): string {
    foreach ($vars as $key => $value) {
        $html = str_replace('{{' . $key . '}}', $value, $html);
    }
    return $html;
}

function page_meta(array $config, array $page): array {
    $meta = $page['meta'] ?? [];
    $globalTitle = $config['seo']['title'] ?? '';
    $title = $meta['title'] ?? $globalTitle;
    $description = $meta['description'] ?? ($config['seo']['description'] ?? '');
    $canonical = ($config['base_url'] ?? '') . ($meta['canonical'] ?? '/');
    return compact('title', 'description', 'canonical');
}

function per_page_css(string $type): string {
    return $type === 'form'
        ? '.lf-shell{max-width:80%;margin:0 auto;padding:32px 0;}'
        : '.lp-shell{max-width:80%;margin:0 auto;}';
}

function schema_org(array $config, array $page, string $url): string {
    $org = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $config['site_name'] ?? 'Liebesblick Fotografie',
        'url' => $config['base_url'] ?? '',
        'logo' => ($config['base_url'] ?? '') . '/assets/images/logo.svg'
    ];
    $service = [
        '@context' => 'https://schema.org',
        '@type' => ($page['type'] ?? '') === 'landing' ? 'Service' : 'Product',
        'name' => $page['title'] ?? 'Fotoshooting',
        'description' => $page['subtitle'] ?? '',
        'provider' => ['@type' => 'Organization', 'name' => $config['site_name'] ?? 'Liebesblick Fotografie']
    ];
    $faqItems = [];
    foreach ($page['faq'] ?? [] as $item) {
        $faqItems[] = [
            '@type' => 'Question',
            'name' => $item['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']]
        ];
    }
    $faq = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $faqItems
    ];
    $breadcrumbs = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Startseite', 'item' => $config['base_url'] ?? ''],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $page['title'] ?? 'Landing', 'item' => $url]
        ]
    ];
    return json_encode([$org, $service, $faq, $breadcrumbs], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function find_page(array $pages, string $type, string $slug = ''): ?array {
    foreach ($pages as $page) {
        if (($page['type'] ?? '') !== $type) {
            continue;
        }
        if ($type === 'landing' && ($page['slug'] ?? '') === $slug && ($page['status'] ?? '') === 'published') {
            return $page;
        }
        if ($type === 'home' && ($page['id'] ?? '') === 'home') {
            return $page;
        }
        if ($type === 'portfolio' && (($page['slug'] ?? '') === $slug || ($page['id'] ?? '') === 'portfolio')) {
            return $page;
        }
    }
    return null;
}

function gallery_markup(array $images): string {
    $items = [];
    foreach ($images as $img) {
        $safe = htmlspecialchars($img, ENT_QUOTES);
        $items[] = '<figure class="lp-gallery-item"><img loading="lazy" class="lp-gallery-image" src="' . $safe . '" alt="Galerie"></figure>';
    }
    return implode("\n", $items);
}

function benefits_markup(array $benefits): string {
    $items = [];
    foreach ($benefits as $benefit) {
        $items[] = '<article class="lp-benefit-card"><h4 class="lp-benefit-title">' . htmlspecialchars($benefit['title'], ENT_QUOTES) . '</h4><p class="lp-benefit-text">' . htmlspecialchars($benefit['description'], ENT_QUOTES) . '</p></article>';
    }
    return implode("\n", $items);
}

function pricing_markup(array $pricing): string {
    $items = [];
    foreach ($pricing as $price) {
        $items[] = '<div class="lp-price-card"><h4 class="lp-price-name">' . htmlspecialchars($price['name'], ENT_QUOTES) . '</h4><p class="lp-price-value">' . htmlspecialchars($price['price'], ENT_QUOTES) . '</p><p>' . htmlspecialchars($price['description'], ENT_QUOTES) . '</p></div>';
    }
    return implode("\n", $items);
}

function faq_markup(array $faq): string {
    $items = [];
    foreach ($faq as $entry) {
        $items[] = '<div class="lp-faq-item"><p class="lp-faq-question">' . htmlspecialchars($entry['q'], ENT_QUOTES) . '</p><p class="lp-faq-answer">' . htmlspecialchars($entry['a'], ENT_QUOTES) . '</p></div>';
    }
    return implode("\n", $items);
}

function legal_page(array $meta, string $content, array $config): void {
    $critical = '.layout-wrap{max-width:80%;margin:0 auto;}';
    $schema = json_encode([[
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $config['site_name'] ?? 'Liebesblick Fotografie',
        'url' => $config['base_url'] ?? ''
    ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $consentVersion = htmlspecialchars($config['legal']['policy_version'] ?? '1', ENT_QUOTES);
    $url = $meta['canonical'];
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . htmlspecialchars($meta['title'], ENT_QUOTES) . '</title>';
    echo '<meta name="description" content="' . htmlspecialchars($meta['description'], ENT_QUOTES) . '">';
    echo '<link rel="canonical" href="' . htmlspecialchars($meta['canonical'], ENT_QUOTES) . '">';
    echo '<meta property="og:title" content="' . htmlspecialchars($meta['title'], ENT_QUOTES) . '">';
    echo '<meta property="og:description" content="' . htmlspecialchars($meta['description'], ENT_QUOTES) . '">';
    echo '<meta property="og:type" content="website">';
    echo '<meta property="og:url" content="' . htmlspecialchars($url, ENT_QUOTES) . '">';
    echo '<meta property="twitter:card" content="summary_large_image">';
    echo '<meta property="twitter:title" content="' . htmlspecialchars($meta['title'], ENT_QUOTES) . '">';
    echo '<meta property="twitter:description" content="' . htmlspecialchars($meta['description'], ENT_QUOTES) . '">';
    echo '<link rel="alternate" hreflang="de-DE" href="' . htmlspecialchars($url, ENT_QUOTES) . '">';
    echo '<style>' . $critical . '</style>';
    echo '<link rel="preload" href="/assets/app.css" as="style">';
    echo '<link rel="stylesheet" href="/assets/app.css" media="print" onload="this.media=\'all\'">';
    echo '<noscript><link rel="stylesheet" href="/assets/app.css"></noscript>';
    echo '<script type="application/ld+json">' . $schema . '</script>';
    echo '</head><body class="layout-body"><div class="layout-wrap"><header class="header-top">';
    echo '<img src="/assets/images/logo.svg" alt="Liebesblick" class="header-logo">';
    echo '<nav class="nav-links"><a class="nav-link" href="/">Startseite</a><a class="nav-link" href="/portfolio">Portfolio</a><a class="nav-link" href="/impressum">Impressum</a><a class="nav-link" href="/datenschutz">Datenschutz</a></nav>';
    echo '</header></div><main class="layout-wrap">' . $content . '</main>';
    echo '<div class="consent-banner" data-version="' . $consentVersion . '"><p>Wir verwenden ausschließlich notwendige Cookies. Mehr erfährst du in unserer <a href="/datenschutz" style="color:#fff;text-decoration:underline;">Datenschutzerklärung</a>.</p><button class="consent-accept">Zustimmen</button></div>';
    echo '<script src="/assets/app.js" defer></script></body></html>';
}

$data = read_data();
$config = $data['config'] ?? [];
$pages = $data['pages'] ?? [];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

if ($uri === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');
    $base = rtrim($config['base_url'] ?? '', '/');
    $urls = [$base . '/', $base . '/portfolio', $base . '/impressum', $base . '/datenschutz'];
    foreach ($pages as $page) {
        if (($page['type'] ?? '') === 'landing' && ($page['status'] ?? '') === 'published') {
            $urls[] = $base . '/l/' . $page['slug'];
            $urls[] = $base . '/form/' . $page['slug'];
        }
    }
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($urls as $url) {
        echo '<url><loc>' . htmlspecialchars($url, ENT_XML1) . '</loc></url>';
    }
    echo '</urlset>';
    exit;
}

if ($uri === '/robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nAllow: /\nSitemap: " . ($config['base_url'] ?? '') . "/sitemap.xml";
    exit;
}

if ($uri === '/impressum') {
    legal_page([
        'title' => 'Impressum | ' . ($config['site_name'] ?? ''),
        'description' => 'Rechtliche Informationen',
        'canonical' => ($config['base_url'] ?? '') . '/impressum'
    ], $config['legal']['impressum'] ?? '', $config);
    exit;
}

if ($uri === '/datenschutz') {
    legal_page([
        'title' => 'Datenschutzerklärung | ' . ($config['site_name'] ?? ''),
        'description' => 'Informationen zum Datenschutz',
        'canonical' => ($config['base_url'] ?? '') . '/datenschutz'
    ], $config['legal']['privacy'] ?? '', $config);
    exit;
}

$type = 'home';
$slug = '';
$page = null;
if ($uri === '/') {
    $page = find_page($pages, 'home');
} elseif ($uri === '/portfolio') {
    $type = 'portfolio';
    $page = find_page($pages, 'portfolio', 'portfolio');
} elseif (preg_match('#^/l/([a-z0-9\-]+)$#', $uri, $matches)) {
    $type = 'landing';
    $slug = $matches[1];
    $page = find_page($pages, 'landing', $slug);
} elseif (preg_match('#^/form/([a-z0-9\-]+)$#', $uri, $matches)) {
    $type = 'form';
    $slug = $matches[1];
    $page = find_page($pages, 'landing', $slug);
} else {
    http_response_code(404);
    legal_page([
        'title' => 'Seite nicht gefunden',
        'description' => '404',
        'canonical' => ($config['base_url'] ?? '') . $uri
    ], '<h1>404</h1><p>Die angeforderte Seite wurde nicht gefunden.</p>', $config);
    exit;
}

if (!$page) {
    http_response_code(404);
    legal_page([
        'title' => 'Seite nicht gefunden',
        'description' => '404',
        'canonical' => ($config['base_url'] ?? '') . $uri
    ], '<h1>404</h1><p>Die angeforderte Seite wurde nicht gefunden.</p>', $config);
    exit;
}

$meta = page_meta($config, $page);
$criticalCss = per_page_css($type);
$url = rtrim($config['base_url'] ?? '', '/') . $uri;
$schema = schema_org($config, $page, $url);
$consentVersion = htmlspecialchars($config['legal']['policy_version'] ?? '1', ENT_QUOTES);

?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($meta['title'], ENT_QUOTES) ?></title>
<meta name="description" content="<?= htmlspecialchars($meta['description'], ENT_QUOTES) ?>">
<link rel="canonical" href="<?= htmlspecialchars($meta['canonical'], ENT_QUOTES) ?>">
<meta property="og:title" content="<?= htmlspecialchars($meta['title'], ENT_QUOTES) ?>">
<meta property="og:description" content="<?= htmlspecialchars($meta['description'], ENT_QUOTES) ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= htmlspecialchars($url, ENT_QUOTES) ?>">
<meta property="twitter:card" content="summary_large_image">
<meta property="twitter:title" content="<?= htmlspecialchars($meta['title'], ENT_QUOTES) ?>">
<meta property="twitter:description" content="<?= htmlspecialchars($meta['description'], ENT_QUOTES) ?>">
<link rel="alternate" hreflang="de-DE" href="<?= htmlspecialchars($url, ENT_QUOTES) ?>">
<style><?= $criticalCss ?></style>
<link rel="preload" href="/assets/app.css" as="style">
<link rel="stylesheet" href="/assets/app.css" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="/assets/app.css"></noscript>
<script type="application/ld+json"><?= $schema ?></script>
</head>
<body class="layout-body">
<div class="layout-wrap">
  <header class="header-top">
    <img src="/assets/images/logo.svg" alt="Liebesblick" class="header-logo">
    <nav class="nav-links">
      <a class="nav-link" href="/">Startseite</a>
      <a class="nav-link" href="/portfolio">Portfolio</a>
      <a class="nav-link" href="/impressum">Impressum</a>
      <a class="nav-link" href="/datenschutz">Datenschutz</a>
    </nav>
  </header>
</div>
<?php
if ($type === 'form') {
    $template = file_get_contents(__DIR__ . '/templates/form.html');
    $successNote = isset($_GET['sent']) ? '<div class="admin-card" style="background:#f6f3f0;margin:24px auto;max-width:80%;padding:16px;">Danke für deine Nachricht!</div>' : '';
    $categories = [];
    foreach ($pages as $p) {
        if (($p['type'] ?? '') === 'landing') {
            $selected = $p['slug'] === $slug ? ' selected' : '';
            $categories[] = '<option value="' . htmlspecialchars($p['title'], ENT_QUOTES) . '"' . $selected . '>' . htmlspecialchars($p['title'], ENT_QUOTES) . '</option>';
        }
    }
    $options = ['Styling Tipps', 'Erinnerungsbox', 'Mini Album', 'Digitale Galerie'];
    $checks = [];
    foreach ($options as $option) {
        $checks[] = '<label class="lf-checkbox"><input type="checkbox" name="options[]" value="' . htmlspecialchars($option, ENT_QUOTES) . '"><span>' . htmlspecialchars($option, ENT_QUOTES) . '</span></label>';
    }
    echo $successNote . render_template($template, [
        'form_action' => '/api.php?action=form.submit&slug=' . urlencode($slug),
        'category_options' => implode('', $categories),
        'option_checks' => implode('', $checks),
        'year' => date('Y')
    ]);
} else {
    $template = file_get_contents(__DIR__ . '/templates/landing.html');
    $gallery = gallery_markup($page['gallery'] ?? []);
    $benefits = benefits_markup($page['benefits'] ?? []);
    $pricing = pricing_markup($page['pricing'] ?? []);
    $faq = faq_markup($page['faq'] ?? []);
    $long = nl2br(htmlspecialchars($page['long_description'] ?? 'Unser Shooting vereint natürliche Momente, warme Farben und liebevolle Details.', ENT_QUOTES));
    echo render_template($template, [
        'hero_image' => htmlspecialchars($page['hero_image'] ?? '/assets/images/landing-hero.jpg', ENT_QUOTES),
        'hero_badge' => 'Liebesblick Fotografie',
        'title' => htmlspecialchars($page['title'] ?? '', ENT_QUOTES),
        'subtitle' => htmlspecialchars($page['subtitle'] ?? '', ENT_QUOTES),
        'hero_text' => htmlspecialchars($page['intro'] ?? 'Festliche Mini-Shootings mit viel Herz.', ENT_QUOTES),
        'gallery_items' => $gallery,
        'benefit_items' => $benefits,
        'long_description' => $long,
        'pricing_items' => $pricing,
        'faq_items' => $faq,
        'form_url' => '/form/' . htmlspecialchars($page['slug'] ?? '', ENT_QUOTES)
    ]);
}
?>
<div class="consent-banner" data-version="<?= $consentVersion ?>">
  <p>Wir verwenden ausschließlich notwendige Cookies. Mehr erfährst du in unserer <a href="/datenschutz" style="color:#fff;text-decoration:underline;">Datenschutzerklärung</a>.</p>
  <button class="consent-accept">Zustimmen</button>
</div>
<script src="/assets/app.js" defer></script>
</body>
</html>
