<?php

namespace App\Domain\Appointments\Notifications;

use App\Domain\Appointments\Models\Appointment;
use App\Domain\Settings\Services\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo al cliente al agendar, reprogramar o cancelar una cita. No lleva
 * datos clínicos: solo servicio, fecha, profesional y sede.
 */
class AppointmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const BOOKED = 'booked';

    public const RESCHEDULED = 'rescheduled';

    public const CANCELLED = 'cancelled';

    public function __construct(public readonly Appointment $appointment, public readonly string $type) {}

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
        $appointment = $this->appointment->loadMissing(['service', 'staff', 'location']);
        $location = $appointment->location;
        $when = $appointment->starts_at->setTimezone($location->timezone ?: 'UTC')->locale('es')->translatedFormat('l j \d\e F \a \l\a\s g:i a');

        [$subject, $intro] = match ($this->type) {
            self::RESCHEDULED => ["Tu cita en {$brand} cambió de horario", 'Tu cita quedó reprogramada:'],
            self::CANCELLED => ["Tu cita en {$brand} fue cancelada", 'Cancelamos esta cita:'],
            default => ["Tu cita en {$brand} está confirmada", 'Te esperamos:'],
        };

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting('¡Hola, '.($notifiable->first_name ?? '').'!')
            ->line($intro)
            ->line("**{$appointment->service->name}** con {$appointment->staff->full_name}")
            ->line(ucfirst($when))
            ->line("{$location->name} · {$location->address_line}, {$location->city}");

        if ($this->type !== self::CANCELLED) {
            $hours = app(Settings::class)->cancellationHours();
            $mail->line("Si no puedes asistir, avísanos con al menos {$hours} horas de anticipación.");
        }

        return $mail;
    }
}
