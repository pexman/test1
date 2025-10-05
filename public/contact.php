<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/Repositories/ContactRepository.php';
require_once __DIR__ . '/../src/Repositories/ActivityLogRepository.php';

$contactRepository = new ContactRepository($pdo);
$activityLogRepository = new ActivityLogRepository($pdo);

$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_string($_POST['name'] ?? '');
    $email = sanitize_string($_POST['email'] ?? '');
    $message = sanitize_string($_POST['message'] ?? '');

    if ($name === '' || $message === '' || !validate_email($email)) {
        $errors[] = 'Todos los campos son obligatorios y el email debe ser válido.';
    } else {
        $contactId = strtoupper(bin2hex(random_bytes(6)));
        $contactRepository->create([
            'contact_id' => $contactId,
            'name' => $name,
            'email' => $email,
            'phone' => sanitize_string($_POST['phone'] ?? ''),
            'subject' => sanitize_string($_POST['subject'] ?? ''),
            'message' => $message,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        $activityLogRepository->log('system', 'contact_created', [
            'user_identifier' => $email,
            'metadata' => ['contact_id' => $contactId],
        ]);

        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contacto</title>
    <link rel="stylesheet" href="/public/css/public.css">
</head>
<body>
<div class="booking-form">
    <h1>Contacto</h1>
    <?php foreach ($errors as $error): ?>
        <p class="error"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endforeach; ?>
    <?php if ($success): ?>
        <p class="success">Hemos recibido tu mensaje.</p>
    <?php else: ?>
        <form method="post">
            <label>Nombre
                <input type="text" name="name" required>
            </label>
            <label>Email
                <input type="email" name="email" required>
            </label>
            <label>Teléfono
                <input type="text" name="phone">
            </label>
            <label>Asunto
                <input type="text" name="subject">
            </label>
            <label>Mensaje
                <textarea name="message" required></textarea>
            </label>
            <button type="submit">Enviar</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
