<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

$token = $_GET['token'] ?? '';
if ($token === '') {
    exit('Token erforderlich');
}

$booking = null;
foreach (glob(BOOKING_BOOKINGS_DIR . '/*.json') as $file) {
    $data = read_json($file);
    if (($data['qr_token'] ?? '') === $token) {
        $booking = $data;
        break;
    }
}

$logEntry = json_encode([
    'timestamp' => date('c'),
    'token' => $token,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
    'status' => $booking ? 'success' : 'failed'
]) . PHP_EOL;
file_put_contents(DATA_DIR . '/verification.log', $logEntry, FILE_APPEND | LOCK_EX);

if (!$booking) {
    http_response_code(404);
    exit('Buchung nicht gefunden');
}

if (!empty($booking['gallery_link'])) {
    header('Refresh: 5; url=' . $booking['gallery_link']);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>QR-Verifikation · Studio Lumière</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <style>
        body {font-family:'Montserrat',sans-serif;background:#f2ede7;margin:0;display:flex;align-items:center;justify-content:center;height:100vh;color:#3b3128;}
        .card {background:#fff;padding:3rem;border-radius:24px;box-shadow:0 25px 70px rgba(122,111,100,.18);max-width:540px;width:100%;display:grid;gap:1.2rem;text-align:center;}
        h1 {margin:0;color:#7A6F64;}
        .badge {display:inline-block;padding:.4rem 1rem;border-radius:999px;font-weight:600;color:#fff;background:#27ae60;}
        .meta {display:grid;gap:.8rem;text-align:left;}
        .meta div {background:#f7f1ec;padding:1rem;border-radius:14px;}
        a {color:#7A6F64;font-weight:600;}
    </style>
</head>
<body>
<div class="card">
    <h1>QR geprüft</h1>
    <div class="badge">Token gültig</div>
    <div class="meta">
        <div><strong>Name:</strong><br><?= sanitize_text($booking['name']) ?></div>
        <div><strong>Shooting:</strong><br><?= sanitize_text($booking['session_type']) ?></div>
        <div><strong>Termin:</strong><br><?= sanitize_text($booking['date']) ?> · <?= sanitize_text($booking['time']) ?></div>
        <div><strong>Status:</strong><br><?= sanitize_text($booking['status']) ?></div>
        <?php if (!empty($booking['gallery_link'])): ?>
            <div><strong>Galerie:</strong> <a href="<?= sanitize_text($booking['gallery_link']) ?>" target="_blank">Wird in Kürze geöffnet</a></div>
        <?php endif; ?>
    </div>
    <p>Dieser QR-Code ist zur Verifizierung gültig. Bitte zeigen Sie diese Seite beim Studio-Einlass.</p>
</div>
</body>
</html>
