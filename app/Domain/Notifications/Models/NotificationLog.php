<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Trazabilidad de envíos. Solo inserción.
 */
#[Fillable([
    'notifiable_type', 'notifiable_id', 'notification_type', 'channel', 'recipient',
    'status', 'provider_message_id', 'error', 'sent_at',
])]
class NotificationLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => NotificationStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
