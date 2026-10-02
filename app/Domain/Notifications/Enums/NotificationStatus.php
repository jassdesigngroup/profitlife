<?php

namespace App\Domain\Notifications\Enums;

use App\Domain\Shared\Enums\HasLabel;

enum NotificationStatus: string
{
    use HasLabel;

    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'En cola',
            self::Sent => 'Enviada',
            self::Failed => 'Fallida',
        };
    }
}
