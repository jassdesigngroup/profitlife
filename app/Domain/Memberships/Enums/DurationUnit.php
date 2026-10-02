<?php

namespace App\Domain\Memberships\Enums;

use App\Domain\Shared\Enums\HasLabel;
use Carbon\CarbonImmutable;

enum DurationUnit: string
{
    use HasLabel;

    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Días',
            self::Week => 'Semanas',
            self::Month => 'Meses',
            self::Year => 'Años',
        };
    }

    public function describe(int $count): string
    {
        return match ($this) {
            self::Day => $count === 1 ? '1 día' : "{$count} días",
            self::Week => $count === 1 ? '1 semana' : "{$count} semanas",
            self::Month => $count === 1 ? '1 mes' : "{$count} meses",
            self::Year => $count === 1 ? '1 año' : "{$count} años",
        };
    }

    /**
     * Último día del periodo que empieza en $start (inclusive). Un mes desde
     * el 31 de enero termina el 28/29 de febrero, nunca se desborda.
     */
    public function endOfPeriod(CarbonImmutable $start, int $count): CarbonImmutable
    {
        $next = match ($this) {
            self::Day => $start->addDays($count),
            self::Week => $start->addWeeks($count),
            self::Month => $start->addMonthsNoOverflow($count),
            self::Year => $start->addYearsNoOverflow($count),
        };

        return $next->subDay();
    }
}
