<?php

namespace App\Domain\Physiotherapy\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum RecordStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Discharged = 'discharged';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'En tratamiento',
            self::Discharged => 'Dado de alta',
        };
    }

    public function color(): string
    {
        return $this === self::Active ? 'success' : 'neutral';
    }
}
