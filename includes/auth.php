<?php
require_once __DIR__ . '/helpers.php';

function start_admin_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_secure' => isset($_SERVER['HTTPS']),
            'cookie_samesite' => 'Strict',
            'name' => SESSION_NAME,
        ]);
    }
}

function admin_login(PDO $pdo, string $email, string $password): bool
{
    $stmt = $pdo->prepare('SELECT id, password_hash FROM ' . DB_PREFIX . 'admin_users WHERE email = :email AND active = 1');
    $stmt->execute([':email' => strtolower($email)]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        start_admin_session();

        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_user_id'] = $user['id'];
        $_SESSION['last_activity'] = time();

        return true;
    }

    return false;
}

function admin_logout(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }
}

function admin_session_valid(bool $renew = true): bool
{
    start_admin_session();

    $lifetime = SESSION_LIFETIME ?? 7200;
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $lifetime) {
        admin_logout();
        return false;
    }

    if ($renew) {
        $_SESSION['last_activity'] = time();
    }

    return !empty($_SESSION['admin_authenticated']);
}

function enforce_session_timeout(): void
{
    if (!admin_session_valid()) {
        header('Location: /admin/login.php?timeout=1');
        exit;
    }
}
