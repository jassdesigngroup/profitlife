<?php

namespace App\Domain\Consents\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum ConsentMethod: string
{
    use HasLabel;

    case Digital = 'digital';
    case Paper = 'paper';

    public function label(): string
    {
        return match ($this) {
            self::Digital => 'Digital',
            self::Paper => 'Papel (escaneado)',
        };
    }
}
