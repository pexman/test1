# Booking & Appointment Management System

Este proyecto proporciona un sistema completo de gestión de reservas/citas construido en PHP con MySQL siguiendo prácticas de producción.

## Características clave

- Instalador automático que crea la base de datos, usuario admin y configuración.
- Arquitectura basada en PDO con prepared statements, transacciones y manejo robusto de errores.
- Formularios públicos con validación y verificación de disponibilidad en tiempo real.
- Panel administrativo seguro con autenticación, CSRF, filtros y exportación de datos.
- API RESTful con endpoints públicos y privados para integraciones.
- Registro completo de actividad y soporte para tokens de reserva/QR.
- Scripts de backup/restauración y documentación del esquema.

## Requisitos

- PHP 8.1+
- Extensiones `pdo`, `pdo_mysql`, `json`
- Servidor MySQL 5.7+ o MariaDB 10.2+
- Servidor web (Apache/Nginx) configurado para servir la carpeta `public/`

## Instalación

1. Clona el repositorio y da permisos de escritura a `config/` y `storage/`.
2. Accede a `install.php` desde el navegador y completa los datos solicitados.
3. Elimina `install.php` tras la instalación por seguridad.
4. Accede al panel admin mediante `/admin/login.php`.

## Backups

Utiliza `scripts/backup.sh config/config.php` para generar copias de seguridad comprimidas.

## Desarrollo

- Toda interacción con la base de datos se realiza mediante `Database` (`includes/db.php`).
- Los repositorios en `src/Repositories` encapsulan las consultas principales.
- Los servicios en `src/Services` contienen la lógica de negocio.

## Seguridad

- Autenticación por sesión con cookies seguras.
- Tokens CSRF en formularios administrativos.
- Tokens únicos generados con `random_bytes`.
- Sanitización de entrada y escape de salida HTML.

## Licencia

MIT
