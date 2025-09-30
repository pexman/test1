<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
$content = read_json(CONTENT_FILE);
$general = $content['general'] ?? [];
$seo = $content['seo'] ?? [];
$images = $content['images'] ?? [];
$nav = $content['navigation'] ?? [];
$buttons = $content['buttons'] ?? [];
$visual = $content['visual'] ?? [];
$galleries = $content['galleries'] ?? [];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= sanitize_text($seo['meta_title'] ?? 'Studio Lumière – Fotografie') ?></title>
    <meta name="description" content="<?= sanitize_text($seo['meta_description'] ?? '') ?>" />
    <meta name="keywords" content="<?= sanitize_text($seo['meta_keywords'] ?? '') ?>" />
    <meta property="og:title" content="<?= sanitize_text($seo['og_title'] ?? '') ?>" />
    <meta property="og:description" content="<?= sanitize_text($seo['og_description'] ?? '') ?>" />
    <meta property="og:image" content="<?= sanitize_text($seo['og_image'] ?? '') ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Allura&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <style>
        :root {--color-dark:#7A6F64;--color-medium:#B8A99A;--color-light:#D8CEC3;--color-lighter:#EEEDED;}
        * {box-sizing:border-box;}
        body {margin:0;font-family:'Montserrat',sans-serif;background:#fdfaf7;color:<?= sanitize_text($visual['text_color'] ?? '#3b3128') ?>;}
        header {position:sticky;top:0;z-index:20;background:rgba(255,255,255,0.9);backdrop-filter:blur(12px);padding:1.2rem 6vw;display:flex;align-items:center;justify-content:space-between;box-shadow:0 12px 40px rgba(122,111,100,.12);}
        nav ul {list-style:none;margin:0;padding:0;display:flex;gap:1.5rem;}
        nav a {text-decoration:none;color:#7A6F64;font-weight:600;position:relative;}
        nav a::after {content:'';position:absolute;left:0;bottom:-6px;width:100%;height:2px;background:linear-gradient(90deg,#7A6F64,#B8A99A);transform:scaleX(0);transform-origin:right;transition:transform .3s ease;}
        nav a:hover::after {transform:scaleX(1);transform-origin:left;}
        .hero {position:relative;min-height:<?= sanitize_text($visual['hero_height'] ?? '80vh') ?>;display:flex;align-items:center;justify-content:center;text-align:center;color:#fff;overflow:hidden;}
        .hero::before {content:'';position:absolute;inset:0;background:url('<?= sanitize_text($images['hero_background'] ?? '') ?>') center/cover no-repeat;filter:brightness(.65);z-index:-2;}
        .hero::after {content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(122,111,100,.45),rgba(216,206,195,.35));z-index:-1;}
        .hero-content {max-width:900px;padding:4rem 6vw;}
        .hero h1 {font-size:clamp(2.8rem,5vw,4rem);margin:0;font-weight:600;}
        .hero p {font-size:clamp(1.15rem,2.4vw,1.4rem);margin:1rem 0 2rem;line-height:1.6;}
        .btn-group {display:flex;gap:1rem;flex-wrap:wrap;justify-content:center;}
        .btn {padding:.85rem 1.8rem;border-radius:999px;border:none;font-weight:600;font-size:1rem;cursor:pointer;transition:transform .2s, box-shadow .2s;text-decoration:none;}
        .btn-primary {background:#7A6F64;color:#fff;box-shadow:0 18px 40px rgba(122,111,100,.32);}
        .btn-secondary {background:rgba(255,255,255,0.85);color:#7A6F64;box-shadow:0 12px 30px rgba(122,111,100,.22);}
        .section {padding:5rem 6vw;}
        .grid {display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:2.5rem;align-items:center;}
        .card {background:#fff;border-radius:24px;padding:2.5rem;box-shadow:0 20px 60px rgba(122,111,100,.15);} 
        .quote {min-height:<?= sanitize_text($visual['quote_height'] ?? '60vh') ?>;display:flex;align-items:center;justify-content:center;text-align:center;position:relative;color:#fff;}
        .quote::before {content:'';position:absolute;inset:0;background:url('<?= sanitize_text($images['quote_background'] ?? '') ?>') center/cover no-repeat;filter:brightness(.55);z-index:-2;}
        .quote::after {content:'';position:absolute;inset:0;background:linear-gradient(120deg,rgba(122,111,100,.6),rgba(184,169,154,.45));z-index:-1;}
        .quote p {font-family:'Allura',cursive;font-size:clamp(2.2rem,4vw,3.2rem);max-width:700px;margin:0;}
        .gallery {display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.5rem;margin-top:3rem;}
        .gallery figure {margin:0;border-radius:20px;overflow:hidden;box-shadow:0 18px 50px rgba(122,111,100,.18);background:#fff;}
        .gallery img {width:100%;height:260px;object-fit:cover;display:block;transition:transform .4s ease;}
        .gallery figcaption {padding:1rem;font-size:.95rem;font-weight:500;color:#7A6F64;}
        .gallery figure:hover img {transform:scale(1.05);}
        footer {background:#7A6F64;color:#fff;padding:3rem 6vw;display:grid;gap:1rem;text-align:center;}
        .contact {background:url('<?= sanitize_text($images['info_background'] ?? '') ?>') center/cover;border-radius:30px;padding:4rem;color:#fff;position:relative;overflow:hidden;}
        .contact::after {content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(122,111,100,.9),rgba(122,111,100,.6));z-index:0;}
        .contact-content {position:relative;z-index:1;max-width:600px;margin:0 auto;text-align:center;}
        .pricing {background:url('<?= sanitize_text($images['pricing_background'] ?? '') ?>') center/cover;border-radius:30px;padding:4rem;color:#fff;position:relative;overflow:hidden;}
        .pricing::after {content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(122,111,100,.9),rgba(184,169,154,.65));z-index:0;}
        .pricing-content {position:relative;z-index:1;max-width:650px;margin:0 auto;text-align:center;}
        .family {background:url('<?= sanitize_text($images['family_background'] ?? '') ?>') center/cover;border-radius:30px;padding:4rem;color:#fff;position:relative;overflow:hidden;}
        .family::after {content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(122,111,100,.85),rgba(216,206,195,.55));z-index:0;}
        .family-content {position:relative;z-index:1;max-width:650px;margin:0 auto;text-align:center;}
        .section-title {font-size:2.5rem;margin-bottom:1rem;color:#7A6F64;}
        @media (max-width:768px) {
            header {flex-direction:column;gap:1rem;}
            nav ul {flex-wrap:wrap;justify-content:center;}
            .hero {min-height:70vh;}
            .section {padding:4rem 6vw;}
        }
    </style>
</head>
<body>
<header id="home">
    <div class="logo" style="display:flex;align-items:center;gap:1rem;">
        <?php if (!empty($images['logo'])): ?>
            <img src="<?= sanitize_text($images['logo']) ?>" alt="Studio Logo" style="height:48px;width:auto;" />
        <?php endif; ?>
        <span style="font-weight:700;font-size:1.25rem;color:#7A6F64;">Studio Lumière</span>
    </div>
    <nav>
        <ul>
            <?php foreach ($nav as $item): if (empty($item['label']) || empty($item['url'])) continue; ?>
                <li><a href="<?= sanitize_text($item['url']) ?>"><?= sanitize_text($item['label']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </nav>
</header>
<section class="hero">
    <div class="hero-content">
        <h1><?= sanitize_text($general['hero_title'] ?? 'Emotionale Fotografie in Berlin') ?></h1>
        <p><?= sanitize_text($general['hero_subtitle'] ?? '') ?></p>
        <div class="btn-group">
            <?php foreach ($buttons as $index => $button): if (empty($button['label']) || empty($button['url'])) continue; ?>
                <a class="btn <?= $index === 0 ? 'btn-primary' : 'btn-secondary' ?>" href="<?= sanitize_text($button['url']) ?>"><?= sanitize_text($button['label']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<section class="section" id="portfolio">
    <div class="grid">
        <div class="card">
            <h2 class="section-title">Unsere Philosophie</h2>
            <div><?= $general['about_text'] ?? '' ?></div>
        </div>
        <div class="card" style="background:linear-gradient(160deg,rgba(122,111,100,.85),rgba(184,169,154,.85));color:#fff;">
            <h2 style="font-size:2rem;font-family:'Allura',cursive;margin-bottom:1rem;">Momente voller Gefühl</h2>
            <p style="line-height:1.7;font-size:1.1rem;"><?= sanitize_text($general['info_text'] ?? '') ?></p>
            <a class="btn btn-secondary" style="background:#fff;color:#7A6F64;margin-top:1.5rem;display:inline-block;" href="booking/index.php">Shooting anfragen</a>
        </div>
    </div>
    <?php if (!empty($galleries['portfolio'])): ?>
        <div class="gallery" id="gallery">
            <?php foreach ($galleries['portfolio'] as $image): if (($image['visible'] ?? false) !== true) continue; ?>
                <figure>
                    <img src="<?= sanitize_text($image['src']) ?>" alt="<?= sanitize_text($image['description'] ?? 'Portfolio Bild') ?>" />
                    <figcaption><?= sanitize_text($image['description'] ?? '') ?></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<section class="quote">
    <p><?= sanitize_text($general['quote_text'] ?? 'Jeder Moment verdient es, bewahrt zu werden.') ?></p>
</section>
<section class="section">
    <div class="contact">
        <div class="contact-content">
            <h2 class="section-title" style="color:#fff;">Informationen</h2>
            <p style="line-height:1.8;"><?= sanitize_text($general['info_text'] ?? '') ?></p>
            <a class="btn btn-primary" href="booking/index.php">Jetzt Termin reservieren</a>
        </div>
    </div>
</section>
<section class="section" id="pricing">
    <div class="pricing">
        <div class="pricing-content">
            <h2 class="section-title" style="color:#fff;">Preise & Pakete</h2>
            <p style="line-height:1.8;font-size:1.1rem;"><?= sanitize_text($general['pricing_text'] ?? '') ?></p>
            <div class="btn-group" style="justify-content:center;margin-top:2rem;">
                <a class="btn btn-secondary" href="booking/index.php">Verfügbarkeit prüfen</a>
            </div>
        </div>
    </div>
</section>
<section class="section" id="family">
    <div class="family">
        <div class="family-content">
            <h2 class="section-title" style="color:#fff;">Familienmomente</h2>
            <p style="line-height:1.8;">Wir begleiten euch mit Ruhe und Empathie, damit natürliche Bilder entstehen.</p>
        </div>
    </div>
    <?php if (!empty($galleries['family'])): ?>
        <div class="gallery" style="margin-top:3rem;">
            <?php foreach ($galleries['family'] as $image): if (($image['visible'] ?? false) !== true) continue; ?>
                <figure>
                    <img src="<?= sanitize_text($image['src']) ?>" alt="<?= sanitize_text($image['description'] ?? 'Familie') ?>" />
                    <figcaption><?= sanitize_text($image['description'] ?? '') ?></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<footer id="contact">
    <p>Studio Lumière · Karl-Marx-Allee 12 · 10243 Berlin</p>
    <p><a href="mailto:hallo@studio-lumiere.de" style="color:#fff;text-decoration:none;font-weight:600;">hallo@studio-lumiere.de</a> · <a href="booking/index.php" style="color:#fff;text-decoration:none;">Jetzt Termin buchen</a></p>
    <p><a href="datenschutz.html" style="color:#fff;text-decoration:none;">Datenschutz</a></p>
</footer>
</body>
</html>
