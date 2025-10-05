<?php
// Sample configuration file. install.php will generate config.php based on user input.

define('DB_HOST', 'localhost');
define('DB_NAME', 'booking_system');
define('DB_USER', 'booking_user');
define('DB_PASS', 'secure_password');
define('DB_CHARSET', 'utf8mb4');
define('DB_PREFIX', '');

define('APP_ENV', 'production');
define('APP_DEBUG', false);
define('APP_TIMEZONE', 'UTC');

define('SESSION_NAME', 'booking_admin_session');
define('SESSION_LIFETIME', 7200);

define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_ENCRYPTION', 'tls');

define('BASE_URL', 'http://localhost');

define('BACKUP_RETENTION', 10);
