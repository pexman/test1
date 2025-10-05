<?php

declare(strict_types=1);

class SessionTypeRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function allActive(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM " . DB_PREFIX . "session_types WHERE active = 1 ORDER BY sort_order ASC, name ASC"
        );

        return $stmt->fetchAll();
    }

    public function findByTypeId(string $typeId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM " . DB_PREFIX . "session_types WHERE type_id = :type_id"
        );
        $stmt->execute([':type_id' => $typeId]);
        $sessionType = $stmt->fetch();

        return $sessionType ?: null;
    }
}
