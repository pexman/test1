<?php

declare(strict_types=1);

class SettingsRepository
{
    private PDO $pdo;
    private array $cache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $stmt = $this->pdo->prepare(
            "SELECT setting_value FROM " . DB_PREFIX . "settings WHERE setting_key = :key"
        );
        $stmt->execute([':key' => $key]);
        $value = $stmt->fetchColumn();

        if ($value === false) {
            return $default;
        }

        $decoded = json_decode((string) $value, true);
        $this->cache[$key] = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;

        return $this->cache[$key];
    }

    public function set(string $key, mixed $value): void
    {
        $encodedValue = is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (string) $value;

        $stmt = $this->pdo->prepare(
            "INSERT INTO " . DB_PREFIX . "settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->execute([
            ':key' => $key,
            ':value' => $encodedValue,
        ]);

        $this->cache[$key] = $value;

        $logStmt = $this->pdo->prepare(
            "INSERT INTO " . DB_PREFIX . "activity_logs (log_type, action, description, metadata) VALUES ('system', 'settings_updated', :description, :metadata)"
        );
        $logStmt->execute([
            ':description' => $key,
            ':metadata' => json_encode(['key' => $key], JSON_THROW_ON_ERROR),
        ]);
    }
}
