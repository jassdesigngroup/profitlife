<?php

namespace App\Domain\Notifications\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum NotificationChannel: string
{
    use HasLabel;

    case Mail = 'mail';
    case Sms = 'sms';
    case Whatsapp = 'whatsapp';
    case Database = 'database';

    public function label(): string
    {
        return match ($this) {
            self::Mail => 'Correo',
            self::Sms => 'SMS',
            self::Whatsapp => 'WhatsApp',
            self::Database => 'Panel',
        };
    }
}
