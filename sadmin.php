<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

session_start();
if (!isset($_SESSION['super_admin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
        if (hash_equals('supersecuretoken', $_POST['token'])) {
            $_SESSION['super_admin'] = true;
        } else {
            $error = 'Ungültiger Token';
        }
    } else {
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="utf-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <title>Super Admin Login</title>
            <style>
                body {font-family:'Montserrat',sans-serif;background:#f4eee9;height:100vh;margin:0;display:flex;align-items:center;justify-content:center;}
                form {background:#fff;padding:2.5rem;border-radius:18px;box-shadow:0 20px 60px rgba(122,111,100,.18);width:320px;}
                input {width:100%;padding:.8rem 1rem;border:1px solid #D8CEC3;border-radius:12px;}
                button {margin-top:1.4rem;width:100%;padding:.85rem;border:none;border-radius:12px;background:#7A6F64;color:#fff;font-weight:600;cursor:pointer;}
                .error {margin-top:1rem;color:#c0392b;}
            </style>
        </head>
        <body>
            <form method="post">
                <h1 style="margin-bottom:1rem;font-size:1.4rem;color:#7A6F64;">Super Admin</h1>
                <input type="password" name="token" placeholder="Token" required />
                <button type="submit">Login</button>
                <?php if (!empty($error)): ?><div class="error"><?= sanitize_text($error ?? '') ?></div><?php endif; ?>
            </form>
        </body>
        </html>
        <?php
        exit;
    }
}

$projectsDir = __DIR__;
$projects = [];
foreach (glob($projectsDir . '/lp-*', GLOB_ONLYDIR) as $dir) {
    $stats = [
        'name' => basename($dir),
        'modified' => date('d.m.Y H:i', filemtime($dir)),
        'admin' => basename($dir) . '/admin.php'
    ];
    $projects[] = $stats;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_project'])) {
    $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower($_POST['new_project'] ?? '')); 
    if ($slug !== '') {
        $source = __DIR__ . '/lp-blank';
        $target = __DIR__ . '/' . $slug;
        if (!file_exists($target) && is_dir($source)) {
            mkdir($target, 0775, true);
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $destPath = $target . '/' . $iterator->getSubPathName();
                if ($file->isDir()) {
                    if (!is_dir($destPath)) {
                        mkdir($destPath, 0775, true);
                    }
                } else {
                    copy($file->getPathname(), $destPath);
                }
            }
            $projects[] = ['name' => basename($target), 'modified' => date('d.m.Y H:i'), 'admin' => basename($target) . '/admin.php'];
            $message = 'Landing Page erstellt';
        } else {
            $error = 'Vorlage fehlt oder Projekt existiert bereits';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Super Admin Dashboard</title>
    <style>
        body {font-family:'Montserrat',sans-serif;background:#f7f1ec;margin:0;padding:4rem 6vw;color:#3b3128;}
        h1 {color:#7A6F64;}
        table {width:100%;border-collapse:collapse;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(122,111,100,.15);}
        th,td {padding:1rem 1.2rem;text-align:left;border-bottom:1px solid #EEEDED;}
        th {background:#f0e6dd;font-weight:600;color:#7A6F64;}
        tr:last-child td {border-bottom:none;}
        a {color:#7A6F64;text-decoration:none;font-weight:600;}
        form {margin-top:2rem;background:#fff;padding:1.5rem;border-radius:18px;box-shadow:0 15px 40px rgba(122,111,100,.15);display:flex;gap:1rem;align-items:center;max-width:420px;}
        input {flex:1;padding:.8rem 1rem;border:1px solid #D8CEC3;border-radius:12px;}
        button {padding:.85rem 1.5rem;border:none;border-radius:12px;background:#7A6F64;color:#fff;font-weight:600;cursor:pointer;}
        .message {margin-top:1rem;color:#27ae60;font-weight:600;}
        .error {margin-top:1rem;color:#c0392b;font-weight:600;}
    </style>
</head>
<body>
    <h1>Super Admin Dashboard</h1>
    <p>Verwalten Sie hier alle Landing Pages und greifen Sie direkt auf die Admin-Bereiche zu.</p>
    <table>
        <thead>
            <tr><th>Projekt</th><th>Aktualisiert</th><th>Admin</th></tr>
        </thead>
        <tbody>
            <?php if (!$projects): ?>
                <tr><td colspan="3">Keine Projekte vorhanden.</td></tr>
            <?php else: ?>
                <?php foreach ($projects as $project): ?>
                    <tr>
                        <td><?= sanitize_text($project['name']) ?></td>
                        <td><?= sanitize_text($project['modified']) ?></td>
                        <td><a href="<?= sanitize_text($project['admin']) ?>" target="_blank">Öffnen</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <form method="post">
        <input type="text" name="new_project" placeholder="Projekt-Slug" required />
        <button type="submit">Landing Page erstellen</button>
    </form>
    <?php if (!empty($message)): ?><div class="message"><?= sanitize_text($message ?? '') ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="error"><?= sanitize_text($error ?? '') ?></div><?php endif; ?>
</body>
</html>
