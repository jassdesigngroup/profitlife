<?php

namespace App\Domain\Training\Notifications;

use App\Domain\Settings\Services\Settings;
use App\Domain\Training\Models\TrainingProgram;
use App\Domain\Training\Services\ProgramPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Envía al cliente su programa en PDF adjunto.
 */
class TrainingProgramNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly TrainingProgram $program) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pdf = app(ProgramPdf::class);
        $brand = app(Settings::class)->brandName();

        return (new MailMessage)
            ->subject("Tu programa «{$this->program->name}» · {$brand}")
            ->greeting('¡Hola, '.($notifiable->first_name ?? '').'!')
            ->line("Te enviamos tu programa {$this->program->name} en PDF.")
            ->line('Si tienes dudas sobre algún ejercicio, consulta a tu entrenador antes de hacerlo.')
            ->attachData($pdf->render($this->program), $pdf->filename($this->program), ['mime' => 'application/pdf']);
    }
}
