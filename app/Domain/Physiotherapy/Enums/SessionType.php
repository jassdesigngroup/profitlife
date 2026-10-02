<?php

namespace App\Domain\Physiotherapy\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum SessionType: string
{
    use HasLabel;

    case InitialEvaluation = 'initial_evaluation';
    case Treatment = 'treatment';
    case FollowUp = 'follow_up';
    case Discharge = 'discharge';

    public function label(): string
    {
        return match ($this) {
            self::InitialEvaluation => 'Evaluación inicial',
            self::Treatment => 'Sesión de tratamiento',
            self::FollowUp => 'Control',
            self::Discharge => 'Alta',
        };
    }

    /**
     * Tipo de nota que acompaña a la sesión.
     */
    public function noteType(): NoteType
    {
        return $this === self::InitialEvaluation ? NoteType::Evaluation : NoteType::Soap;
    }
}
