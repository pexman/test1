<?php

declare(strict_types=1);

class ActivityLogRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function log(string $type, string $action, array $data = []): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO " . DB_PREFIX . "activity_logs (log_type, action, description, user_identifier, ip_address, metadata) VALUES (:log_type, :action, :description, :user_identifier, :ip_address, :metadata)"
        );

        $stmt->execute([
            ':log_type' => $type,
            ':action' => $action,
            ':description' => $data['description'] ?? null,
            ':user_identifier' => $data['user_identifier'] ?? null,
            ':ip_address' => $data['ip_address'] ?? null,
            ':metadata' => !empty($data['metadata']) ? json_encode($data['metadata'], JSON_THROW_ON_ERROR) : null,
        ]);
    }
}
