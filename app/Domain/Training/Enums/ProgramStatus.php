<?php

namespace App\Domain\Training\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum ProgramStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Active => 'Activo',
            self::Completed => 'Completado',
            self::Archived => 'Archivado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Active => 'success',
            self::Completed => 'info',
            self::Archived => 'neutral',
        };
    }
}
