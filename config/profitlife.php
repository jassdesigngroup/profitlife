<?php

/*
|--------------------------------------------------------------------------
| Valores por defecto de la organización
|--------------------------------------------------------------------------
|
| Son el respaldo cuando no existe el ajuste correspondiente en la tabla
| `settings` (grupo `general`). Nada específico de un cliente vive en el
| código: la marca, la moneda y la zona horaria se configuran aquí o en
| `settings` y se leen a través de App\Domain\Settings\Services\Settings.
|
*/

return [

    'brand_name' => env('APP_NAME', 'Laravel'),

    'currency' => env('APP_CURRENCY', 'COP'),

    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'America/Bogota'),

    'locale' => env('APP_LOCALE', 'es_CO'),

    'members' => [
        // Prefijo del número visible de cliente (p. ej. PL-000123). Se puede cambiar en settings.
        'number_prefix' => env('MEMBER_NUMBER_PREFIX', 'CL'),
        'number_padding' => 6,
    ],

    'memberships' => [
        'grace_days' => (int) env('MEMBERSHIP_GRACE_DAYS', 5),
    ],

    'appointments' => [
        // Horas antes de la cita hasta las que se cancela sin descontar la sesión.
        'cancellation_hours' => (int) env('APPOINTMENT_CANCELLATION_HOURS', 12),
        // Minutos antes de la cita desde los que el kiosco deja entrar a quien no tiene membresía.
        'check_in_minutes_before' => 60,
    ],

    'check_ins' => [
        // Minutos en los que un segundo ingreso del mismo cliente cuenta como repetido.
        'duplicate_minutes' => (int) env('CHECKIN_DUPLICATE_MINUTES', 60),
        // Intentos fallidos de celular + PIN antes de bloquear ese celular un rato.
        'pin_max_attempts' => 5,
        'pin_decay_seconds' => 900,
    ],

    'documents' => [
        'disk' => env('DOCUMENTS_DISK', 'local'),
        'max_kb' => 10240,
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
    ],

    'invitations' => [
        'expires_hours' => (int) env('INVITATION_EXPIRES_HOURS', 72),
    ],

];
