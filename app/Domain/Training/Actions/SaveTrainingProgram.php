<?php

namespace App\Domain\Training\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Physiotherapy\Models\PhysiotherapyRecord;
use App\Domain\Physiotherapy\Models\TreatmentPlan;
use App\Domain\Training\Enums\ProgramStatus;
use App\Domain\Training\Enums\ProgramType;
use App\Domain\Training\Models\TrainingProgram;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Datos generales de un programa (o plantilla, sin cliente). La estructura
 * de rutinas se guarda con SaveProgramStructure.
 */
class SaveTrainingProgram
{
    /**
     * @param  array{name: string, goal: ?string, starts_on: ?CarbonImmutable, ends_on: ?CarbonImmutable, status: ProgramStatus, treatment_plan_id?: ?int}  $data
     */
    public function create(?Member $member, ProgramType $type, array $data, User $actor): TrainingProgram
    {
        $planId = $data['treatment_plan_id'] ?? null;

        if ($type === ProgramType::Rehab) {
            $record = $member ? PhysiotherapyRecord::query()->where('member_id', $member->id)->first() : null;
            if ($record === null) {
                throw ValidationException::withMessages(['name' => 'El programa de rehabilitación necesita la historia clínica del paciente.']);
            }
            if ($planId !== null && ! TreatmentPlan::query()->where('physiotherapy_record_id', $record->id)->whereKey($planId)->exists()) {
                throw ValidationException::withMessages(['treatmentPlanId' => 'Plan de tratamiento no válido.']);
            }
        } else {
            $planId = null;
        }

        return TrainingProgram::query()->create([
            'member_id' => $member?->id,
            'staff_id' => $actor->staff->id,
            'treatment_plan_id' => $planId,
            'type' => $type,
            'is_template' => $member === null,
            'name' => $data['name'],
            'goal' => $data['goal'] ?: null,
            'starts_on' => $member ? $data['starts_on']?->toDateString() : null,
            'ends_on' => $member ? $data['ends_on']?->toDateString() : null,
            'status' => $data['status'],
        ]);
    }

    /**
     * @param  array{name: string, goal: ?string, starts_on: ?CarbonImmutable, ends_on: ?CarbonImmutable, status: ProgramStatus}  $data
     */
    public function update(TrainingProgram $program, array $data): void
    {
        $program->update([
            'name' => $data['name'],
            'goal' => $data['goal'] ?: null,
            'starts_on' => $program->is_template ? null : $data['starts_on']?->toDateString(),
            'ends_on' => $program->is_template ? null : $data['ends_on']?->toDateString(),
            'status' => $data['status'],
        ]);
    }
}
