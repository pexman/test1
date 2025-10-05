<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

set_exception_handler(static function (Throwable $throwable): void {
    error_log($throwable->getMessage());
    if (defined('APP_DEBUG') && APP_DEBUG) {
        http_response_code(500);
        echo '<pre>' . htmlspecialchars($throwable, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
    } else {
        http_response_code(500);
        echo 'An unexpected error occurred. Please try again later.';
    }
});

if (function_exists('date_default_timezone_set') && defined('APP_TIMEZONE')) {
    date_default_timezone_set(APP_TIMEZONE);
}

$dbInstance = Database::getInstance();
$pdo = $dbInstance->getConnection();
