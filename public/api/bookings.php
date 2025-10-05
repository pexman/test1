<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../src/Repositories/BookingRepository.php';
require_once __DIR__ . '/../../src/Repositories/AvailabilityRepository.php';
require_once __DIR__ . '/../../src/Repositories/SessionTypeRepository.php';
require_once __DIR__ . '/../../src/Repositories/ActivityLogRepository.php';
require_once __DIR__ . '/../../src/Services/BookingService.php';

$bookingRepository = new BookingRepository($pdo);
$availabilityRepository = new AvailabilityRepository($pdo);
$sessionTypeRepository = new SessionTypeRepository($pdo);
$activityLogRepository = new ActivityLogRepository($pdo);
$bookingService = new BookingService($bookingRepository, $availabilityRepository, $sessionTypeRepository, $activityLogRepository);

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if (!empty($_GET['token'])) {
                $booking = $bookingRepository->findByToken(sanitize_string($_GET['token']));
                if (!$booking) {
                    json_response(['error' => 'Not found'], 404);
                }
                unset($booking['token'], $booking['qr_token']);
                json_response(['booking' => $booking]);
            }

            start_admin_session();
            if (!admin_session_valid()) {
                json_response(['error' => 'Unauthorized'], 401);
            }

            [$limit, $offset] = paginate((int) ($_GET['page'] ?? 1), (int) ($_GET['limit'] ?? 20));
            $filters = [
                'status' => $_GET['status'] ?? null,
                'search' => $_GET['search'] ?? null,
            ];
            $results = $bookingService->listBookings($filters, $limit, $offset);
            json_response($results);

        case 'POST':
            $input = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($input)) {
                json_response(['error' => 'Invalid payload'], 400);
            }

            $input['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? null;
            $input['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;

            try {
                $booking = $bookingService->createBooking($input);
            } catch (InvalidArgumentException $exception) {
                json_response(['error' => $exception->getMessage()], 400);
            }

            json_response(['booking' => $booking], 201);

        case 'PATCH':
            start_admin_session();
            if (!admin_session_valid()) {
                json_response(['error' => 'Unauthorized'], 401);
            }

            $input = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($input) || empty($input['booking_id']) || empty($input['status'])) {
                json_response(['error' => 'Invalid payload'], 400);
            }

            if ($input['status'] === 'confirmed') {
                $bookingService->confirmBooking($input['booking_id']);
            } elseif ($input['status'] === 'cancelled') {
                $bookingService->cancelBooking($input['booking_id']);
            }

            json_response(['status' => 'ok']);

        default:
            header('Allow: GET, POST, PATCH', true, 405);
            json_response(['error' => 'Method not allowed'], 405);
    }
} catch (InvalidArgumentException $exception) {
    json_response(['error' => $exception->getMessage()], 400);
} catch (Throwable $e) {
    error_log($e->getMessage());
    json_response(['error' => 'Server error'], 500);
}
