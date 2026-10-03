<?php

namespace App\Domain\Training\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum ProgramType: string
{
    use HasLabel;

    case Training = 'training';
    case Rehab = 'rehab';

    public function label(): string
    {
        return match ($this) {
            self::Training => 'Entrenamiento',
            self::Rehab => 'Rehabilitación',
        };
    }
}
