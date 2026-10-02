<?php

namespace App\Domain\CheckIns\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum CredentialType: string
{
    use HasLabel;

    case Qr = 'qr';
    case MembershipCode = 'membership_code';

    public function label(): string
    {
        return match ($this) {
            self::Qr => 'Código QR',
            self::MembershipCode => 'Código de membresía',
        };
    }
}
