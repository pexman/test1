<?php

declare(strict_types=1);

class MigrationRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function hasExecuted(string $version): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM " . DB_PREFIX . "migrations WHERE version = :version"
        );
        $stmt->execute([':version' => $version]);

        return (bool) $stmt->fetchColumn();
    }

    public function logExecution(string $version, string $description): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO " . DB_PREFIX . "migrations (version, description) VALUES (:version, :description)"
        );
        $stmt->execute([
            ':version' => $version,
            ':description' => $description,
        ]);
    }
}
