<?php

namespace App\Domain\Appointments\Actions;

use App\Domain\Appointments\Enums\AppointmentStatus;
use App\Domain\Appointments\Models\Appointment;
use App\Domain\Appointments\Notifications\AppointmentNotification;
use App\Domain\Appointments\Services\AppointmentStatusChanger;
use App\Domain\Appointments\Services\SessionLedger;
use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Billing\Actions\VoidInvoice;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\Services\Settings;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancela una cita. A tiempo (antes del límite de horas) se devuelve la
 * sesión y se anula el comprobante sin pagos; tarde, la sesión se conserva
 * descontada (gerencia puede devolverla).
 */
class CancelAppointment
{
    public function __construct(
        private readonly AppointmentStatusChanger $statuses,
        private readonly SessionLedger $ledger,
        private readonly VoidInvoice $voidInvoice,
        private readonly AuditLogger $audit,
        private readonly Settings $settings,
    ) {}

    public function isLate(Appointment $appointment): bool
    {
        return Date::now()->addHours($this->settings->cancellationHours())->greaterThan($appointment->starts_at);
    }

    public function execute(Appointment $appointment, string $reason, User $actor): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['cancelReason' => 'Indique el motivo de la cancelación.']);
        }

        if (! $appointment->isActive()) {
            throw ValidationException::withMessages(['cancelReason' => 'La cita ya no está activa.']);
        }

        $late = $this->isLate($appointment);

        DB::transaction(function () use ($appointment, $reason, $actor, $late) {
            $appointment->forceFill([
                'cancelled_at' => Date::now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => mb_substr($reason, 0, 255),
            ])->save();

            $this->statuses->change($appointment, AppointmentStatus::Cancelled, $reason, $actor);

            $refunded = 0;
            if (! $late) {
                $refunded = $this->ledger->refund($appointment, $actor);

                $invoice = $appointment->invoice();
                if ($invoice !== null && $invoice->paid_cents === 0) {
                    $this->voidInvoice->execute($invoice, 'Cita cancelada', $actor);
                }
            }

            $this->audit->log('appointments', AuditEvent::AppointmentCancelled, $appointment, $actor, [
                'member_id' => $appointment->member_id,
                'late' => $late,
                'credits_refunded' => $refunded,
                'reason' => $reason,
            ]);
        });

        $member = $appointment->member;
        if (filled($member?->email)) {
            $member->notify(new AppointmentNotification($appointment, AppointmentNotification::CANCELLED));
        }
    }
}
