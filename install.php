<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$requirements = [
    'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'JSON Extension' => extension_loaded('json'),
    'GD Extension' => extension_loaded('gd'),
    'Fileinfo Extension' => extension_loaded('fileinfo'),
    'data/ beschreibbar' => is_writable(DATA_DIR),
    'uploads/ beschreibbar' => is_writable(UPLOAD_DIR)
];

$status = array_reduce($requirements, static fn($carry, $item) => $carry && $item, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminPassword = trim($_POST['admin_password'] ?? '');
    if ($adminPassword !== '') {
        $configPath = __DIR__ . '/config.local.php';
        $config = "<?php\nconst ADMIN_PASSWORD_OVERRIDE = '" . password_hash($adminPassword, PASSWORD_DEFAULT) . "';\n";
        file_put_contents($configPath, $config, LOCK_EX);
        secure_log('Install script set admin password override');
    }
    $message = 'Installation abgeschlossen.';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Installation · Studio Lumière</title>
    <style>
        body {font-family:'Montserrat',sans-serif;background:#f5f0eb;margin:0;padding:4rem 6vw;color:#3b3128;}
        h1 {color:#7A6F64;}
        ul {list-style:none;padding:0;}
        li {background:#fff;margin-bottom:1rem;padding:1rem 1.5rem;border-radius:16px;box-shadow:0 12px 30px rgba(122,111,100,.12);display:flex;justify-content:space-between;align-items:center;}
        .status-ok {color:#27ae60;font-weight:600;}
        .status-fail {color:#c0392b;font-weight:600;}
        form {margin-top:2rem;background:#fff;padding:2rem;border-radius:20px;box-shadow:0 15px 40px rgba(122,111,100,.18);max-width:480px;}
        label {display:block;margin-bottom:.5rem;font-weight:600;}
        input[type=password] {width:100%;padding:.8rem 1rem;border:1px solid #D8CEC3;border-radius:12px;font-size:1rem;}
        button {margin-top:1.5rem;padding:.85rem 1.5rem;border:none;border-radius:999px;background:#7A6F64;color:#fff;font-weight:600;cursor:pointer;}
        .message {margin-top:1.5rem;color:#27ae60;font-weight:600;}
    </style>
</head>
<body>
    <h1>Installationsassistent</h1>
    <p>Prüfen Sie die Systemanforderungen und setzen Sie optional ein neues Admin-Passwort.</p>
    <ul>
        <?php foreach ($requirements as $label => $ok): ?>
            <li>
                <span><?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
                <span class="<?= $ok ? 'status-ok' : 'status-fail' ?>"><?= $ok ? 'OK' : 'Fehlt' ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
    <form method="post">
        <label>Neues Admin-Passwort (optional)</label>
        <input type="password" name="admin_password" placeholder="Neues Passwort" />
        <button type="submit">Speichern</button>
        <?php if (!empty($message)): ?>
            <div class="message">Installation abgeschlossen.</div>
        <?php endif; ?>
    </form>
</body>
</html>
