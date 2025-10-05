<?php

declare(strict_types=1);

class FoundViaRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function allActive(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM " . DB_PREFIX . "found_via_options WHERE active = 1 ORDER BY sort_order ASC, name ASC"
        );

        return $stmt->fetchAll();
    }
}
