<?php

namespace App\Domain\Identity\Notifications;

use App\Domain\Settings\Services\Settings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Enlace de recuperación de contraseña, enviado en cola.
 */
class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        $brand = app(Settings::class)->brandName();
        $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject("{$brand}: restablezca su contraseña")
            ->greeting('Hola,')
            ->line('Recibimos una solicitud para restablecer la contraseña de su cuenta.')
            ->action('Restablecer contraseña', $this->resetUrl($notifiable))
            ->line("El enlace vence en {$expire} minutos.")
            ->line('Si usted no hizo la solicitud, ignore este mensaje: su contraseña no cambiará.');
    }
}
