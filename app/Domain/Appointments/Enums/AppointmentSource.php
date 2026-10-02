<?php

namespace App\Domain\Appointments\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum AppointmentSource: string
{
    use HasLabel;

    case Admin = 'admin';
    case Portal = 'portal';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Panel',
            self::Portal => 'Portal del cliente',
        };
    }
}
