<?php

namespace App\Domain\CheckIns\Notifications;

use App\Domain\CheckIns\Models\MemberAccessCredential;
use App\Domain\Settings\Services\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Envía al cliente un enlace firmado para ver su código QR de acceso. El
 * correo no lleva el código: si se genera uno nuevo, el enlace deja de servir.
 */
class MemberAccessCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const LINK_DAYS = 30;

    public readonly string $url;

    public function __construct(MemberAccessCredential $credential)
    {
        $this->url = URL::temporarySignedRoute('access-code.show', now()->addDays(self::LINK_DAYS), ['credential' => $credential->id]);
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

        return (new MailMessage)
            ->subject("Tu código de acceso a {$brand}")
            ->greeting('¡Hola, '.($notifiable->first_name ?? '').'!')
            ->line('Con este código QR registras tu ingreso en el kiosco de la entrada.')
            ->action('Ver mi código de acceso', $this->url)
            ->line('Guárdalo en tu celular (captura de pantalla) o imprímelo. El enlace vence en '.self::LINK_DAYS.' días.')
            ->line('No lo compartas: es personal.');
    }
}
