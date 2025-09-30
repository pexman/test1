<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

action_dispatch();

function action_dispatch(): void
{
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    switch ($action) {
        case 'create_booking':
            create_booking();
            break;
        case 'get_availability':
            get_availability();
            break;
        case 'set_availability':
            ensure_authenticated();
            set_availability();
            break;
        case 'check_availability_date':
            get_availability(true);
            break;
        case 'get_monthly_availability':
            ensure_authenticated();
            get_monthly_availability();
            break;
        case 'get_booking':
            get_booking();
            break;
        case 'confirm_booking':
            ensure_authenticated();
            update_booking_status('confirmed');
            break;
        case 'cancel_booking':
            update_booking_status('cancelled');
            break;
        case 'update_gallery_link':
            ensure_authenticated();
            update_gallery_link();
            break;
        case 'update_booking':
            ensure_authenticated();
            update_booking_data();
            break;
        default:
            echo json_encode(['error' => 'Unknown action']);
    }
}

function load_booking_settings(): array
{
    $defaults = include __DIR__ . '/booking-config.php';
    return array_replace_recursive($defaults, read_json(BOOKING_SETTINGS_FILE));
}

function create_booking(): void
{
    $settings = load_booking_settings();
    $required = $settings['required_fields'] ?? [];
    $data = $_POST;
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => 'Bitte füllen Sie alle Pflichtfelder aus.']);
            return;
        }
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige E-Mail-Adresse.']);
        return;
    }
    $date = $data['date'];
    $time = $data['time'];
    $minDays = (int)($settings['min_days_notice'] ?? 0);
    if ($minDays > 0) {
        $minTimestamp = strtotime('+' . $minDays . ' days');
        if (strtotime($date) < $minTimestamp) {
            http_response_code(400);
            echo json_encode(['error' => 'Bitte wählen Sie ein Datum mit ausreichender Vorlaufzeit.']);
            return;
        }
    }
    if (!validate_availability($date, $time, $settings)) {
        http_response_code(400);
        echo json_encode(['error' => 'Der ausgewählte Slot ist nicht mehr verfügbar.']);
        return;
    }
    $bookingId = 'BK' . strtoupper(bin2hex(random_bytes(4)));
    $token = generate_token(12);
    $qrToken = generate_token(10);
    $status = ($settings['auto_confirm'] ?? false) ? 'confirmed' : 'pending';
    $payload = [
        'booking_id' => $bookingId,
        'token' => $token,
        'qr_token' => $qrToken,
        'name' => sanitize_text($data['name']),
        'email' => sanitize_text($data['email']),
        'instagram' => sanitize_text($data['instagram'] ?? ''),
        'session_type' => sanitize_text($data['session_type']),
        'discovery' => sanitize_text($data['discovery'] ?? ''),
        'message' => sanitize_text($data['message'] ?? ''),
        'date' => $date,
        'time' => $time,
        'marketing' => !empty($data['marketing']),
        'gdpr_timestamp' => date('c'),
        'gdpr_ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
        'status' => $status,
        'created_at' => date('c')
    ];
    if (!write_json(BOOKING_BOOKINGS_DIR . '/booking_' . $bookingId . '.json', $payload)) {
        http_response_code(500);
        echo json_encode(['error' => 'Buchung konnte nicht gespeichert werden.']);
        return;
    }
    log_gdpr($payload);
    mark_slot($date, $time, $status === 'confirmed' ? 'booked' : 'reserved');
    secure_log('Booking created: ' . $bookingId);
    echo json_encode(['success' => true, 'token' => $token, 'booking_id' => $bookingId]);
}

function validate_availability(string $date, string $time, array $settings): bool
{
    $availability = read_json(AVAILABILITY_FILE);
    $day = $availability[$date] ?? [];
    if (($day[$time] ?? '') !== 'available') {
        return false;
    }
    $count = 0;
    foreach (glob(BOOKING_BOOKINGS_DIR . '/*.json') as $file) {
        $booking = read_json($file);
        if (($booking['date'] ?? '') === $date && ($booking['status'] ?? '') !== 'cancelled') {
            $count++;
        }
    }
    return $count < ($settings['max_bookings_per_day'] ?? 3);
}

function log_gdpr(array $payload): void
{
    $entry = json_encode([
        'timestamp' => date('c'),
        'booking_id' => $payload['booking_id'],
        'ip' => $payload['gdpr_ip'],
        'consent' => true,
        'marketing' => $payload['marketing']
    ]) . PHP_EOL;
    file_put_contents(GDPR_LOG_FILE, $entry, FILE_APPEND | LOCK_EX);
}

