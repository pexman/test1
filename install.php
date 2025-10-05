<?php
declare(strict_types=1);

$errors = [];
$success = false;

if (!extension_loaded('pdo') || !extension_loaded('pdo_mysql')) {
    $errors[] = 'Las extensiones PDO y PDO_MySQL son requeridas.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors) {
    $host = trim((string) ($_POST['db_host'] ?? 'localhost'));
    $dbName = trim((string) ($_POST['db_name'] ?? ''));
    $dbUser = trim((string) ($_POST['db_user'] ?? ''));
    $dbPass = (string) ($_POST['db_pass'] ?? '');
    $dbPrefix = trim((string) ($_POST['db_prefix'] ?? ''));
    $adminEmail = trim((string) ($_POST['admin_email'] ?? ''));
    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    $timezone = trim((string) ($_POST['timezone'] ?? 'UTC'));
    $smtpHost = trim((string) ($_POST['smtp_host'] ?? ''));
    $smtpPort = (int) ($_POST['smtp_port'] ?? 587);
    $smtpUser = trim((string) ($_POST['smtp_user'] ?? ''));
    $smtpPass = (string) ($_POST['smtp_pass'] ?? '');
    $smtpEncryption = trim((string) ($_POST['smtp_encryption'] ?? 'tls'));

    if ($dbName === '' || $dbUser === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || $adminPassword === '') {
        $errors[] = 'Todos los campos obligatorios deben completarse.';
    } else {
        try {
            $dsn = "mysql:host={$host};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $dbName) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $dsnDb = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";
            $db = new PDO($dsnDb, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $db->exec("SET time_zone='" . addslashes($timezone) . "'");

            $tablesSql = [
                'settings' => "CREATE TABLE IF NOT EXISTS {$dbPrefix}settings ( id INT PRIMARY KEY AUTO_INCREMENT, setting_key VARCHAR(100) UNIQUE NOT NULL, setting_value TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci",
                'session_types' => "CREATE TABLE IF NOT EXISTS {$dbPrefix}session_types ( id INT PRIMARY KEY AUTO_INCREMENT, type_id VARCHAR(50) UNIQUE NOT NULL, name VARCHAR(100) NOT NULL, description TEXT, duration VARCHAR(50), price VARCHAR(50), active BOOLEAN DEFAULT 1, sort_order INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci",
                'bookings' => "CREATE TABLE IF NOT EXISTS {$dbPrefix}bookings ( id INT PRIMARY KEY AUTO_INCREMENT, booking_id VARCHAR(50) UNIQUE NOT NULL, token VARCHAR(64) UNIQUE NOT NULL, qr_token VARCHAR(64) UNIQUE NOT NULL, status ENUM('pending','confirmed','cancelled') DEFAULT 'pending', customer_name VARCHAR(100) NOT NULL, customer_email VARCHAR(150) NOT NULL, customer_instagram VARCHAR(100), session_type_id INT, found_via VARCHAR(50), booking_date DATE NOT NULL, booking_time TIME, wunschtermin TEXT, message TEXT, gallery_link VARCHAR(500), gdpr_processing BOOLEAN DEFAULT 1, gdpr_marketing BOOLEAN DEFAULT 0, consent_date TIMESTAMP, ip_address VARCHAR(45), user_agent TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, confirmed_at TIMESTAMP NULL, cancelled_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL, INDEX idx_booking_id (booking_id), INDEX idx_token (token), INDEX idx_qr_token (qr_token), INDEX idx_status (status), INDEX idx_booking_date (booking_date), INDEX idx_customer_email (customer_email), FOREIGN KEY (session_type_id) REFERENCES {$dbPrefix}session_types(id) ON DELETE SET NULL ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci",
                'availability' => "CREATE TABLE IF NOT EXISTS {$dbPrefix}availability ( id INT PRIMARY KEY AUTO_INCREMENT, availability_date DATE UNIQUE NOT NULL, is_available BOOLEAN DEFAULT 1, slots JSON, notes TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_date (availability_date) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci",
                'contacts' => "CREATE TABLE IF NOT EXISTS {$dbPrefix}contacts ( id INT PRIMARY KEY AUTO_INCREMENT, contact_id VARCHAR(50) UNIQUE NOT NULL, name VARCHAR(100) NOT NULL, email VARCHAR(150) NOT NULL, phone VARCHAR(50), subject VARCHAR(200), message TEXT, ip_address VARCHAR(45), status ENUM('new','read','responded') DEFAULT 'new', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_status (status), INDEX idx_created (created_at) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci",
                'email_templates' => "CREATE TABLE IF NOT EXISTS {$dbPrefix}email_templates ( id INT PRIMARY KEY AUTO_INCREMENT, template_key VARCHAR(50) UNIQUE NOT NULL, template_name VARCHAR(100) NOT NULL, subject TEXT NOT NULL, body_html TEXT NOT NULL, variables JSON, active BOOLEAN DEFAULT 1, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci",
                'activity_logs' => "CREATE TABLE IF NOT EXISTS {$dbPrefix}activity_logs ( id INT PRIMARY KEY AUTO_INCREMENT, log_type ENUM('booking','admin','qr_verification','email','system') NOT NULL, action VARCHAR(100) NOT NULL, description TEXT, user_identifier VARCHAR(150), ip_address VARCHAR(45), metadata JSON, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_type (log_type), INDEX idx_created (created_at) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci",
                'found_via_options' => "CREATE TABLE IF NOT EXISTS {$dbPrefix}found_via_options ( id INT PRIMARY KEY AUTO_INCREMENT, option_id VARCHAR(50) UNIQUE NOT NULL, name VARCHAR(100) NOT NULL, active BOOLEAN DEFAULT 1, sort_order INT DEFAULT 0 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci",
                'migrations' => "CREATE TABLE IF NOT EXISTS {$dbPrefix}migrations ( id INT PRIMARY KEY AUTO_INCREMENT, version VARCHAR(20) UNIQUE NOT NULL, description VARCHAR(200), executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci",
                'admin_users' => "CREATE TABLE IF NOT EXISTS {$dbPrefix}admin_users ( id INT PRIMARY KEY AUTO_INCREMENT, email VARCHAR(150) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL, active BOOLEAN DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];

            foreach ($tablesSql as $sql) {
                $db->exec($sql);
            }

            $db->beginTransaction();

            $db->prepare("INSERT IGNORE INTO {$dbPrefix}session_types (type_id, name, description, duration, price, active, sort_order) VALUES (:type_id, :name, :description, :duration, :price, 1, :sort)")
                ->execute([':type_id' => 'standard', ':name' => 'Sesión estándar', ':description' => 'Sesión fotográfica básica', ':duration' => '60 minutos', ':price' => '150 EUR', ':sort' => 1]);

            $db->prepare("INSERT IGNORE INTO {$dbPrefix}found_via_options (option_id, name, active, sort_order) VALUES (:option_id, :name, 1, :sort)")
                ->execute([':option_id' => 'instagram', ':name' => 'Instagram', ':sort' => 1]);

            $db->prepare("INSERT IGNORE INTO {$dbPrefix}settings (setting_key, setting_value) VALUES ('site_name', :value)")
                ->execute([':value' => 'Sistema de reservas']);

            $db->prepare("INSERT IGNORE INTO {$dbPrefix}email_templates (template_key, template_name, subject, body_html, variables, active) VALUES ('booking_confirmation', 'Confirmación de reserva', 'Confirmación de reserva {{booking_id}}', '<p>Hola {{customer_name}},</p><p>Tu reserva para el {{booking_date}} ha sido recibida.</p>', JSON_ARRAY('booking_id','customer_name','booking_date'), 1)")
                ->execute();

            $db->prepare("INSERT INTO {$dbPrefix}admin_users (email, password_hash) VALUES (:email, :password) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), active = 1")
                ->execute([
                    ':email' => strtolower($adminEmail),
                    ':password' => password_hash($adminPassword, PASSWORD_BCRYPT),
                ]);

            $db->prepare("INSERT IGNORE INTO {$dbPrefix}migrations (version, description) VALUES ('1.0.0', 'Initial schema')")
                ->execute();

            $db->commit();

            $configContent = "<?php\n" .
                "define('DB_HOST', '" . addslashes($host) . "');\n" .
                "define('DB_NAME', '" . addslashes($dbName) . "');\n" .
                "define('DB_USER', '" . addslashes($dbUser) . "');\n" .
                "define('DB_PASS', '" . addslashes($dbPass) . "');\n" .
                "define('DB_CHARSET', 'utf8mb4');\n" .
                "define('DB_PREFIX', '" . addslashes($dbPrefix) . "');\n" .
                "define('APP_ENV', 'production');\n" .
                "define('APP_DEBUG', false);\n" .
                "define('APP_TIMEZONE', '" . addslashes($timezone) . "');\n" .
                "define('SESSION_NAME', 'booking_admin_session');\n" .
                "define('SESSION_LIFETIME', 7200);\n" .
                "define('SMTP_HOST', '" . addslashes($smtpHost) . "');\n" .
                "define('SMTP_PORT', " . $smtpPort . ");\n" .
                "define('SMTP_USER', '" . addslashes($smtpUser) . "');\n" .
                "define('SMTP_PASS', '" . addslashes($smtpPass) . "');\n" .
                "define('SMTP_ENCRYPTION', '" . addslashes($smtpEncryption) . "');\n" .
                "define('BASE_URL', 'http://' . \$_SERVER['HTTP_HOST']);\n" .
                "define('BACKUP_RETENTION', 10);\n";

            file_put_contents(__DIR__ . '/config/config.php', $configContent);

            $tables = ['settings', 'session_types', 'bookings', 'availability', 'contacts', 'email_templates', 'activity_logs', 'found_via_options', 'migrations', 'admin_users'];
            foreach ($tables as $table) {
                $stmt = $db->query('SHOW TABLES LIKE ' . $db->quote($dbPrefix . $table));
                if ($stmt->rowCount() === 0) {
                    throw new RuntimeException('La tabla ' . $table . ' no se creó correctamente.');
                }
            }

            $success = true;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Instalador</title>
    <link rel="stylesheet" href="/public/css/public.css">
</head>
<body>
<div class="booking-form">
    <h1>Instalador del sistema de reservas</h1>
    <?php foreach ($errors as $error): ?>
        <p class="error"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
    <?php endforeach; ?>
    <?php if ($success): ?>
        <p class="success">Instalación completada. Elimina install.php por seguridad.</p>
    <?php else: ?>
        <form method="post">
            <label>Host MySQL
                <input type="text" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
            </label>
            <label>Base de datos
                <input type="text" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
            </label>
            <label>Usuario MySQL
                <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
            </label>
            <label>Contraseña MySQL
                <input type="password" name="db_pass">
            </label>
            <label>Prefijo de tablas
                <input type="text" name="db_prefix" value="<?= htmlspecialchars($_POST['db_prefix'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </label>
            <label>Email administrador
                <input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
            </label>
            <label>Contraseña admin
                <input type="password" name="admin_password" required>
            </label>
            <label>Timezone
                <input type="text" name="timezone" value="<?= htmlspecialchars($_POST['timezone'] ?? 'UTC', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" required>
            </label>
            <fieldset>
                <legend>SMTP (opcional)</legend>
                <label>Host SMTP
                    <input type="text" name="smtp_host" value="<?= htmlspecialchars($_POST['smtp_host'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                </label>
                <label>Puerto SMTP
                    <input type="number" name="smtp_port" value="<?= (int) ($_POST['smtp_port'] ?? 587); ?>">
                </label>
                <label>Usuario SMTP
                    <input type="text" name="smtp_user" value="<?= htmlspecialchars($_POST['smtp_user'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                </label>
                <label>Contraseña SMTP
                    <input type="password" name="smtp_pass">
                </label>
                <label>Encriptación
                    <input type="text" name="smtp_encryption" value="<?= htmlspecialchars($_POST['smtp_encryption'] ?? 'tls', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                </label>
            </fieldset>
            <button type="submit">Instalar</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
