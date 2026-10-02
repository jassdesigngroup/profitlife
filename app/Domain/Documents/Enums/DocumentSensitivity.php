<?php

namespace App\Domain\Documents\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum DocumentSensitivity: string
{
    use HasLabel;

    case Administrative = 'administrative';
    case Clinical = 'clinical';

    public function label(): string
    {
        return match ($this) {
            self::Administrative => 'Administrativo',
            self::Clinical => 'Clínico',
        };
    }
}
