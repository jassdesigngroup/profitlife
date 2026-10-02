<?php

namespace App\Domain\Physiotherapy\Actions;

use App\Domain\Appointments\Models\Appointment;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Physiotherapy\Enums\ClinicalAction;
use App\Domain\Physiotherapy\Enums\SessionType;
use App\Domain\Physiotherapy\Models\ClinicalNote;
use App\Domain\Physiotherapy\Models\PhysiotherapyRecord;
use App\Domain\Physiotherapy\Models\PhysiotherapySession;
use App\Domain\Physiotherapy\Models\TreatmentPlan;
use App\Domain\Physiotherapy\Services\ClinicalAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra una sesión con su nota (sin firmar). La nota usa la plantilla
 * del tipo de sesión: evaluación inicial o SOAP.
 */
class RecordPhysiotherapySession
{
    public function __construct(private readonly ClinicalAccess $access) {}

    /**
     * @param  array{session_type: SessionType, performed_at: CarbonImmutable, pain_scale: ?int, treatment_plan_id: ?int, appointment_id: ?int, location_id: int, summary_for_member: ?string, sections: array<string, ?string>}  $data
     */
    public function execute(PhysiotherapyRecord $record, User $actor, array $data): ClinicalNote
    {
        $staff = $actor->staff ?? throw ValidationException::withMessages(['session' => 'Solo un profesional registra sesiones.']);

        if ($data['performed_at']->isFuture()) {
            throw ValidationException::withMessages(['performedAt' => 'La sesión no puede ser futura.']);
        }

        if ($data['treatment_plan_id'] !== null
            && ! TreatmentPlan::query()->where('physiotherapy_record_id', $record->id)->whereKey($data['treatment_plan_id'])->exists()) {
            throw ValidationException::withMessages(['planId' => 'Plan de tratamiento no válido.']);
        }

        if ($data['appointment_id'] !== null) {
            $appointment = Appointment::query()->withoutGlobalScopes()->whereKey($data['appointment_id'])->where('member_id', $record->member_id)->first();
            if ($appointment === null || PhysiotherapySession::query()->where('appointment_id', $appointment->id)->exists()) {
                throw ValidationException::withMessages(['appointmentId' => 'La cita no es válida o ya tiene una sesión registrada.']);
            }
        }

        Location::query()->withoutGlobalScopes()->findOrFail($data['location_id']);

        $sections = $this->sections($data['session_type'], $data['sections']);

        return DB::transaction(function () use ($record, $actor, $staff, $data, $sections) {
            $session = PhysiotherapySession::query()->create([
                'physiotherapy_record_id' => $record->id,
                'treatment_plan_id' => $data['treatment_plan_id'],
                'appointment_id' => $data['appointment_id'],
                'staff_id' => $staff->id,
                'location_id' => $data['location_id'],
                'session_type' => $data['session_type'],
                'performed_at' => $data['performed_at'],
                'pain_scale' => $data['pain_scale'],
                'summary_for_member' => $data['summary_for_member'] ?: null,
            ]);

            $note = ClinicalNote::query()->create([
                'physiotherapy_record_id' => $record->id,
                'physiotherapy_session_id' => $session->id,
                'author_id' => $staff->id,
                'type' => $data['session_type']->noteType(),
                'body' => $sections,
            ]);

            $this->access->log($actor, $record->member_id, $session, ClinicalAction::Create);

            return $note;
        });
    }

    /**
     * Solo las secciones de la plantilla, sin texto vacío. Exige al menos una.
     *
     * @param  array<string, ?string>  $input
     * @return array<string, string>
     */
    public static function sections(SessionType $type, array $input): array
    {
        $sections = [];
        foreach (array_keys($type->noteType()->sections()) as $key) {
            $value = trim((string) ($input[$key] ?? ''));
            if ($value !== '') {
                $sections[$key] = $value;
            }
        }

        if ($sections === []) {
            throw ValidationException::withMessages(['sections' => 'Escriba al menos una sección de la nota.']);
        }

        return $sections;
    }
}
