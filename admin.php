<?php
session_start();
const DATA_FILE = __DIR__ . '/data/data.json';

define('RATE_LIMIT_WINDOW', 60);
define('RATE_LIMIT_MAX', 30);

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

function write_data(array $data): void {
    $fp = fopen(DATA_FILE, 'c+');
    if (!$fp) {
        throw new RuntimeException('Datenbank nicht schreibbar');
    }
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

function ensure_rate_limit(): void {
    $now = time();
    $window = $_SESSION['rate_window'] ?? $now;
    $count = $_SESSION['rate_count'] ?? 0;
    if ($now - $window > RATE_LIMIT_WINDOW) {
        $_SESSION['rate_window'] = $now;
        $_SESSION['rate_count'] = 1;
        return;
    }
    if ($count >= RATE_LIMIT_MAX) {
        http_response_code(429);
        exit('Zu viele Anfragen.');
    }
    $_SESSION['rate_count'] = $count + 1;
}

$data = read_data();
$config = $data['config'] ?? [];
ensure_rate_limit();

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function require_login(array $config): void {
    if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
        header('Location: /admin.php?login=1');
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    if ($email && $password && $config['admin_user']['email'] === $email && password_verify($password, $config['admin_user']['password_hash'] ?? '')) {
        $_SESSION['admin'] = true;
        header('Location: /admin.php');
        exit;
    }
    $error = 'Ungültige Zugangsdaten';
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin.php?login=1');
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'save_config') {
    require_login($config);
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        exit('Ungültiges Token');
    }
    $config['site_name'] = htmlspecialchars(trim($_POST['site_name'] ?? ''), ENT_QUOTES);
    $config['seo']['title'] = htmlspecialchars(trim($_POST['seo_title'] ?? ''), ENT_QUOTES);
    $config['seo']['description'] = htmlspecialchars(trim($_POST['seo_description'] ?? ''), ENT_QUOTES);
    $config['openai']['api_key'] = trim($_POST['openai_key'] ?? '');
    $config['openai']['model'] = 'gpt-4o-mini';
    $config['openai']['temperature'] = (float)($_POST['openai_temperature'] ?? 0.7);
    $config['openai']['prompt_template'] = trim($_POST['openai_prompt'] ?? $config['openai']['prompt_template']);
    $data['config'] = $config;
    write_data($data);
    header('Location: /admin.php?saved=1');
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'save_page') {
    require_login($config);
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        exit('Ungültiges Token');
    }
    $pageId = $_POST['page_id'] ?? bin2hex(random_bytes(6));
    $isNew = true;
    foreach ($data['pages'] as &$page) {
        if (($page['id'] ?? '') === $pageId) {
            $isNew = false;
            $pageRef =& $page;
            break;
        }
    }
    if (!isset($pageRef)) {
        $pageRef = [];
        $data['pages'][] =& $pageRef;
    $pageRef = &$data['pages'][array_key_last($data['pages'])];
    }
    $pageRef['id'] = $pageId;
    $pageRef['type'] = $_POST['page_type'] ?? 'landing';
    $pageRef['slug'] = preg_replace('/[^a-z0-9\-]/', '', strtolower($_POST['slug'] ?? ''));
    $pageRef['title'] = trim($_POST['title'] ?? '');
    $pageRef['subtitle'] = trim($_POST['subtitle'] ?? '');
    $pageRef['intro'] = trim($_POST['intro'] ?? '');
    $pageRef['long_description'] = trim($_POST['long_description'] ?? '');
    $pageRef['hero_image'] = trim($_POST['hero_image'] ?? '');
    $pageRef['status'] = in_array($_POST['status'] ?? 'draft', ['draft', 'published'], true) ? $_POST['status'] : 'draft';
    $pageRef['meta'] = [
        'title' => trim($_POST['meta_title'] ?? ''),
        'description' => trim($_POST['meta_description'] ?? ''),
        'canonical' => trim($_POST['meta_canonical'] ?? '')
    ];
    $pageRef['benefits'] = json_decode($_POST['benefits'] ?? '[]', true) ?: [];
    $pageRef['pricing'] = json_decode($_POST['pricing'] ?? '[]', true) ?: [];
    $pageRef['faq'] = json_decode($_POST['faq'] ?? '[]', true) ?: [];
    $pageRef['gallery'] = json_decode($_POST['gallery'] ?? '[]', true) ?: [];
    write_data($data);
    unset($pageRef);
    header('Location: /admin.php?page=' . urlencode($pageId) . '&saved=1');
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'delete_page') {
    require_login($config);
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        exit('Ungültiges Token');
    }
    $pageId = $_POST['page_id'] ?? '';
    $data['pages'] = array_values(array_filter($data['pages'], fn($p) => ($p['id'] ?? '') !== $pageId));
    write_data($data);
    header('Location: /admin.php?deleted=1');
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'duplicate_page') {
    require_login($config);
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        exit('Ungültiges Token');
    }
    $pageId = $_POST['page_id'] ?? '';
    foreach ($data['pages'] as $page) {
        if (($page['id'] ?? '') === $pageId) {
            $copy = $page;
            $copy['id'] = bin2hex(random_bytes(6));
            $copy['slug'] = $copy['slug'] . '-copy';
            $copy['status'] = 'draft';
            $data['pages'][] = $copy;
            write_data($data);
            header('Location: /admin.php?page=' . urlencode($copy['id']));
            exit;
        }
    }
    header('Location: /admin.php?error=notfound');
    exit;
}

