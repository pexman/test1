<?php
declare(strict_types=1);

// Global configuration for CMS and booking system

$defaultPasswordHash = '$2y$12$M9MHfd7vqtEPX79KuiPpduOQE52YpnVtlj2BjfMeCa/3/OI4O4Hu2';
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
    if (defined('ADMIN_PASSWORD_OVERRIDE')) {
        $defaultPasswordHash = ADMIN_PASSWORD_OVERRIDE;
    }
}
define('ADMIN_PASSWORD_HASH', $defaultPasswordHash);
define('ADMIN_SESSION_TIMEOUT', 3600);
define('DATA_DIR', __DIR__ . '/data');
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('CACHE_DIR', __DIR__ . '/cache');
define('BOOKING_DATA_DIR', __DIR__ . '/booking/data');
define('BOOKING_BOOKINGS_DIR', BOOKING_DATA_DIR . '/bookings');
define('CONTENT_FILE', DATA_DIR . '/content.json');
define('BACKUP_DIR', DATA_DIR . '/backups');
define('AVAILABILITY_FILE', BOOKING_DATA_DIR . '/availability.json');
define('BOOKING_SETTINGS_FILE', BOOKING_DATA_DIR . '/booking_settings.json');
define('GDPR_LOG_FILE', BOOKING_DATA_DIR . '/gdpr_log.jsonl');
define('SECURITY_LOG', DATA_DIR . '/security.log');
define('TIMEZONE', 'Europe/Berlin');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

date_default_timezone_set(TIMEZONE);

if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0775, true);
}
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0775, true);
}
if (!is_dir(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0775, true);
}
if (!is_dir(BOOKING_DATA_DIR)) {
    mkdir(BOOKING_DATA_DIR, 0775, true);
}
if (!is_dir(BOOKING_BOOKINGS_DIR)) {
    mkdir(BOOKING_BOOKINGS_DIR, 0775, true);
}
if (!is_dir(dirname(GDPR_LOG_FILE))) {
    mkdir(dirname(GDPR_LOG_FILE), 0775, true);
}

function secure_log(string $message): void
{
    $entry = sprintf('[%s] %s%s', date('c'), $message, PHP_EOL);
    file_put_contents(SECURITY_LOG, $entry, FILE_APPEND | LOCK_EX);
}

function read_json(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }
    $content = file_get_contents($path);
    if ($content === false || $content === '') {
        return [];
    }
    $data = json_decode($content, true);
    if (!is_array($data)) {
        return [];
    }
    return $data;
}

function write_json(string $path, array $data): bool
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return (bool)file_put_contents($path, $json, LOCK_EX);
}

function sanitize_text(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function generate_token(int $length = 16): string
{
    return bin2hex(random_bytes($length));
}

function ensure_authenticated(): void
{
    session_start();
    if (!isset($_SESSION['admin_last_activity'])) {
        $_SESSION['admin_last_activity'] = time();
    }
    if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
        http_response_code(403);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }
    if (time() - $_SESSION['admin_last_activity'] > ADMIN_SESSION_TIMEOUT) {
        session_destroy();
        http_response_code(440);
        echo json_encode(['error' => 'Session expired']);
        exit;
    }
    $_SESSION['admin_last_activity'] = time();
}

function backup_file(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    $timestamp = date('Ymd_His');
    $filename = basename($path, '.json') . '_' . $timestamp . '.json';
    copy($path, BACKUP_DIR . '/' . $filename);
}

function rate_limit(string $key, int $limit = 5, int $window = 60): bool
{
    $bucket = sys_get_temp_dir() . '/cms_rate_' . md5($key);
    $entries = [];
    if (file_exists($bucket)) {
        $entries = array_filter(array_map('intval', explode('\n', trim((string)file_get_contents($bucket)))));
        $entries = array_filter($entries, static fn($time) => ($time + $window) >= time());
    }
    if (count($entries) >= $limit) {
        return false;
    }
    $entries[] = time();
    file_put_contents($bucket, implode("\n", $entries));
    return true;
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
?>
