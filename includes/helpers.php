<?php
function sanitize_string(?string $value): string
{
    return trim((string) $value);
}

function validate_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function generate_token(int $length = 32): string
{
    return bin2hex(random_bytes($length));
}

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function paginate(int $page, int $perPage): array
{
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    return [$perPage, $offset];
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function require_admin_auth(): void
{
    if (function_exists('admin_session_valid')) {
        if (!admin_session_valid()) {
            header('Location: /admin/login.php');
            exit;
        }
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_secure' => isset($_SERVER['HTTPS']),
            'cookie_samesite' => 'Strict',
            'name' => SESSION_NAME,
        ]);
    }

    if (empty($_SESSION['admin_authenticated'])) {
        header('Location: /admin/login.php');
        exit;
    }
}
