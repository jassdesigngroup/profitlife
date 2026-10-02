<?php

namespace App\Domain\Members\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum Gender: string
{
    use HasLabel;

    case Female = 'female';
    case Male = 'male';
    case NonBinary = 'non_binary';
    case Other = 'other';
    case Undisclosed = 'undisclosed';

    public function label(): string
    {
        return match ($this) {
            self::Female => 'Femenino',
            self::Male => 'Masculino',
            self::NonBinary => 'No binario',
            self::Other => 'Otro',
            self::Undisclosed => 'Prefiere no decirlo',
        };
    }
}
