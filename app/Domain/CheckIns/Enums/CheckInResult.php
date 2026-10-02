<?php

namespace App\Domain\CheckIns\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum CheckInResult: string
{
    use HasLabel;

    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Aceptado',
            self::Rejected => 'Rechazado',
        };
    }

    public function color(): string
    {
        return $this === self::Accepted ? 'success' : 'danger';
    }
}
