<?php

namespace App\Domain\Appointments\Actions;

use App\Domain\Appointments\Enums\AppointmentStatus;
use App\Domain\Appointments\Models\Appointment;
use App\Domain\Appointments\Notifications\AppointmentNotification;
use App\Domain\Appointments\Services\AppointmentStatusChanger;
use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Staff\Models\Staff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reprograma una cita: la original queda "reprogramada" y se crea una
 * nueva enlazada (rescheduled_from_id) que hereda su forma de pago.
 */
class RescheduleAppointment
{
    public function __construct(
        private readonly BookAppointment $book,
        private readonly AppointmentStatusChanger $statuses,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Appointment $appointment, Staff $staff, CarbonImmutable $startsAt, User $actor, ?string $reason = null, ?int $roomId = null): Appointment
    {
        if (! $appointment->isActive()) {
            throw ValidationException::withMessages(['startsAt' => 'Solo se reprograman citas pendientes o confirmadas.']);
        }

        $new = DB::transaction(function () use ($appointment, $staff, $startsAt, $actor, $reason, $roomId) {
            $this->statuses->change($appointment, AppointmentStatus::Rescheduled, $reason ?: 'Reprogramada', $actor);

            $new = $this->book->execute(
                $appointment->member,
                $appointment->service,
                $appointment->location,
                $staff,
                $startsAt,
                $actor,
                $roomId,
                $appointment->notes,
                rescheduledFrom: $appointment,
            );

            $this->audit->log('appointments', AuditEvent::AppointmentRescheduled, $new, $actor, [
                'member_id' => $appointment->member_id,
                'from_appointment_id' => $appointment->id,
                'from' => $appointment->starts_at->toIso8601String(),
                'to' => $startsAt->toIso8601String(),
                'staff_id' => $staff->id,
            ]);

            return $new;
        });

        $member = $appointment->member;
        if (filled($member?->email)) {
            $member->notify(new AppointmentNotification($new, AppointmentNotification::RESCHEDULED));
        }

        return $new;
    }
}
