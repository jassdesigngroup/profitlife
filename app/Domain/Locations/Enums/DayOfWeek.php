<?php

namespace App\Domain\Locations\Enums;

use App\Domain\Shared\Enums\HasLabel;

/**
 * Día de la semana ISO-8601: 1 = lunes ... 7 = domingo.
 */
enum DayOfWeek: int
{
    use HasLabel;

    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Lunes',
            self::Tuesday => 'Martes',
            self::Wednesday => 'Miércoles',
            self::Thursday => 'Jueves',
            self::Friday => 'Viernes',
            self::Saturday => 'Sábado',
            self::Sunday => 'Domingo',
        };
    }

    public function shortLabel(): string
    {
        return mb_substr($this->label(), 0, 3);
    }
}
