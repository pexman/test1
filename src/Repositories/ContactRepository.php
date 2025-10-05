<?php

declare(strict_types=1);

class ContactRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(array $data): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO " . DB_PREFIX . "contacts (contact_id, name, email, phone, subject, message, ip_address, status) VALUES (:contact_id, :name, :email, :phone, :subject, :message, :ip_address, :status)"
        );
        $stmt->execute([
            ':contact_id' => $data['contact_id'],
            ':name' => $data['name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'] ?? null,
            ':subject' => $data['subject'] ?? null,
            ':message' => $data['message'],
            ':ip_address' => $data['ip_address'] ?? null,
            ':status' => $data['status'] ?? 'new',
        ]);
    }
}
