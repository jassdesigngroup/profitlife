<?php

namespace App\Domain\Physiotherapy\Notifications;

use App\Domain\Identity\Models\User;
use App\Domain\Physiotherapy\Models\PhysiotherapyRecord;
use App\Domain\Settings\Services\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al profesional responsable de que alguien abrió la historia de su
 * paciente con acceso de emergencia. No incluye datos clínicos.
 */
class EmergencyAccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PhysiotherapyRecord $record,
        public readonly User $actor,
        public readonly string $reason,
    ) {}

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
        $member = $this->record->member;

        return (new MailMessage)
            ->subject("Acceso de emergencia a una historia clínica · {$brand}")
            ->line("{$this->actor->name} abrió con acceso de emergencia la historia clínica de {$member?->full_name} ({$member?->member_number}).")
            ->line("Motivo indicado: {$this->reason}")
            ->action('Ver la historia', route('admin.clinical.show', $this->record->member_id))
            ->line('Si no reconoce este acceso, informe a la dirección.');
    }
}