$loggedIn = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

function page_count(array $pages, string $type): int {
    return count(array_filter($pages, fn($p) => ($p['type'] ?? '') === $type));
}

if (!$loggedIn) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Admin Login</title>
        <link rel="stylesheet" href="/assets/app.css">
    </head>
    <body class="layout-body">
    <div class="admin-shell">
        <div class="admin-card">
            <h1 class="admin-title">Login</h1>
            <?php if (!empty($error)): ?><p style="color:#b50808;"><?= htmlspecialchars($error, ENT_QUOTES) ?></p><?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="login">
                <div class="lf-field-row">
                    <label class="lf-label">E-Mail</label>
                    <input class="lf-input" type="email" name="email" required>
                </div>
                <div class="lf-field-row">
                    <label class="lf-label">Passwort</label>
                    <input class="lf-input" type="password" name="password" required>
                </div>
                <button class="admin-button" type="submit">Anmelden</button>
            </form>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

$selectedPageId = $_GET['page'] ?? '';
$selectedPage = null;
foreach ($data['pages'] as $page) {
    if (($page['id'] ?? '') === $selectedPageId) {
        $selectedPage = $page;
        break;
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/app.js" defer></script>
</head>
<body class="layout-body">
<div class="admin-shell">
    <div class="admin-card">
        <h1 class="admin-title">Dashboard</h1>
        <div class="admin-grid">
            <div class="admin-stat">Landings: <?= page_count($data['pages'], 'landing') ?></div>
            <div class="admin-stat">Portfolio: <?= page_count($data['pages'], 'portfolio') ?></div>
            <div class="admin-stat">Consents: <?= count($data['consents'] ?? []) ?></div>
        </div>
        <p><a class="admin-button" href="/admin.php?logout=1">Logout</a></p>
    </div>

    <div class="admin-card">
        <h2 class="admin-title">Seiten verwalten</h2>
        <table class="admin-table">
            <thead>
            <tr><th>Titel</th><th>Slug</th><th>Status</th><th>Aktionen</th></tr>
            </thead>
            <tbody>
            <?php foreach ($data['pages'] as $page): ?>
                <tr>
                    <td><?= htmlspecialchars($page['title'] ?? '', ENT_QUOTES) ?></td>
                    <td><?= htmlspecialchars($page['slug'] ?? '', ENT_QUOTES) ?></td>
                    <td><?= htmlspecialchars($page['status'] ?? 'draft', ENT_QUOTES) ?></td>
                    <td>
                        <a class="admin-button" href="/admin.php?page=<?= urlencode($page['id'] ?? '') ?>">Bearbeiten</a>
                        <form method="post" style="display:inline-block" onsubmit="return confirm('Wirklich löschen?');">
                            <input type="hidden" name="action" value="delete_page">
                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                            <input type="hidden" name="page_id" value="<?= htmlspecialchars($page['id'] ?? '', ENT_QUOTES) ?>">
                            <button class="admin-button" type="submit">Löschen</button>
                        </form>
                        <form method="post" style="display:inline-block">
                            <input type="hidden" name="action" value="duplicate_page">
                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                            <input type="hidden" name="page_id" value="<?= htmlspecialchars($page['id'] ?? '', ENT_QUOTES) ?>">
                            <button class="admin-button" type="submit">Duplizieren</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="admin-card">
        <h2 class="admin-title">Seite bearbeiten</h2>
        <form method="post">
            <input type="hidden" name="action" value="save_page">
            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
            <input type="hidden" name="page_id" value="<?= htmlspecialchars($selectedPage['id'] ?? '', ENT_QUOTES) ?>">
            <div class="lf-field-row">
                <label class="lf-label">Typ</label>
                <select class="lf-input" name="page_type">
                    <?php $types = ['landing' => 'Landing', 'home' => 'Home', 'portfolio' => 'Portfolio'];
                    foreach ($types as $key => $label):
                        $sel = (($selectedPage['type'] ?? 'landing') === $key) ? ' selected' : '';
                        echo '<option value="' . $key . '"' . $sel . '>' . $label . '</option>';
                    endforeach; ?>
                </select>
            </div>
            <div class="lf-field-row"><label class="lf-label">Slug</label><input class="lf-input" name="slug" value="<?= htmlspecialchars($selectedPage['slug'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="lf-field-row"><label class="lf-label">Titel</label><input class="lf-input" name="title" value="<?= htmlspecialchars($selectedPage['title'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="lf-field-row"><label class="lf-label">Untertitel</label><input class="lf-input" name="subtitle" value="<?= htmlspecialchars($selectedPage['subtitle'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="lf-field-row"><label class="lf-label">Intro</label><textarea class="lf-textarea" id="intro" name="intro"><?= htmlspecialchars($selectedPage['intro'] ?? '', ENT_QUOTES) ?></textarea></div>
            <button class="admin-button" type="button" data-ai-generate data-ai-target="intro" data-ai-type="intro">Generieren mit IA</button>
            <div class="lf-field-row"><label class="lf-label">Beschreibung</label><textarea class="lf-textarea" id="long_description" name="long_description"><?= htmlspecialchars($selectedPage['long_description'] ?? '', ENT_QUOTES) ?></textarea></div>
            <button class="admin-button" type="button" data-ai-generate data-ai-target="long_description" data-ai-type="beschreibung">Generieren mit IA</button>
            <div class="lf-field-row"><label class="lf-label">Hero Bild</label><input class="lf-input" name="hero_image" value="<?= htmlspecialchars($selectedPage['hero_image'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="lf-field-row"><label class="lf-label">Status</label>
                <select class="lf-input" name="status">
                    <?php foreach (['draft' => 'Entwurf', 'published' => 'Veröffentlicht'] as $key => $label):
                        $sel = (($selectedPage['status'] ?? 'draft') === $key) ? ' selected' : '';
                        echo '<option value="' . $key . '"' . $sel . '>' . $label . '</option>';
                    endforeach; ?>
                </select>
            </div>
            <div class="lf-field-row"><label class="lf-label">Galerie (JSON Array)</label><textarea class="lf-textarea" name="gallery"><?= htmlspecialchars(json_encode($selectedPage['gallery'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?></textarea></div>
            <div class="lf-field-row"><label class="lf-label">Benefits (JSON Array)</label><textarea class="lf-textarea" name="benefits"><?= htmlspecialchars(json_encode($selectedPage['benefits'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?></textarea></div>
            <div class="lf-field-row"><label class="lf-label">Pricing (JSON Array)</label><textarea class="lf-textarea" name="pricing"><?= htmlspecialchars(json_encode($selectedPage['pricing'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?></textarea></div>
            <div class="lf-field-row"><label class="lf-label">FAQ (JSON Array)</label><textarea class="lf-textarea" name="faq"><?= htmlspecialchars(json_encode($selectedPage['faq'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?></textarea></div>
            <div class="lf-field-row"><label class="lf-label">Meta Titel</label><input class="lf-input" name="meta_title" value="<?= htmlspecialchars($selectedPage['meta']['title'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="lf-field-row"><label class="lf-label">Meta Beschreibung</label><textarea class="lf-textarea" name="meta_description"><?= htmlspecialchars($selectedPage['meta']['description'] ?? '', ENT_QUOTES) ?></textarea></div>
            <div class="lf-field-row"><label class="lf-label">Canonical</label><input class="lf-input" name="meta_canonical" value="<?= htmlspecialchars($selectedPage['meta']['canonical'] ?? '', ENT_QUOTES) ?>"></div>
            <button class="admin-button" type="submit">Speichern</button>
        </form>
    </div>

    <div class="admin-card">
        <h2 class="admin-title">Konfiguration</h2>
        <form method="post">
            <input type="hidden" name="action" value="save_config">
            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
            <div class="lf-field-row"><label class="lf-label">Site Name</label><input class="lf-input" name="site_name" value="<?= htmlspecialchars($config['site_name'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="lf-field-row"><label class="lf-label">SEO Titel</label><input class="lf-input" name="seo_title" value="<?= htmlspecialchars($config['seo']['title'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="lf-field-row"><label class="lf-label">SEO Beschreibung</label><textarea class="lf-textarea" name="seo_description"><?= htmlspecialchars($config['seo']['description'] ?? '', ENT_QUOTES) ?></textarea></div>
            <div class="lf-field-row"><label class="lf-label">OpenAI Key</label><input class="lf-input" name="openai_key" value="<?= htmlspecialchars($config['openai']['api_key'] ?? '', ENT_QUOTES) ?>"></div>
            <div class="lf-field-row"><label class="lf-label">OpenAI Temperatur</label><input class="lf-input" name="openai_temperature" value="<?= htmlspecialchars((string)($config['openai']['temperature'] ?? 0.7), ENT_QUOTES) ?>"></div>
            <div class="lf-field-row"><label class="lf-label">Prompt Vorlage</label><textarea class="lf-textarea" name="openai_prompt"><?= htmlspecialchars($config['openai']['prompt_template'] ?? '', ENT_QUOTES) ?></textarea></div>
            <button class="admin-button" type="submit">Konfiguration speichern</button>
        </form>
    </div>
</div>
</body>
</html>
