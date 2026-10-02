<?php

namespace App\Domain\Physiotherapy\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum PlanStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Active => 'En curso',
            self::Completed => 'Completado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Active => 'info',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }
}
