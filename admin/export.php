<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/auth-check.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="bookings_' . date('Ymd_His') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Booking ID', 'Nombre', 'Email', 'Fecha', 'Estado']);

$stmt = $pdo->prepare('SELECT booking_id, customer_name, customer_email, booking_date, status FROM ' . DB_PREFIX . 'bookings ORDER BY created_at DESC LIMIT :limit');
$stmt->bindValue(':limit', 5000, PDO::PARAM_INT);
$stmt->execute();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row);
}

fclose($output);
exit;
