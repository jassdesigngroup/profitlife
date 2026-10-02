<?php

namespace App\Domain\Appointments\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum ServiceCategory: string
{
    use HasLabel;

    case Physiotherapy = 'physiotherapy';
    case PersonalTraining = 'personal_training';
    case Assessment = 'assessment';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Physiotherapy => 'Fisioterapia',
            self::PersonalTraining => 'Entrenamiento personal',
            self::Assessment => 'Valoración',
            self::Other => 'Otro',
        };
    }
}