function mark_slot(string $date, string $time, string $status): void
{
    $availability = read_json(AVAILABILITY_FILE);
    if (!isset($availability[$date])) {
        $availability[$date] = [];
    }
    $availability[$date][$time] = $status;
    write_json(AVAILABILITY_FILE, $availability);
}

function get_availability(bool $raw = false): void
{
    $date = $_GET['date'] ?? $_POST['date'] ?? '';
    if ($date === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Date missing']);
        return;
    }
    $availability = read_json(AVAILABILITY_FILE);
    $day = $availability[$date] ?? [];
    $response = [];
    foreach ($day as $time => $status) {
        $response[] = [
            'time' => $time,
            'available' => $status === 'available'
        ];
    }
    echo json_encode(['date' => $date, 'slots' => $response]);
}

function set_availability(): void
{
    $payload = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($payload) || empty($payload['date']) || !isset($payload['slots'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        return;
    }
    $availability = read_json(AVAILABILITY_FILE);
    $availability[$payload['date']] = $payload['slots'];
    write_json(AVAILABILITY_FILE, $availability);
    secure_log('Availability updated for ' . $payload['date']);
    echo json_encode(['success' => true]);
}

function get_monthly_availability(): void
{
    $month = $_GET['month'] ?? date('Y-m');
    $availability = read_json(AVAILABILITY_FILE);
    $result = [];
    foreach ($availability as $date => $slots) {
        if (strpos($date, $month) === 0) {
            $result[$date] = $slots;
        }
    }
    echo json_encode(['month' => $month, 'days' => $result]);
}

function load_booking_by_token(string $token): ?array
{
    foreach (glob(BOOKING_BOOKINGS_DIR . '/*.json') as $file) {
        $booking = read_json($file);
        if (($booking['token'] ?? '') === $token || ($booking['qr_token'] ?? '') === $token || ($booking['booking_id'] ?? '') === $token) {
            $booking['_file'] = $file;
            return $booking;
        }
    }
    return null;
}

function get_booking(): void
{
    $token = $_GET['token'] ?? '';
    if ($token === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Token missing']);
        return;
    }
    $booking = load_booking_by_token($token);
    if (!$booking) {
        http_response_code(404);
        echo json_encode(['error' => 'Booking not found']);
        return;
    }
    unset($booking['_file']);
    echo json_encode(['booking' => $booking]);
}

function update_booking_status(string $status): void
{
    $token = $_POST['token'] ?? $_GET['token'] ?? '';
    if ($token === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Token required']);
        return;
    }
    $booking = load_booking_by_token($token);
    if (!$booking) {
        http_response_code(404);
        echo json_encode(['error' => 'Buchung nicht gefunden']);
        return;
    }
    $booking['status'] = $status;
    if ($status === 'cancelled') {
        mark_slot($booking['date'], $booking['time'], 'available');
    } elseif ($status === 'confirmed') {
        mark_slot($booking['date'], $booking['time'], 'booked');
    }
    write_json($booking['_file'], $booking);
    secure_log('Booking status changed to ' . $status . ' for ' . ($booking['booking_id'] ?? '')); 
    echo json_encode(['success' => true]);
}

function update_gallery_link(): void
{
    $token = $_POST['token'] ?? '';
    $galleryLink = trim($_POST['gallery_link'] ?? '');
    if ($token === '' || $galleryLink === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Daten']);
        return;
    }
    $booking = load_booking_by_token($token);
    if (!$booking) {
        http_response_code(404);
        echo json_encode(['error' => 'Buchung nicht gefunden']);
        return;
    }
    $booking['gallery_link'] = $galleryLink;
    write_json($booking['_file'], $booking);
    echo json_encode(['success' => true]);
}

function update_booking_data(): void
{
    $payload = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($payload) || empty($payload['token'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Daten']);
        return;
    }
    $booking = load_booking_by_token($payload['token']);
    if (!$booking) {
        http_response_code(404);
        echo json_encode(['error' => 'Buchung nicht gefunden']);
        return;
    }
    $allowed = ['name','email','instagram','session_type','discovery','message','date','time'];
    $oldDate = $booking['date'] ?? '';
    $oldTime = $booking['time'] ?? '';
    foreach ($allowed as $field) {
        if (isset($payload[$field])) {
            $booking[$field] = sanitize_text((string)$payload[$field]);
        }
    }
    if (($booking['date'] ?? '') !== $oldDate || ($booking['time'] ?? '') !== $oldTime) {
        if ($oldDate && $oldTime) {
            mark_slot($oldDate, $oldTime, 'available');
        }
        if (($booking['date'] ?? '') && ($booking['time'] ?? '')) {
            mark_slot($booking['date'], $booking['time'], $booking['status'] === 'confirmed' ? 'booked' : 'reserved');
        }
    }
    write_json($booking['_file'], $booking);
    echo json_encode(['success' => true]);
}
