<?php

namespace App\Domain\Staff\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Staff\Models\Staff;
use App\Domain\Staff\Models\StaffSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reemplaza la disponibilidad semanal de un profesional en las sedes que
 * administra quien la edita (las demás sedes no se tocan).
 */
class SaveStaffSchedules
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<array{location_id: int, day_of_week: int, starts_at: string, ends_at: string}>  $rows
     * @param  list<int>  $editableLocationIds
     * @param  list<array{location_id: int, day_of_week: int, starts_at: string, ends_at: string}>  $kept  franjas de otras sedes que se conservan
     */
    public function execute(Staff $staff, array $rows, array $editableLocationIds, User $actor, array $kept = []): void
    {
        $staffLocations = $staff->locationIds();

        foreach ($rows as $i => $row) {
            if (! in_array($row['location_id'], $editableLocationIds, true) || ! in_array($row['location_id'], $staffLocations, true)) {
                throw ValidationException::withMessages(["schedules.{$i}.location_id" => 'Sede no válida para este profesional.']);
            }

            if ($row['starts_at'] >= $row['ends_at']) {
                throw ValidationException::withMessages(["schedules.{$i}.ends_at" => 'La hora final debe ser posterior a la inicial.']);
            }
        }

        // Una persona no puede estar en dos franjas a la vez (ni en dos sedes).
        $byDay = collect([...$rows, ...$kept])->groupBy('day_of_week');
        foreach ($byDay as $day => $blocks) {
            $sorted = $blocks->sortBy('starts_at')->values();
            for ($i = 1; $i < $sorted->count(); $i++) {
                if ($sorted[$i]['starts_at'] < $sorted[$i - 1]['ends_at']) {
                    throw ValidationException::withMessages(['schedules' => 'Hay franjas que se cruzan el mismo día.']);
                }
            }
        }

        DB::transaction(function () use ($staff, $rows, $editableLocationIds, $actor) {
            StaffSchedule::query()->where('staff_id', $staff->id)->whereIn('location_id', $editableLocationIds)->delete();

            foreach ($rows as $row) {
                StaffSchedule::query()->create(['staff_id' => $staff->id] + $row);
            }

            $this->audit->log('staff', AuditEvent::ScheduleUpdated, $staff, $actor, [
                'locations' => $editableLocationIds,
                'blocks' => count($rows),
            ]);
        });
    }
}
