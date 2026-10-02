<?php

namespace App\Domain\CheckIns\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum CheckInMethod: string
{
    use HasLabel;

    case Qr = 'qr';
    case Phone = 'phone';
    case MemberNumber = 'member_number';
    case MembershipCode = 'membership_code';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Qr => 'Código QR',
            self::Phone => 'Celular y PIN',
            self::MemberNumber => 'Número de cliente',
            self::MembershipCode => 'Código de membresía',
            self::Manual => 'Recepción',
        };
    }
}
