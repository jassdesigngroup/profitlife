<?php

namespace App\Domain\Consents\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum ConsentType: string
{
    use HasLabel;

    case DataProcessing = 'data_processing';
    case ClinicalTreatment = 'clinical_treatment';
    case ImageUse = 'image_use';
    case Liability = 'liability';

    public function label(): string
    {
        return match ($this) {
            self::DataProcessing => 'Tratamiento de datos personales',
            self::ClinicalTreatment => 'Tratamiento clínico',
            self::ImageUse => 'Uso de imagen',
            self::Liability => 'Exoneración de responsabilidad',
        };
    }
}
