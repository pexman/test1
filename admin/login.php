<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

start_admin_session();

if (!empty($_SESSION['admin_authenticated'])) {
    header('Location: /admin/index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Invalid CSRF token.';
    } else {
        $email = sanitize_string($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if (!validate_email($email)) {
            $error = 'Invalid credentials.';
        } elseif (!admin_login($pdo, $email, $password)) {
            $error = 'Invalid credentials.';
        } else {
            header('Location: /admin/index.php');
            exit;
        }
    }
}

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <link rel="stylesheet" href="/public/css/admin.css">
</head>
<body>
    <div class="login-container">
        <h1>Panel administrativo</h1>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $token; ?>">
            <label>Email
                <input type="email" name="email" required>
            </label>
            <label>Contraseña
                <input type="password" name="password" required>
            </label>
            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>
</html>
