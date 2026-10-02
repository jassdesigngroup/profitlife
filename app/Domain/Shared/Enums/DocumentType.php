<?php

namespace App\Domain\Shared\Enums;

enum DocumentType: string
{
    use HasLabel;

    case CitizenshipCard = 'CC';
    case ForeignerId = 'CE';
    case IdentityCard = 'TI';
    case Passport = 'PA';
    case TemporaryProtectionPermit = 'PPT';

    public function label(): string
    {
        return match ($this) {
            self::CitizenshipCard => 'Cédula de ciudadanía',
            self::ForeignerId => 'Cédula de extranjería',
            self::IdentityCard => 'Tarjeta de identidad',
            self::Passport => 'Pasaporte',
            self::TemporaryProtectionPermit => 'Permiso por protección temporal',
        };
    }
}
