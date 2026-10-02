<?php

namespace App\Domain\Appointments\Services;

use App\Domain\Appointments\Enums\AppointmentStatus;
use App\Domain\Appointments\Models\Appointment;
use App\Domain\Appointments\Models\AppointmentStatusHistory;
use App\Domain\Identity\Models\User;

class AppointmentStatusChanger
{
    public function change(Appointment $appointment, AppointmentStatus $to, ?string $reason, ?User $actor): void
    {
        $from = $appointment->status;

        $appointment->forceFill(['status' => $to])->save();

        $this->record($appointment, $reason, $actor, $from);
    }

    public function record(Appointment $appointment, ?string $reason, ?User $actor, ?AppointmentStatus $from = null): void
    {
        AppointmentStatusHistory::query()->create([
            'appointment_id' => $appointment->id,
            'from_status' => $from,
            'to_status' => $appointment->status,
            'reason' => $reason === null ? null : mb_substr($reason, 0, 255),
            'changed_by' => $actor?->id,
        ]);
    }
}
