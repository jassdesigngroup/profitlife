<?php

namespace App\Domain\Documents\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum DocumentCategory: string
{
    use HasLabel;

    case Contract = 'contract';
    case Consent = 'consent';
    case Medical = 'medical';
    case Identification = 'identification';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Contract => 'Contrato',
            self::Consent => 'Consentimiento',
            self::Medical => 'Médico',
            self::Identification => 'Identificación',
            self::Other => 'Otro',
        };
    }

    /**
     * Los documentos médicos son siempre clínicos.
     */
    public function sensitivity(): DocumentSensitivity
    {
        return $this === self::Medical ? DocumentSensitivity::Clinical : DocumentSensitivity::Administrative;
    }
}
