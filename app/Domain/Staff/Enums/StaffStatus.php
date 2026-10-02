<?php

namespace App\Domain\Staff\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum StaffStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Inactive => 'Inactivo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'neutral',
        };
    }
}
