<?php

declare(strict_types=1);

class AvailabilityRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getByDate(string $date): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM " . DB_PREFIX . "availability WHERE availability_date = :date"
        );
        $stmt->execute([':date' => $date]);
        $availability = $stmt->fetch();

        return $availability ?: null;
    }

    public function setAvailability(string $date, bool $isAvailable, array $slots, ?string $notes = null): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO " . DB_PREFIX . "availability (availability_date, is_available, slots, notes) VALUES (:date, :is_available, :slots, :notes) ON DUPLICATE KEY UPDATE is_available = VALUES(is_available), slots = VALUES(slots), notes = VALUES(notes), updated_at = NOW()"
        );

        $stmt->execute([
            ':date' => $date,
            ':is_available' => $isAvailable ? 1 : 0,
            ':slots' => json_encode(array_values($slots), JSON_THROW_ON_ERROR),
            ':notes' => $notes,
        ]);
    }

    public function removeSlot(string $date, string $slot): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE " . DB_PREFIX . "availability SET slots = JSON_REMOVE(slots, JSON_UNQUOTE(JSON_SEARCH(slots, 'one', :slot))), updated_at = NOW() WHERE availability_date = :date"
        );
        $stmt->execute([
            ':slot' => $slot,
            ':date' => $date,
        ]);
    }

    public function listBetween(string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM " . DB_PREFIX . "availability WHERE availability_date BETWEEN :start AND :end ORDER BY availability_date"
        );
        $stmt->execute([
            ':start' => $startDate,
            ':end' => $endDate,
        ]);

        return $stmt->fetchAll();
    }
}
