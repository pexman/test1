# Booking System Database Schema

This document describes the core database objects created by `install.php`.

## Tables

- `settings`: Key/value storage for runtime configuration. Values that are valid JSON will be decoded automatically by the application layer.
- `session_types`: Catalog of session types offered, including price and duration metadata.
- `bookings`: Master record for reservations. Uses tokens for secure lookup and includes GDPR fields. Supports soft deletes via `deleted_at`.
- `availability`: Stores daily availability and a JSON array of available time slots.
- `contacts`: Contact form submissions with status tracking.
- `email_templates`: HTML templates used for transactional email notifications.
- `activity_logs`: Auditable actions for bookings, admin events, QR scans, emails, and system tasks.
- `found_via_options`: Configurable list of lead sources for analytics.
- `migrations`: Tracks executed schema migrations.
- `admin_users`: Authenticated administrators with hashed passwords.

## Indices

Indexes follow the specification to optimise token lookups, status filters, and date-based queries. Foreign keys cascade `session_type_id` to `NULL` when a session type is removed to preserve booking history.

## Collation

All tables use `utf8mb4_unicode_ci` to fully support Unicode characters.
