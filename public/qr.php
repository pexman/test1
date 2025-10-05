<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/Repositories/BookingRepository.php';
require_once __DIR__ . '/../src/Repositories/ActivityLogRepository.php';

$qrToken = sanitize_string($_GET['token'] ?? '');

if ($qrToken === '') {
    http_response_code(400);
    echo 'Token requerido';
    exit;
}

$bookingRepository = new BookingRepository($pdo);
$activityLogRepository = new ActivityLogRepository($pdo);
$booking = $bookingRepository->findByQrToken($qrToken);

if (!$booking) {
    http_response_code(404);
    echo 'Reserva no encontrada';
    exit;
}

$activityLogRepository->log('qr_verification', 'scan', [
    'description' => 'QR escaneado',
    'metadata' => ['booking_id' => $booking['booking_id']],
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
]);

if (!empty($booking['gallery_link'])) {
    header('Location: ' . $booking['gallery_link']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>QR verificado</title>
    <link rel="stylesheet" href="/public/css/public.css">
</head>
<body>
    <div class="booking-form">
        <h1>Reserva verificada</h1>
        <p>Cliente: <?= htmlspecialchars($booking['customer_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
        <p>Fecha: <?= htmlspecialchars($booking['booking_date'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
        <p>Estado: <?= htmlspecialchars($booking['status'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    </div>
</body>
</html>
