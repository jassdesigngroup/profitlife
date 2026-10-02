<?php

namespace App\Domain\Staff\Notifications;

use App\Domain\Identity\Models\User;
use App\Domain\Settings\Services\Settings;
use App\Domain\Staff\Services\InvitationUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitación al panel. El enlace se firma al crear la notificación, de modo
 * que la caducidad cuenta desde el momento del envío a la cola.
 */
class StaffInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public readonly string $url;

    public function __construct(User $user)
    {
        $this->url = InvitationUrl::for($user);
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brand = app(Settings::class)->brandName();
        $hours = config('profitlife.invitations.expires_hours');

        return (new MailMessage)
            ->subject("Invitación al panel de {$brand}")
            ->greeting('¡Hola!')
            ->line("Le crearon una cuenta en el panel de {$brand}.")
            ->line('Para activarla, defina su contraseña con el siguiente botón.')
            ->action('Definir mi contraseña', $this->url)
            ->line("El enlace vence en {$hours} horas. Si vence, pida que le reenvíen la invitación.");
    }
}
