<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

session_start();
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    header('Location: ../admin.php');
    exit;
}

$bookings = [];
foreach (glob(BOOKING_BOOKINGS_DIR . '/*.json') as $file) {
    $data = read_json($file);
    $bookings[] = $data;
}
usort($bookings, static fn($a,$b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

$total = count($bookings);
$pending = count(array_filter($bookings, static fn($b) => ($b['status'] ?? '') === 'pending'));
$confirmed = count(array_filter($bookings, static fn($b) => ($b['status'] ?? '') === 'confirmed'));
$currentMonth = date('Y-m');
$monthly = count(array_filter($bookings, static function ($b) use ($currentMonth) {
    $date = $b['date'] ?? '';
    return $date !== '' && strpos($date, $currentMonth) === 0;
}));

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Booking Dashboard · Studio Lumière</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Allura&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <style>
        :root {--color-dark:#7A6F64;--color-medium:#B8A99A;--color-light:#D8CEC3;--color-lighter:#EEEDED;}
        body {font-family:'Montserrat',sans-serif;margin:0;background:#f7f1ec;color:#3b3128;}
        header {padding:2rem 4vw;background:#fff;box-shadow:0 15px 50px rgba(122,111,100,.12);display:flex;justify-content:space-between;align-items:center;}
        header h1 {margin:0;color:#7A6F64;}
        header a {color:#7A6F64;text-decoration:none;font-weight:600;}
        main {padding:3rem 4vw;display:grid;gap:2rem;}
        .stats {display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem;}
        .stat {background:#fff;padding:1.5rem;border-radius:20px;box-shadow:0 15px 45px rgba(122,111,100,.12);}
        .stat h2 {margin:0;font-size:1rem;color:#7A6F64;text-transform:uppercase;letter-spacing:.05em;}
        .stat p {margin:.5rem 0 0;font-size:2rem;font-weight:600;}
        table {width:100%;border-collapse:collapse;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(122,111,100,.15);}
        th,td {padding:1rem 1.2rem;text-align:left;border-bottom:1px solid #EEEDED;font-size:.95rem;}
        th {background:#f0e6dd;color:#7A6F64;font-weight:600;}
        tr:last-child td {border-bottom:none;}
        .badge {padding:.3rem .7rem;border-radius:999px;font-weight:600;font-size:.8rem;color:#fff;}
        .pending {background:#f39c12;}
        .confirmed {background:#27ae60;}
        .cancelled {background:#c0392b;}
        .actions {display:flex;gap:.5rem;}
        .actions a,.actions button {padding:.5rem .9rem;border-radius:10px;border:none;text-decoration:none;font-weight:600;font-size:.85rem;cursor:pointer;}
        .btn-view {background:#EEEDED;color:#7A6F64;}
        .btn-confirm {background:#27ae60;color:#fff;}
        .btn-cancel {background:#c0392b;color:#fff;}
        .filters {display:flex;flex-wrap:wrap;gap:1rem;align-items:center;background:#fff;padding:1.5rem;border-radius:20px;box-shadow:0 15px 45px rgba(122,111,100,.12);}
        .filters label {display:flex;flex-direction:column;font-weight:600;color:#7A6F64;font-size:.9rem;}
        .filters select,.filters input {padding:.7rem 1rem;border:1px solid #D8CEC3;border-radius:12px;font-size:.95rem;}
    </style>
</head>
<body>
<header>
    <h1>Booking Dashboard</h1>
    <a href="../admin.php">Zurück zum CMS</a>
</header>
<main>
    <div class="stats">
        <div class="stat"><h2>Gesamt</h2><p><?= $total ?></p></div>
        <div class="stat"><h2>Ausstehend</h2><p><?= $pending ?></p></div>
        <div class="stat"><h2>Bestätigt</h2><p><?= $confirmed ?></p></div>
        <div class="stat"><h2>Dieser Monat</h2><p><?= $monthly ?></p></div>
    </div>
    <div class="filters">
        <label>Status
            <select id="filter-status">
                <option value="">Alle</option>
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </label>
        <label>Typ
            <select id="filter-type">
                <option value="">Alle</option>
                <?php foreach (array_unique(array_map(static fn($b) => $b['session_type'] ?? '', $bookings)) as $type): if (!$type) continue; ?>
                    <option value="<?= sanitize_text($type) ?>"><?= sanitize_text($type) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Datum
            <input type="date" id="filter-date" />
        </label>
        <label>Suche
            <input type="text" id="filter-search" placeholder="Name oder E-Mail" />
        </label>
    </div>
    <table id="booking-table">
        <thead>
            <tr>
                <th>ID</th><th>Name</th><th>Email</th><th>Typ</th><th>Termin</th><th>Status</th><th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bookings as $booking): ?>
                <tr data-status="<?= sanitize_text($booking['status']) ?>" data-type="<?= sanitize_text($booking['session_type']) ?>" data-date="<?= sanitize_text($booking['date']) ?>" data-search="<?= strtolower(sanitize_text($booking['name'] . ' ' . $booking['email'])) ?>">
                    <td><?= sanitize_text($booking['booking_id']) ?></td>
                    <td><?= sanitize_text($booking['name']) ?></td>
                    <td><?= sanitize_text($booking['email']) ?></td>
                    <td><?= sanitize_text($booking['session_type']) ?></td>
                    <td><?= sanitize_text($booking['date']) ?> · <?= sanitize_text($booking['time']) ?></td>
                    <td><span class="badge <?= sanitize_text($booking['status']) ?>"><?= sanitize_text($booking['status']) ?></span></td>
                    <td class="actions">
                        <a class="btn-view" href="booking-confirmation.php?token=<?= urlencode($booking['token']) ?>" target="_blank">Ansehen</a>
                        <?php if (($booking['status'] ?? '') === 'pending'): ?>
                            <button class="btn-confirm" data-action="confirm" data-token="<?= sanitize_text($booking['token']) ?>">Bestätigen</button>
                        <?php endif; ?>
                        <?php if (($booking['status'] ?? '') !== 'cancelled'): ?>
                            <button class="btn-cancel" data-action="cancel" data-token="<?= sanitize_text($booking['token']) ?>">Stornieren</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</main>
<script>
const rows = Array.from(document.querySelectorAll('#booking-table tbody tr'));
const statusFilter = document.getElementById('filter-status');
const typeFilter = document.getElementById('filter-type');
const dateFilter = document.getElementById('filter-date');
const searchFilter = document.getElementById('filter-search');

function applyFilters() {
    const status = statusFilter.value;
    const type = typeFilter.value;
    const date = dateFilter.value;
    const search = searchFilter.value.toLowerCase();
    rows.forEach(row => {
        const matchStatus = !status || row.dataset.status === status;
        const matchType = !type || row.dataset.type === type;
        const matchDate = !date || row.dataset.date === date;
        const matchSearch = !search || row.dataset.search.includes(search);
        row.style.display = matchStatus && matchType && matchDate && matchSearch ? '' : 'none';
    });
}

[statusFilter, typeFilter, dateFilter, searchFilter].forEach(input => input.addEventListener('input', applyFilters));

document.querySelectorAll('[data-action]').forEach(button => {
    button.addEventListener('click', async () => {
        const action = button.dataset.action;
        const token = button.dataset.token;
        const formData = new FormData();
        formData.append('token', token);
        const response = await fetch('booking-api.php?action=' + (action === 'confirm' ? 'confirm_booking' : 'cancel_booking'), {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (!data.error) {
            location.reload();
        } else {
            alert(data.error);
        }
    });
});
</script>
</body>
</html>
