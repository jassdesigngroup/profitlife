<?php

namespace App\Domain\Appointments\Actions;

use App\Domain\Appointments\Enums\AppointmentStatus;
use App\Domain\Appointments\Models\Appointment;
use App\Domain\Appointments\Services\AppointmentStatusChanger;
use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;

/**
 * Marca una cita como atendida o como inasistencia. La inasistencia
 * conserva la sesión descontada y el comprobante.
 */
class MarkAppointment
{
    public function __construct(
        private readonly AppointmentStatusChanger $statuses,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Appointment $appointment, AppointmentStatus $to, User $actor): void
    {
        if (! in_array($to, [AppointmentStatus::Completed, AppointmentStatus::NoShow], true)) {
            throw ValidationException::withMessages(['status' => 'Estado no permitido.']);
        }

        if (! $appointment->isActive()) {
            throw ValidationException::withMessages(['status' => 'La cita ya no está activa.']);
        }

        if ($appointment->starts_at->greaterThan(Date::now())) {
            throw ValidationException::withMessages(['status' => 'La cita todavía no ha empezado.']);
        }

        $this->statuses->change($appointment, $to, null, $actor);

        $this->audit->log('appointments', AuditEvent::AppointmentStatusChanged, $appointment, $actor, [
            'member_id' => $appointment->member_id,
            'to' => $to->value,
        ]);
    }
}
