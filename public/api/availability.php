<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../src/Repositories/AvailabilityRepository.php';

header('Access-Control-Allow-Origin: *');

$date = sanitize_string($_GET['date'] ?? '');
if (!$date) {
    json_response(['error' => 'Date required'], 400);
}

try {
    $availabilityRepository = new AvailabilityRepository($pdo);
    $availability = $availabilityRepository->getByDate($date);

    if (!$availability || !$availability['is_available']) {
        json_response(['slots' => []]);
    }

    $slots = json_decode($availability['slots'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
    json_response(['slots' => $slots]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    json_response(['error' => 'Server error'], 500);
}
