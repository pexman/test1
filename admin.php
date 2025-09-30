<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

session_start();

if (!isset($_SESSION['admin_last_activity'])) {
    $_SESSION['admin_last_activity'] = time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if (!rate_limit('admin_login_' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 5, 300)) {
        secure_log('Rate limit exceeded for admin login');
        $error = 'Zu viele Versuche. Bitte später erneut versuchen.';
    } elseif (password_verify($_POST['password'], ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_last_activity'] = time();
        secure_log('Admin login successful');
        header('Location: admin.php');
        exit;
    } else {
        secure_log('Admin login failed');
        $error = 'Passwort ungültig';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Studio Lumière – Admin Login</title>
    <style>
        body {font-family: 'Montserrat', sans-serif; background: linear-gradient(135deg,#EEEDED,#B8A99A); display:flex; align-items:center; justify-content:center; height:100vh; margin:0;}
        .login-card {background:#fff; padding:3rem; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,0.15); max-width:400px; width:100%; text-align:center;}
        h1 {margin-bottom:1.5rem; color:#7A6F64; font-weight:600;}
        input {width:100%; padding:0.85rem 1rem; border:1px solid #D8CEC3; border-radius:12px; font-size:1rem;}
        button {margin-top:1.5rem; padding:0.9rem 1rem; width:100%; border:none; border-radius:12px; background:#7A6F64; color:#fff; font-size:1rem; cursor:pointer; transition:transform .2s, box-shadow .2s;}
        button:hover {transform:translateY(-2px); box-shadow:0 10px 20px rgba(122,111,100,0.25);} 
        .error {color:#c0392b; margin-top:1rem;}
    </style>
</head>
<body>
<div class="login-card">
    <h1>Admin Login</h1>
    <form method="post">
        <input type="password" name="password" placeholder="Passwort" required />
        <button type="submit">Anmelden</button>
        <?php if (!empty($error)): ?>
            <div class="error"><?= sanitize_text($error ?? '') ?></div>
        <?php endif; ?>
    </form>
</div>
</body>
</html>
<?php
    exit;
}

$content = read_json(CONTENT_FILE);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Studio Lumière – CMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Allura&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <style>
        :root {--color-dark:#7A6F64;--color-medium:#B8A99A;--color-light:#D8CEC3;--color-lighter:#EEEDED;}
        * {box-sizing:border-box;}
        body {font-family:'Montserrat',sans-serif;margin:0;background:linear-gradient(135deg,#EEEDED,#D8CEC3);color:#3b3128;}
        header {display:flex;justify-content:space-between;align-items:center;padding:1.5rem 3vw;background:#fff;box-shadow:0 12px 40px rgba(122,111,100,.12);position:sticky;top:0;z-index:10;}
        header h1 {font-size:1.5rem;margin:0;}
        header a {color:#7A6F64;text-decoration:none;font-weight:600;}
        .tabs {display:flex;gap:.75rem;padding:1.5rem 3vw;background:transparent;position:sticky;top:86px;z-index:9;}
        .tabs button {padding:.8rem 1.2rem;border:none;border-radius:14px;background:#fff;color:#7A6F64;font-weight:600;cursor:pointer;box-shadow:0 12px 40px rgba(122,111,100,.15);transition:all .25s ease;}
        .tabs button.active {background:#7A6F64;color:#fff;transform:translateY(-1px);}
        main {padding:2rem 3vw 4rem;}
        .tab-section {display:none;background:#fff;padding:2rem;border-radius:20px;box-shadow:0 25px 70px rgba(122,111,100,.15);margin-bottom:2rem;}
        .tab-section.active {display:block;}
        h2 {margin-top:0;font-size:1.3rem;color:#7A6F64;}
        label {display:block;font-weight:600;margin:1.2rem 0 .5rem;}
        input[type=text],input[type=url],textarea,input[type=color] {width:100%;padding:.8rem 1rem;border:1px solid #D8CEC3;border-radius:12px;font-size:1rem;background:#fdfdfd;box-shadow:inset 0 1px 2px rgba(0,0,0,.04);} 
        textarea {min-height:120px;resize:vertical;}
        .nav-row {display:grid;grid-template-columns:1fr 1fr auto;gap:.75rem;margin-bottom:.75rem;align-items:center;}
        .nav-row button.remove {background:#fbeaea;color:#c0392b;border:none;border-radius:12px;padding:.6rem 1rem;font-weight:600;cursor:pointer;}
        button.primary {background:#7A6F64;color:#fff;border:none;padding:.85rem 1.4rem;border-radius:14px;font-size:1rem;font-weight:600;cursor:pointer;box-shadow:0 15px 40px rgba(122,111,100,.25);}
        button.secondary {background:#EEEDED;color:#7A6F64;border:none;padding:.6rem 1.4rem;border-radius:14px;font-weight:600;cursor:pointer;}
        #upload-dropzone {margin-top:1rem;padding:2rem;border:2px dashed #B8A99A;border-radius:16px;text-align:center;background:#faf7f4;transition:all .3s ease;}
        #upload-dropzone.dragover {background:#fff;box-shadow:0 15px 30px rgba(122,111,100,.2);} 
        .gallery-block {border:1px solid #EEEDED;border-radius:16px;padding:1.5rem;margin-bottom:1.5rem;background:#fafafa;}
        .gallery-card {display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem;margin-bottom:1rem;padding:1rem;border-radius:12px;background:#fff;box-shadow:0 10px 30px rgba(122,111,100,.08);}
        .gallery-card textarea {min-height:80px;}
        .gallery-card .checkbox {display:flex;align-items:center;gap:.5rem;font-weight:500;}
        #toast {position:fixed;bottom:1.5rem;right:1.5rem;background:#7A6F64;color:#fff;padding:1rem 1.5rem;border-radius:12px;opacity:0;transform:translateY(20px);transition:all .3s ease;pointer-events:none;}
        #toast.visible {opacity:1;transform:translateY(0);} 
        #toast.error {background:#c0392b;}
        @media (max-width:900px){.nav-row{grid-template-columns:1fr;}.tabs{flex-wrap:wrap;}}
    </style>
</head>
<body>
<header>
    <h1>Studio Lumière – Content Management</h1>
    <a href="?logout=1">Logout</a>
</header>
<div class="tabs">
    <button data-tab="tab-general">Allgemein</button>
    <button data-tab="tab-seo">SEO</button>
    <button data-tab="tab-images">Bilder</button>
    <button data-tab="tab-gallery">Galerien</button>
    <button data-tab="tab-buttons">Buttons & Navigation</button>
</div>
<main>
    <section class="tab-section" id="tab-general">
        <h2>Allgemeine Inhalte</h2>
        <form>
            <label>Hero Titel</label>
            <input type="text" data-json-field="general.hero_title" name="general[hero_title]" value="<?= sanitize_text($content['general']['hero_title'] ?? '') ?>" />
            <label>Hero Untertitel</label>
            <textarea data-json-field="general.hero_subtitle" name="general[hero_subtitle]"><?= sanitize_text($content['general']['hero_subtitle'] ?? '') ?></textarea>
            <label>Über uns Text</label>
            <textarea data-json-field="general.about_text" name="general[about_text]"><?= sanitize_text($content['general']['about_text'] ?? '') ?></textarea>
            <label>Quote</label>
            <textarea data-json-field="general.quote_text" name="general[quote_text]"><?= sanitize_text($content['general']['quote_text'] ?? '') ?></textarea>
            <label>Info Text</label>
            <textarea data-json-field="general.info_text" name="general[info_text]"><?= sanitize_text($content['general']['info_text'] ?? '') ?></textarea>
            <label>Pricing Text</label>
            <textarea data-json-field="general.pricing_text" name="general[pricing_text]"><?= sanitize_text($content['general']['pricing_text'] ?? '') ?></textarea>
            <label>Hero Höhe</label>
            <input type="text" data-json-field="visual.hero_height" name="visual[hero_height]" value="<?= sanitize_text($content['visual']['hero_height'] ?? '80vh') ?>" />
            <label>Quote Höhe</label>
            <input type="text" data-json-field="visual.quote_height" name="visual[quote_height]" value="<?= sanitize_text($content['visual']['quote_height'] ?? '60vh') ?>" />
            <label>Textfarbe</label>
            <input type="color" data-json-field="visual.text_color" name="visual[text_color]" value="<?= sanitize_text($content['visual']['text_color'] ?? '#3b3128') ?>" />
            <label>Akzentfarbe</label>
            <input type="color" data-json-field="visual.accent_color" name="visual[accent_color]" value="<?= sanitize_text($content['visual']['accent_color'] ?? '#7A6F64') ?>" />
            <button data-save class="primary">Speichern</button>
        </form>
    </section>

    <section class="tab-section" id="tab-seo">
        <h2>SEO & Open Graph</h2>
        <form>
            <label>Meta Titel</label>
            <input type="text" data-json-field="seo.meta_title" name="seo[meta_title]" value="<?= sanitize_text($content['seo']['meta_title'] ?? '') ?>" />
            <label>Meta Beschreibung</label>
            <textarea data-json-field="seo.meta_description" name="seo[meta_description]"><?= sanitize_text($content['seo']['meta_description'] ?? '') ?></textarea>
            <label>Meta Keywords</label>
            <textarea data-json-field="seo.meta_keywords" name="seo[meta_keywords]"><?= sanitize_text($content['seo']['meta_keywords'] ?? '') ?></textarea>
            <label>OG Titel</label>
            <input type="text" data-json-field="seo.og_title" name="seo[og_title]" value="<?= sanitize_text($content['seo']['og_title'] ?? '') ?>" />
            <label>OG Beschreibung</label>
            <textarea data-json-field="seo.og_description" name="seo[og_description]"><?= sanitize_text($content['seo']['og_description'] ?? '') ?></textarea>
            <label>OG Bild</label>
            <input type="text" data-json-field="seo.og_image" name="seo[og_image]" value="<?= sanitize_text($content['seo']['og_image'] ?? '') ?>" />
            <button data-save class="primary">Speichern</button>
        </form>
    </section>

    <section class="tab-section" id="tab-images">
        <h2>Bildverwaltung</h2>
        <form>
            <label>Logo</label>
            <input type="text" data-json-field="images.logo" name="images[logo]" value="<?= sanitize_text($content['images']['logo'] ?? '') ?>" />
            <label>Hero Hintergrund</label>
            <input type="text" data-json-field="images.hero_background" name="images[hero_background]" value="<?= sanitize_text($content['images']['hero_background'] ?? '') ?>" />
            <label>Quote Hintergrund</label>
            <input type="text" data-json-field="images.quote_background" name="images[quote_background]" value="<?= sanitize_text($content['images']['quote_background'] ?? '') ?>" />
            <label>Info Hintergrund</label>
            <input type="text" data-json-field="images.info_background" name="images[info_background]" value="<?= sanitize_text($content['images']['info_background'] ?? '') ?>" />
            <label>Pricing Hintergrund</label>
            <input type="text" data-json-field="images.pricing_background" name="images[pricing_background]" value="<?= sanitize_text($content['images']['pricing_background'] ?? '') ?>" />
            <label>Familien Hintergrund</label>
            <input type="text" data-json-field="images.family_background" name="images[family_background]" value="<?= sanitize_text($content['images']['family_background'] ?? '') ?>" />
            <div id="upload-dropzone">Dateien hierher ziehen oder klicken um zu wählen
                <input type="file" name="files[]" multiple style="display:none" />
            </div>
            <ul id="upload-list"></ul>
            <button data-save class="primary">Speichern</button>
        </form>
    </section>

    <section class="tab-section" id="tab-gallery">
        <h2>Galerien</h2>
        <div id="galleries-container"></div>
        <button data-save class="primary" style="margin-top:1.5rem;">Galerien speichern</button>
    </section>

    <section class="tab-section" id="tab-buttons">
        <h2>Navigation & Buttons</h2>
        <form>
            <h3>Navigationspunkte</h3>
            <div id="navigation-list"></div>
            <button type="button" id="add-navigation" class="secondary">Link hinzufügen</button>
            <h3 style="margin-top:2rem;">Buttons</h3>
            <div id="button-list"></div>
            <button type="button" id="add-button" class="secondary">Button hinzufügen</button>
            <button data-save class="primary" style="margin-top:1.5rem;">Speichern</button>
        </form>
    </section>
</main>
<div id="toast"></div>
<script src="admin.js" defer></script>
</body>
</html>
