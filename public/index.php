<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/Repositories/SessionTypeRepository.php';
require_once __DIR__ . '/../src/Repositories/FoundViaRepository.php';
require_once __DIR__ . '/../src/Repositories/AvailabilityRepository.php';
require_once __DIR__ . '/../src/Repositories/BookingRepository.php';
require_once __DIR__ . '/../src/Repositories/ActivityLogRepository.php';
require_once __DIR__ . '/../src/Services/BookingService.php';

$sessionTypeRepository = new SessionTypeRepository($pdo);
$foundViaRepository = new FoundViaRepository($pdo);
$availabilityRepository = new AvailabilityRepository($pdo);
$bookingRepository = new BookingRepository($pdo);
$activityLogRepository = new ActivityLogRepository($pdo);
$bookingService = new BookingService($bookingRepository, $availabilityRepository, $sessionTypeRepository, $activityLogRepository);

$sessionTypes = $sessionTypeRepository->allActive();
$foundViaOptions = $foundViaRepository->allActive();

$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerName = sanitize_string($_POST['customer_name'] ?? '');
    $customerEmail = sanitize_string($_POST['customer_email'] ?? '');
    $sessionType = sanitize_string($_POST['session_type'] ?? '');
    $bookingDate = sanitize_string($_POST['booking_date'] ?? '');
    $bookingTime = sanitize_string($_POST['booking_time'] ?? '');

    if ($customerName === '') {
        $errors[] = 'El nombre es obligatorio.';
    }

    if (!validate_email($customerEmail)) {
        $errors[] = 'El email no es válido.';
    }

    if ($bookingDate === '') {
        $errors[] = 'La fecha es obligatoria.';
    }

    if (!$errors) {
        try {
            $booking = $bookingService->createBooking([
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_instagram' => sanitize_string($_POST['customer_instagram'] ?? ''),
                'session_type_id' => $sessionType,
                'found_via' => sanitize_string($_POST['found_via'] ?? ''),
                'booking_date' => $bookingDate,
                'booking_time' => $bookingTime,
                'wunschtermin' => sanitize_string($_POST['wunschtermin'] ?? ''),
                'message' => sanitize_string($_POST['message'] ?? ''),
                'gdpr_marketing' => !empty($_POST['gdpr_marketing']),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
            $success = true;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reservar sesión</title>
    <link rel="stylesheet" href="/public/css/public.css">
</head>
<body>
    <main class="booking-form">
        <h1>Reserva tu sesión</h1>
        <?php if ($success): ?>
            <p class="success">¡Reserva realizada! Recibirás un email de confirmación.</p>
        <?php else: ?>
            <?php foreach ($errors as $error): ?>
                <p class="error"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
            <?php endforeach; ?>
            <form method="post" id="bookingForm">
                <label>Nombre completo
                    <input type="text" name="customer_name" required>
                </label>
                <label>Email
                    <input type="email" name="customer_email" required>
                </label>
                <label>Instagram
                    <input type="text" name="customer_instagram">
                </label>
                <label>Tipo de sesión
                    <select name="session_type" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($sessionTypes as $sessionType): ?>
                            <option value="<?= htmlspecialchars($sessionType['type_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                <?= htmlspecialchars($sessionType['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>¿Cómo nos encontraste?
                    <select name="found_via">
                        <option value="">Seleccionar</option>
                        <?php foreach ($foundViaOptions as $option): ?>
                            <option value="<?= htmlspecialchars($option['option_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                <?= htmlspecialchars($option['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Fecha deseada
                    <input type="date" name="booking_date" id="bookingDate" required>
                </label>
                <label>Hora disponible
                    <select name="booking_time" id="bookingTime"></select>
                </label>
                <label>Notas adicionales
                    <textarea name="message"></textarea>
                </label>
                <label>
                    <input type="checkbox" name="gdpr_marketing" value="1"> Deseo recibir novedades.
                </label>
                <button type="submit">Reservar</button>
            </form>
        <?php endif; ?>
    </main>
    <script src="/public/js/booking.js"></script>
</body>
</html>
