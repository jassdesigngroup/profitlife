<?php

namespace App\Domain\Locations\DTOs;

use App\Domain\Locations\Enums\DayOfWeek;

/**
 * Franja horaria local de la sede, en formato HH:MM.
 */
final readonly class HourSlot
{
    public function __construct(
        public DayOfWeek $day,
        public string $opensAt,
        public string $closesAt,
    ) {}
}
