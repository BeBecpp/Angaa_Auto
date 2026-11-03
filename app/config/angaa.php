<?php

/*
|--------------------------------------------------------------------------
| АНГАА АВТО business configuration
|--------------------------------------------------------------------------
|
| Non-secret defaults. Owner-editable business details (phone, address, hours)
| live in the business_settings table and are edited in the admin panel.
| Secrets (SMS provider credentials) belong in the environment only.
|
*/

return [

    'brand' => [
        'name' => 'АНГАА АВТО',
        'latin_name' => 'ANGAA AUTO',
        'descriptor' => 'Авто сервис · Дархан',
        'tagline' => 'Засвар бүр бүртгэлтэй.',
        'hero' => 'Машиныхаа засварыг мэдэж яв.',
    ],

    'timezone' => env('BUSINESS_TIMEZONE', 'Asia/Ulaanbaatar'),

    'phone' => [
        'country_code' => '976',
        'local_pattern' => '/^[0-9]{8}$/',
    ],

    'uploads' => [
        'disk' => 'media',
        'max_files_per_request' => 5,
        'max_bytes' => 8 * 1024 * 1024,
        'max_pixels' => 16_000_000,
        'max_edge_px' => 2400,
        'thumb_edge_px' => 480,
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'abandoned_grace_hours' => 24,
    ],

    'otp' => [
        'ttl_minutes' => 10,
        'max_attempts' => 5,
        'resend_cooldown_seconds' => 60,
        'local_viewer' => env('OTP_LOCAL_VIEWER', true),
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
    ],

    'reminders' => [
        'quiet_start' => '20:00',
        'quiet_end' => '09:00',
        'service_offsets_days' => [-7, 0, 7],
        'appointment_offsets_hours' => [24, 2],
        'max_per_customer_per_day' => 2,
        'max_attempts' => 3,
        'mileage_max_age_days' => 180,
    ],

    'consent' => [
        'contact_version' => '2026-11',
        'reminder_version' => '2026-11',
    ],

    'scheduling' => [
        'slot_minutes' => 30,
        'default_duration_min' => 60,
        'lock_seconds' => 10,
    ],

    'backup' => [
        'destination' => env('BACKUP_DESTINATION'),
    ],

];
