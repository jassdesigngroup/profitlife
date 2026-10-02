<?php

namespace App\Domain\Staff\Actions;

use App\Domain\Appointments\Models\Appointment;
use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Staff\Models\Staff;
use App\Domain\Staff\Models\StaffTimeOff;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Registra una ausencia. Devuelve cuántas citas activas quedan dentro de
 * ella, para avisar que hay que reprogramarlas.
 */
class SaveTimeOff
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(Staff $staff, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?string $reason, User $actor): int
    {
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages(['offUntil' => 'El fin debe ser posterior al inicio.']);
        }

        $off = StaffTimeOff::query()->create([
            'staff_id' => $staff->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => $reason,
            'created_by' => $actor->id,
        ]);

        // El motivo puede ser de salud: no va a la auditoría.
        $this->audit->log('staff', AuditEvent::TimeOffSaved, $staff, $actor, ['time_off_id' => $off->id]);

        return Appointment::query()->withoutGlobalScopes()
            ->where('staff_id', $staff->id)
            ->active()
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->count();
    }
}
