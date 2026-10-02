<?php

namespace App\Domain\Physiotherapy\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum ClinicalAction: string
{
    use HasLabel;

    case View = 'view';
    case Create = 'create';
    case Update = 'update';
    case Sign = 'sign';
    case Download = 'download';
    case Export = 'export';
    case Emergency = 'emergency';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Consulta',
            self::Create => 'Creación',
            self::Update => 'Modificación',
            self::Sign => 'Firma',
            self::Download => 'Descarga',
            self::Export => 'Exportación',
            self::Emergency => 'Acceso de emergencia',
        };
    }
}
