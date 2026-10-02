<?php

namespace App\Support;

use App\Domain\Settings\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

/**
 * Fecha "de hoy" según la zona horaria del negocio (no la del servidor, que
 * es UTC). Entre las 7 p. m. y la medianoche de Bogotá, UTC ya va un día
 * adelante: las vigencias deben usar la fecha local.
 *
 * Devuelve la fecha local a medianoche, comparable con columnas DATE.
 */
final class BusinessDate
{
    public static function today(): CarbonImmutable
    {
        $local = Date::now()->setTimezone(app(Settings::class)->displayTimezone());

        return CarbonImmutable::createFromFormat('!Y-m-d', $local->format('Y-m-d'), 'UTC');
    }
}
