<?php

namespace App\Domain\Appointments\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum CreditReason: string
{
    use HasLabel;

    case Grant = 'grant';
    case Consume = 'consume';
    case Expire = 'expire';
    case Adjust = 'adjust';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Grant => 'Sesiones del plan',
            self::Consume => 'Cita',
            self::Expire => 'Vencimiento',
            self::Adjust => 'Ajuste manual',
            self::Refund => 'Devolución',
        };
    }
}
