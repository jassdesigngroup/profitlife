<?php

namespace App\Domain\Appointments\Actions;

use App\Domain\Appointments\Models\Appointment;
use App\Domain\Appointments\Services\SessionLedger;
use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Devuelve la sesión descontada por una cancelación tardía o una
 * inasistencia. La Policy Appointment@refundCredit la aplica quien llama.
 */
class RefundAppointmentCredit
{
    public function __construct(
        private readonly SessionLedger $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Appointment $appointment, string $reason, User $actor): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['refundReason' => 'Indique el motivo de la devolución.']);
        }

        DB::transaction(function () use ($appointment, $reason, $actor) {
            $refunded = $this->ledger->refund($appointment, $actor);

            if ($refunded === 0) {
                throw ValidationException::withMessages(['refundReason' => 'Esta cita no tiene sesiones por devolver.']);
            }

            $this->audit->log('appointments', AuditEvent::CreditsAdjusted, $appointment, $actor, [
                'member_id' => $appointment->member_id,
                'service_id' => $appointment->service_id,
                'delta' => $refunded,
                'reason' => $reason,
            ]);
        });
    }
}
