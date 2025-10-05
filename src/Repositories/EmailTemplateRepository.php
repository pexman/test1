<?php

declare(strict_types=1);

class EmailTemplateRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findActiveByKey(string $key): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM " . DB_PREFIX . "email_templates WHERE template_key = :template_key AND active = 1"
        );
        $stmt->execute([':template_key' => $key]);
        $template = $stmt->fetch();

        return $template ?: null;
    }

    public function updateBody(string $key, string $subject, string $body): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE " . DB_PREFIX . "email_templates SET subject = :subject, body_html = :body, updated_at = CURRENT_TIMESTAMP WHERE template_key = :template_key"
        );
        $stmt->execute([
            ':subject' => $subject,
            ':body' => $body,
            ':template_key' => $key,
        ]);
    }
}
