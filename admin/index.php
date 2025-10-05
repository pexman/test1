<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/auth-check.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../src/Repositories/BookingRepository.php';
require_once __DIR__ . '/../src/Repositories/AvailabilityRepository.php';
require_once __DIR__ . '/../src/Repositories/SessionTypeRepository.php';
require_once __DIR__ . '/../src/Repositories/ActivityLogRepository.php';
require_once __DIR__ . '/../src/Services/BookingService.php';

enforce_session_timeout();

$bookingRepository = new BookingRepository($pdo);
$availabilityRepository = new AvailabilityRepository($pdo);
$sessionTypeRepository = new SessionTypeRepository($pdo);
$activityLogRepository = new ActivityLogRepository($pdo);
$bookingService = new BookingService($bookingRepository, $availabilityRepository, $sessionTypeRepository, $activityLogRepository);

$filters = [
    'status' => $_GET['status'] ?? null,
    'search' => $_GET['search'] ?? null,
];

[$limit, $offset] = paginate((int) ($_GET['page'] ?? 1), 20);
$bookings = $bookingService->listBookings($filters, $limit, $offset);

$dashboardStmt = $pdo->query('SELECT status, COUNT(*) AS total FROM ' . DB_PREFIX . 'bookings GROUP BY status');
$stats = $dashboardStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="/public/css/admin.css">
</head>
<body>
    <header class="admin-header">
        <h1>Dashboard de reservas</h1>
        <nav>
            <a href="/admin/index.php">Inicio</a>
            <a href="/admin/export.php">Exportar CSV</a>
            <a href="/admin/logout.php">Salir</a>
        </nav>
    </header>

    <section class="stats">
        <?php foreach ($stats as $stat): ?>
            <div class="stat-card">
                <span class="stat-label"><?= htmlspecialchars($stat['status'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                <span class="stat-value"><?= (int) $stat['total']; ?></span>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="filters">
        <form method="get">
            <select name="status">
                <option value="">Todos</option>
                <option value="pending" <?= (($_GET['status'] ?? '') === 'pending') ? 'selected' : ''; ?>>Pendientes</option>
                <option value="confirmed" <?= (($_GET['status'] ?? '') === 'confirmed') ? 'selected' : ''; ?>>Confirmados</option>
                <option value="cancelled" <?= (($_GET['status'] ?? '') === 'cancelled') ? 'selected' : ''; ?>>Cancelados</option>
            </select>
            <input type="search" name="search" placeholder="Buscar" value="<?= htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <button type="submit">Filtrar</button>
        </form>
    </section>

    <section class="bookings">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Email</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bookings['data'] as $booking): ?>
                <tr>
                    <td><?= htmlspecialchars($booking['booking_id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($booking['customer_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($booking['customer_email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($booking['booking_date'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($booking['status'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</body>
</html>
