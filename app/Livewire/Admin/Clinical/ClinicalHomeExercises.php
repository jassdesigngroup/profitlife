<?php

namespace App\Livewire\Admin\Clinical;

use App\Domain\Physiotherapy\Models\PhysiotherapyRecord;
use App\Domain\Physiotherapy\Models\TreatmentPlan;
use App\Domain\Training\Actions\SaveTrainingProgram;
use App\Domain\Training\Enums\ProgramStatus;
use App\Domain\Training\Enums\ProgramType;
use App\Domain\Training\Models\TrainingProgram;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Ejercicios para casa: programas de tipo rehabilitación vinculados a un
 * plan de tratamiento. Solo para el equipo tratante.
 */
class ClinicalHomeExercises extends Component
{
    #[Locked]
    public int $recordId;

    public bool $showForm = false;

    public string $name = '';

    public string $goal = '';

    public string $planId = '';

    public function mount(int $recordId): void
    {
        $this->recordId = $recordId;
        $this->authorize('view', $this->record());
        $this->authorize('viewAny', TrainingProgram::class);
    }

    public function create(): void
    {
        $record = $this->record();
        $this->authorize('create', [TrainingProgram::class, $record->member, ProgramType::Rehab]);
        $this->reset(['name', 'goal']);
        $this->planId = (string) ($record->plans()->where('status', 'active')->latest('starts_on')->value('id') ?? '');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(SaveTrainingProgram $save): void
    {
        $record = $this->record();
        $member = $record->member;
        $this->authorize('create', [TrainingProgram::class, $member, ProgramType::Rehab]);

        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'goal' => ['nullable', 'string', 'max:2000'],
            'planId' => ['nullable', 'integer', Rule::exists('treatment_plans', 'id')->where('physiotherapy_record_id', $record->id)],
        ], [], ['name' => 'nombre', 'goal' => 'indicaciones', 'planId' => 'plan de tratamiento']);

        $program = $save->create($member, ProgramType::Rehab, [
            'name' => $this->name,
            'goal' => $this->goal,
            'starts_on' => CarbonImmutable::parse(BusinessDate::today()->toDateString()),
            'ends_on' => null,
            'status' => ProgramStatus::Active,
            'treatment_plan_id' => $this->planId === '' ? null : (int) $this->planId,
        ], auth()->user());

        $this->redirectRoute('admin.training.programs.edit', $program, navigate: true);
    }

    public function render(): View
    {
        $record = $this->record();
        $this->authorize('view', $record);

        return view('livewire.admin.clinical.home-exercises', [
            'record' => $record,
            'programs' => TrainingProgram::query()
                ->where('member_id', $record->member_id)
                ->where('type', ProgramType::Rehab)
                ->with('treatmentPlan:id,title')
                ->withCount('workouts')
                ->latest('id')
                ->get(),
            'plans' => $this->showForm ? TreatmentPlan::query()->where('physiotherapy_record_id', $record->id)->latest('starts_on')->pluck('title', 'id') : collect(),
        ]);
    }

    private function record(): PhysiotherapyRecord
    {
        return PhysiotherapyRecord::query()->findOrFail($this->recordId);
    }
}
