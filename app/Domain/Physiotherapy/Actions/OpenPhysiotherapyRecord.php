<?php

namespace App\Domain\Physiotherapy\Actions;

use App\Domain\Appointments\Enums\AppointmentStatus;
use App\Domain\Appointments\Models\Appointment;
use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Physiotherapy\Enums\ClinicalAction;
use App\Domain\Physiotherapy\Enums\RecordStatus;
use App\Domain\Physiotherapy\Models\PhysiotherapyRecord;
use App\Domain\Physiotherapy\Services\ClinicalAccess;
use App\Domain\Physiotherapy\Services\ClinicalTeam;
use App\Domain\Staff\Models\Staff;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Abre la historia clínica. Quien la abre queda como responsable; también
 * entran al equipo los profesionales con citas clínicas del paciente.
 */
class OpenPhysiotherapyRecord
{
    public function __construct(
        private readonly ClinicalTeam $team,
        private readonly ClinicalAccess $access,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{reason_for_consultation: ?string, medical_history: ?string, medications: ?string, allergies: ?string}  $data
     */
    public function execute(Member $member, User $actor, array $data): PhysiotherapyRecord
    {
        $staff = $actor->staff ?? throw ValidationException::withMessages(['record' => 'Solo un profesional puede abrir la historia clínica.']);

        return DB::transaction(function () use ($member, $actor, $staff, $data) {
            if (PhysiotherapyRecord::query()->where('member_id', $member->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['record' => 'El cliente ya tiene historia clínica.']);
            }

            $record = PhysiotherapyRecord::query()->create([
                'member_id' => $member->id,
                'primary_staff_id' => $staff->id,
                'status' => RecordStatus::Active,
                'reason_for_consultation' => $data['reason_for_consultation'] ?: null,
                'medical_history' => $data['medical_history'] ?: null,
                'medications' => $data['medications'] ?: null,
                'allergies' => $data['allergies'] ?: null,
                'opened_at' => Date::now(),
                'opened_by' => $actor->id,
            ]);

            $this->team->add($record, $staff, $actor);

            $withAppointments = Appointment::query()->withoutGlobalScopes()
                ->where('member_id', $member->id)
                ->whereIn('status', [...AppointmentStatus::activeValues(), AppointmentStatus::Completed->value])
                ->whereHas('service', fn ($q) => $q->where('is_clinical', true))
                ->distinct()
                ->pluck('staff_id');

            foreach (Staff::query()->withoutGlobalScopes()->whereIn('id', $withAppointments)->get() as $other) {
                $this->team->add($record, $other, $actor);
            }

            $this->access->log($actor, $member->id, $record, ClinicalAction::Create);
            $this->audit->log('clinical', AuditEvent::ClinicalRecordOpened, $record, $actor, ['member_id' => $member->id]);

            return $record;
        });
    }
}
