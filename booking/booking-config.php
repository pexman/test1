<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

return [
    'session_types' => [
        'Paarshooting',
        'Babybauchshooting',
        'Familienshooting',
        'Newborn',
        'Mini-Shooting',
        'Sonstiges'
    ],
    'required_fields' => ['name','email','session_type','date','time','gdpr'],
    'discovery_sources' => ['Instagram','Facebook','Google','Empfehlung','Sonstiges'],
    'min_days_notice' => 2,
    'max_bookings_per_day' => 3,
    'auto_confirm' => false
];
