<?php

namespace App\Domain\Memberships\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum VisitLimitPeriod: string
{
    use HasLabel;

    case Week = 'week';
    case Month = 'month';
    case Term = 'term';

    public function label(): string
    {
        return match ($this) {
            self::Week => 'por semana',
            self::Month => 'por mes',
            self::Term => 'en toda la vigencia',
        };
    }
}
