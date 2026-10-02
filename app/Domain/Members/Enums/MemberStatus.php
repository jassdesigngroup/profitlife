<?php

namespace App\Domain\Members\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum MemberStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Inactive = 'inactive';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Inactive => 'Inactivo',
            self::Blocked => 'Bloqueado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'neutral',
            self::Blocked => 'danger',
        };
    }
}
