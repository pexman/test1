<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

template_response();

function template_response(): void
{
    $token = $_GET['token'] ?? '';
    if ($token === '') {
        echo 'Token erforderlich';
        return;
    }
    $booking = load_booking_by_token($token);
    if (!$booking) {
        echo 'Buchung nicht gefunden';
        return;
    }
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($booking['qr_token']);
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Buchungsbestätigung · <?= sanitize_text($booking['booking_id']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Allura&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <style>
        body {font-family:'Montserrat',sans-serif;margin:0;background:#f6f0eb;color:#3b3128;}
        main {max-width:900px;margin:3rem auto;padding:0 2rem;}
        .ticket {background:#fff;border-radius:24px;box-shadow:0 25px 80px rgba(122,111,100,.18);overflow:hidden;display:grid;grid-template-columns:2fr 1fr;min-height:480px;}
        .info {padding:2.5rem 3rem;display:grid;gap:1rem;}
        .info h1 {margin:0;font-size:2rem;color:#7A6F64;}
        .badge {display:inline-block;padding:.4rem .9rem;border-radius:999px;font-weight:600;color:#fff;}
        .status-pending {background:#f39c12;}
        .status-confirmed {background:#27ae60;}
        .status-cancelled {background:#c0392b;}
        .qr {background:linear-gradient(145deg,#7A6F64,#B8A99A);display:flex;flex-direction:column;align-items:center;justify-content:center;color:#fff;padding:2.5rem;gap:1rem;}
        .meta {display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;}
        .meta div {background:#f7f1ec;padding:1rem;border-radius:14px;}
        .actions {display:flex;gap:1rem;margin-top:1.5rem;}
        .actions a {padding:.8rem 1.3rem;border-radius:12px;text-decoration:none;font-weight:600;}
        .btn-cancel {background:#c0392b;color:#fff;}
        .btn-download {background:#7A6F64;color:#fff;}
        .gdpr {font-size:.9rem;color:#7A6F64;margin-top:2rem;}
        @media (max-width:800px){.ticket{grid-template-columns:1fr;} .qr{min-height:260px;}}
    </style>
</head>
<body>
<main>
    <div class="ticket" id="ticket">
        <div class="info">
            <h1>Buchung #<?= sanitize_text($booking['booking_id']) ?></h1>
            <div class="badge status-<?= sanitize_text($booking['status']) ?>">Status: <?= ucfirst(sanitize_text($booking['status'])) ?></div>
            <div class="meta">
                <div><strong>Name:</strong><br><?= sanitize_text($booking['name']) ?></div>
                <div><strong>E-Mail:</strong><br><?= sanitize_text($booking['email']) ?></div>
                <div><strong>Shooting:</strong><br><?= sanitize_text($booking['session_type']) ?></div>
                <div><strong>Termin:</strong><br><?= sanitize_text($booking['date']) ?> · <?= sanitize_text($booking['time']) ?></div>
                <div><strong>Instagram:</strong><br><?= sanitize_text($booking['instagram']) ?></div>
                <div><strong>Erstellt:</strong><br><?= sanitize_text($booking['created_at']) ?></div>
            </div>
            <?php if (!empty($booking['gallery_link'])): ?>
                <div><strong>Galerie:</strong> <a href="<?= sanitize_text($booking['gallery_link']) ?>" target="_blank">Ansehen</a></div>
            <?php endif; ?>
            <div class="actions">
                <?php if ($booking['status'] !== 'cancelled'): ?>
                    <a class="btn-cancel" href="booking-confirmation.php?token=<?= urlencode($booking['token']) ?>&cancel=1">Buchung stornieren</a>
                <?php endif; ?>
                <a class="btn-download" href="#" onclick="window.print();return false;">Ticket drucken</a>
            </div>
            <div class="gdpr">
                Ihre Daten werden gemäß DSGVO verarbeitet. Sie können der Verarbeitung jederzeit widersprechen. Bei Fragen wenden Sie sich an privacy@studio-lumiere.de.
            </div>
        </div>
        <div class="qr">
            <img src="<?= $qrUrl ?>" alt="QR Code" style="width:220px;height:220px;border-radius:12px;background:#fff;padding:1rem;" />
            <div style="text-align:center;">
                <strong>QR-Verifizierung</strong><br />
                Token: <?= sanitize_text($booking['qr_token']) ?><br />
                <a href="verify-qr.php?token=<?= urlencode($booking['qr_token']) ?>" style="color:#fff;text-decoration:underline;">Jetzt prüfen</a>
            </div>
        </div>
    </div>
</main>
<script>
<?php if (isset($_GET['cancel']) && $booking['status'] !== 'cancelled'): ?>
fetch('booking-api.php?action=cancel_booking', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'token=<?= urlencode($booking['token']) ?>'
}).then(() => window.location.href='booking-confirmation.php?token=<?= urlencode($booking['token']) ?>');
<?php else: ?>
setTimeout(() => window.location.reload(), 120000);
<?php endif; ?>
</script>
</body>
</html>
<?php
}

function load_booking_by_token(string $token): ?array
{
    foreach (glob(BOOKING_BOOKINGS_DIR . '/*.json') as $file) {
        $booking = read_json($file);
        if (($booking['token'] ?? '') === $token) {
            $booking['_file'] = $file;
            return $booking;
        }
    }
    return null;
}
