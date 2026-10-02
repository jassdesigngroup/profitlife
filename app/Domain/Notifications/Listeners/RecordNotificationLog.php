<?php

namespace App\Domain\Notifications\Listeners;

use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Models\NotificationLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\SentMessage;
use Throwable;

/**
 * Deja una fila en notification_logs por cada envío (exitoso o fallido)
 * a un notificable Eloquent. El canal `database` (campana) no se registra.
 */
class RecordNotificationLog
{
    public function handleSent(NotificationSent $event): void
    {
        $providerId = $event->response instanceof SentMessage ? $event->response->getMessageId() : null;

        $this->record($event->notifiable, $event->notification, $event->channel, NotificationStatus::Sent, $providerId);
    }

    public function handleFailed(NotificationFailed $event): void
    {
        $error = $event->data['exception'] ?? null;
        $message = $error instanceof Throwable ? $error::class.': '.$error->getMessage() : 'Error desconocido';

        $this->record($event->notifiable, $event->notification, $event->channel, NotificationStatus::Failed, null, $message);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            NotificationSent::class => 'handleSent',
            NotificationFailed::class => 'handleFailed',
        ];
    }

    private function record(
        mixed $notifiable,
        Notification $notification,
        string $channel,
        NotificationStatus $status,
        ?string $providerId,
        ?string $error = null,
    ): void {
        if (! $notifiable instanceof Model || $channel === 'database') {
            return;
        }

        $recipient = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor($channel, $notification)
            : null;

        if (is_array($recipient)) {
            $recipient = implode(',', array_keys($recipient) === range(0, count($recipient) - 1) ? $recipient : array_keys($recipient));
        }

        NotificationLog::query()->create([
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'notification_type' => Str::limit(Str::snake(class_basename($notification)), 60, ''),
            'channel' => $channel,
            'recipient' => Str::limit((string) $recipient, 190, ''),
            'status' => $status,
            'provider_message_id' => $providerId === null ? null : Str::limit($providerId, 120, ''),
            'error' => $error,
            'sent_at' => $status === NotificationStatus::Sent ? now() : null,
        ]);
    }
}
