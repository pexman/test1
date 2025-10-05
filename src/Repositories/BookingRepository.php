<?php

declare(strict_types=1);

class BookingRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(array $data): array
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO " . DB_PREFIX . "bookings (booking_id, token, qr_token, status, customer_name, customer_email, customer_instagram, session_type_id, found_via, booking_date, booking_time, wunschtermin, message, gallery_link, gdpr_processing, gdpr_marketing, consent_date, ip_address, user_agent) VALUES (:booking_id, :token, :qr_token, :status, :customer_name, :customer_email, :customer_instagram, :session_type_id, :found_via, :booking_date, :booking_time, :wunschtermin, :message, :gallery_link, :gdpr_processing, :gdpr_marketing, NOW(), :ip_address, :user_agent)"
            );

            $stmt->execute([
                ':booking_id' => $data['booking_id'],
                ':token' => $data['token'],
                ':qr_token' => $data['qr_token'],
                ':status' => $data['status'] ?? 'pending',
                ':customer_name' => $data['customer_name'],
                ':customer_email' => $data['customer_email'],
                ':customer_instagram' => $data['customer_instagram'] ?? null,
                ':session_type_id' => $data['session_type_id'] ?? null,
                ':found_via' => $data['found_via'] ?? null,
                ':booking_date' => $data['booking_date'],
                ':booking_time' => $data['booking_time'] ?? null,
                ':wunschtermin' => $data['wunschtermin'] ?? null,
                ':message' => $data['message'] ?? null,
                ':gallery_link' => $data['gallery_link'] ?? null,
                ':gdpr_processing' => (int) ($data['gdpr_processing'] ?? 1),
                ':gdpr_marketing' => (int) ($data['gdpr_marketing'] ?? 0),
                ':ip_address' => $data['ip_address'] ?? null,
                ':user_agent' => $data['user_agent'] ?? null,
            ]);

            if (!empty($data['slot_update'])) {
                $availabilityStmt = $this->pdo->prepare(
                    "UPDATE " . DB_PREFIX . "availability SET slots = JSON_REMOVE(slots, JSON_UNQUOTE(JSON_SEARCH(slots, 'one', :slot))), updated_at = NOW() WHERE availability_date = :date"
                );
                $availabilityStmt->execute([
                    ':slot' => $data['slot_update']['slot'],
                    ':date' => $data['slot_update']['date'],
                ]);
            }

            $logStmt = $this->pdo->prepare(
                "INSERT INTO " . DB_PREFIX . "activity_logs (log_type, action, user_identifier, ip_address, metadata) VALUES ('booking', 'created', :user_identifier, :ip_address, :metadata)"
            );
            $logStmt->execute([
                ':user_identifier' => $data['customer_email'],
                ':ip_address' => $data['ip_address'] ?? null,
                ':metadata' => json_encode(['booking_id' => $data['booking_id']], JSON_THROW_ON_ERROR),
            ]);

            $this->pdo->commit();

            return $this->findByBookingId($data['booking_id']);
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function findByBookingId(string $bookingId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT b.*, st.name AS session_name FROM " . DB_PREFIX . "bookings b LEFT JOIN " . DB_PREFIX . "session_types st ON b.session_type_id = st.id WHERE b.booking_id = :booking_id"
        );
        $stmt->execute([':booking_id' => $bookingId]);
        $booking = $stmt->fetch();

        return $booking ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT b.*, st.name AS session_name FROM " . DB_PREFIX . "bookings b LEFT JOIN " . DB_PREFIX . "session_types st ON b.session_type_id = st.id WHERE b.token = :token"
        );
        $stmt->execute([':token' => $token]);
        $booking = $stmt->fetch();

        return $booking ?: null;
    }

    public function findByQrToken(string $qrToken): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT booking_id, customer_name, customer_email, session_type_id, booking_date, booking_time, status, gallery_link FROM " . DB_PREFIX . "bookings WHERE qr_token = :qr_token"
        );
        $stmt->execute([':qr_token' => $qrToken]);
        $booking = $stmt->fetch();

        return $booking ?: null;
    }

    public function updateStatus(string $bookingId, string $status): bool
    {
        $validStatuses = ['pending', 'confirmed', 'cancelled'];
        if (!in_array($status, $validStatuses, true)) {
            throw new InvalidArgumentException('Invalid status');
        }

        $timestampColumn = match ($status) {
            'confirmed' => 'confirmed_at',
            'cancelled' => 'cancelled_at',
            default => null,
        };

        $columns = "status = :status";
        if ($timestampColumn) {
            $columns .= ", {$timestampColumn} = NOW()";
        }

        $stmt = $this->pdo->prepare(
            "UPDATE " . DB_PREFIX . "bookings SET {$columns} WHERE booking_id = :booking_id"
        );

        return $stmt->execute([
            ':status' => $status,
            ':booking_id' => $bookingId,
        ]);
    }

    public function paginate(array $filters, int $limit, int $offset): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'b.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(b.customer_name LIKE :search OR b.customer_email LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'b.booking_date >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'b.booking_date <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        $sql = "SELECT SQL_CALC_FOUND_ROWS b.*, st.name AS session_name FROM " . DB_PREFIX . "bookings b LEFT JOIN " . DB_PREFIX . "session_types st ON b.session_type_id = st.id WHERE " . implode(' AND ', $where) . " ORDER BY b.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $bookings = $stmt->fetchAll();

        $countStmt = $this->pdo->query('SELECT FOUND_ROWS() AS total');
        $total = (int) $countStmt->fetchColumn();

        return ['data' => $bookings, 'total' => $total];
    }
}
