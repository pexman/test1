<?php

declare(strict_types=1);

class BookingService
{
    private BookingRepository $bookingRepository;
    private AvailabilityRepository $availabilityRepository;
    private SessionTypeRepository $sessionTypeRepository;
    private ActivityLogRepository $activityLogRepository;

    public function __construct(
        BookingRepository $bookingRepository,
        AvailabilityRepository $availabilityRepository,
        SessionTypeRepository $sessionTypeRepository,
        ActivityLogRepository $activityLogRepository
    ) {
        $this->bookingRepository = $bookingRepository;
        $this->availabilityRepository = $availabilityRepository;
        $this->sessionTypeRepository = $sessionTypeRepository;
        $this->activityLogRepository = $activityLogRepository;
    }

    public function createBooking(array $payload): array
    {
        $sessionType = null;
        if (!empty($payload['session_type_id'])) {
            $sessionType = $this->sessionTypeRepository->findByTypeId($payload['session_type_id']);
            if (!$sessionType) {
                throw new InvalidArgumentException('Session type not found');
            }
        }

        if (empty($payload['customer_name'])) {
            throw new InvalidArgumentException('Customer name is required');
        }

        if (empty($payload['customer_email']) || !validate_email((string) $payload['customer_email'])) {
            throw new InvalidArgumentException('A valid email address is required');
        }

        $bookingId = strtoupper(bin2hex(random_bytes(5)));
        $token = generate_token(16);
        $qrToken = generate_token(16);

        if (empty($payload['booking_date'])) {
            throw new InvalidArgumentException('Booking date is required');
        }

        $availability = $this->availabilityRepository->getByDate($payload['booking_date']);
        if (!$availability || !$availability['is_available']) {
            throw new InvalidArgumentException('Date not available');
        }

        $slots = json_decode($availability['slots'] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
        if (!empty($payload['booking_time']) && !in_array($payload['booking_time'], $slots, true)) {
            throw new InvalidArgumentException('Time slot not available');
        }

        $booking = $this->bookingRepository->create([
            'booking_id' => $bookingId,
            'token' => $token,
            'qr_token' => $qrToken,
            'status' => 'pending',
            'customer_name' => sanitize_string($payload['customer_name']),
            'customer_email' => $payload['customer_email'],
            'customer_instagram' => $payload['customer_instagram'] ?? null,
            'session_type_id' => $sessionType['id'] ?? null,
            'found_via' => $payload['found_via'] ?? null,
            'booking_date' => $payload['booking_date'],
            'booking_time' => $payload['booking_time'] ?? null,
            'wunschtermin' => $payload['wunschtermin'] ?? null,
            'message' => $payload['message'] ?? null,
            'gallery_link' => null,
            'gdpr_processing' => true,
            'gdpr_marketing' => !empty($payload['gdpr_marketing']),
            'ip_address' => $payload['ip_address'] ?? null,
            'user_agent' => $payload['user_agent'] ?? null,
            'slot_update' => !empty($payload['booking_time']) ? [
                'date' => $payload['booking_date'],
                'slot' => $payload['booking_time'],
            ] : null,
        ]);

        $this->activityLogRepository->log('booking', 'queued_email', [
            'user_identifier' => $payload['customer_email'],
            'metadata' => ['booking_id' => $bookingId, 'type' => 'confirmation'],
        ]);

        return $booking;
    }

    public function confirmBooking(string $bookingId): void
    {
        if ($this->bookingRepository->updateStatus($bookingId, 'confirmed')) {
            $this->activityLogRepository->log('booking', 'status_changed', [
                'description' => 'Booking confirmed',
                'metadata' => ['booking_id' => $bookingId, 'status' => 'confirmed'],
            ]);
        }
    }

    public function cancelBooking(string $bookingId): void
    {
        if ($this->bookingRepository->updateStatus($bookingId, 'cancelled')) {
            $this->activityLogRepository->log('booking', 'status_changed', [
                'description' => 'Booking cancelled',
                'metadata' => ['booking_id' => $bookingId, 'status' => 'cancelled'],
            ]);
        }
    }

    public function listBookings(array $filters, int $limit, int $offset): array
    {
        return $this->bookingRepository->paginate($filters, $limit, $offset);
    }
}
