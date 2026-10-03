<?php

namespace App\Livewire\Admin\Members;

use App\Domain\Members\Models\Member;
use App\Domain\Physiotherapy\Models\PhysiotherapyRecord;
use App\Domain\Training\Actions\DuplicateTrainingProgram;
use App\Domain\Training\Actions\SaveTrainingProgram;
use App\Domain\Training\Enums\ProgramStatus;
use App\Domain\Training\Enums\ProgramType;
use App\Domain\Training\Models\Exercise;
use App\Domain\Training\Models\TrainingProgram;
use App\Domain\Training\Models\WorkoutLog;
use App\Domain\Training\Services\ExerciseProgress;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use App\Livewire\Admin\Members\Concerns\ResolvesMember;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pestaña "Entrenamiento": programas del cliente, historial de
 * entrenamientos registrados y progreso por ejercicio. Los programas de
 * rehabilitación solo aparecen al equipo tratante.
 */
class MemberTraining extends Component
{
    use InteractsWithToasts;
    use ResolvesMember {
        mount as resolveMember;
    }

    // Nuevo programa
    public bool $showCreate = false;

    public string $templateId = '';

    public string $name = '';

    public string $goal = '';

    public string $startsOn = '';

    // Copiar a otro cliente
    public bool $showCopy = false;

    #[Locked]
    public ?int $copyProgramId = null;

    public string $copySearch = '';

    // Progreso
    public string $progressExercise = '';

    public function mount(int $memberId): void
    {
        $this->resolveMember($memberId);
        $this->authorize('viewAny', TrainingProgram::class);
    }

    public function openCreate(): void
    {
        $this->authorize('create', [TrainingProgram::class, $this->member()]);
        $this->reset(['templateId', 'name', 'goal']);
        $this->startsOn = BusinessDate::today()->toDateString();
        $this->resetValidation();
        $this->showCreate = true;
    }

    public function create(SaveTrainingProgram $save, DuplicateTrainingProgram $duplicate): void
    {
        $member = $this->member();
        $this->authorize('create', [TrainingProgram::class, $member]);

        $this->validate([
            'templateId' => ['nullable', 'integer', Rule::exists('training_programs', 'id')->where('is_template', true)->whereNull('deleted_at')],
            'name' => [$this->templateId === '' ? 'required' : 'nullable', 'string', 'max:150'],
            'goal' => ['nullable', 'string', 'max:2000'],
            'startsOn' => ['nullable', 'date_format:Y-m-d'],
        ], [], ['templateId' => 'plantilla', 'name' => 'nombre', 'goal' => 'objetivo', 'startsOn' => 'inicio']);

        $startsOn = $this->startsOn ? CarbonImmutable::createFromFormat('!Y-m-d', $this->startsOn) : null;

        if ($this->templateId !== '') {
            $template = TrainingProgram::query()->where('is_template', true)->findOrFail((int) $this->templateId);
            $this->authorize('view', $template);
            $program = $duplicate->execute($template, $member, auth()->user(), $this->name ?: null);
            $program->update(array_filter([
                'goal' => $this->goal ?: null,
                'starts_on' => $startsOn?->toDateString(),
            ]));
        } else {
            $program = $save->create($member, ProgramType::Training, [
                'name' => $this->name,
                'goal' => $this->goal,
                'starts_on' => $startsOn,
                'ends_on' => null,
                'status' => ProgramStatus::Active,
            ], auth()->user());
        }

        $this->redirectRoute('admin.training.programs.edit', $program, navigate: true);
    }

    public function openCopy(int $programId): void
    {
        $program = $this->ownProgram($programId);
        $this->authorize('view', $program);
        $this->authorize('create', TrainingProgram::class);
        $this->copyProgramId = $program->id;
        $this->copySearch = '';
        $this->showCopy = true;
    }

    public function copyTo(int $targetId, DuplicateTrainingProgram $duplicate): void
    {
        $source = $this->ownProgram((int) $this->copyProgramId);
        $this->authorize('view', $source);
        // LocationScope: solo clientes que este usuario puede ver.
        $target = Member::query()->findOrFail($targetId);
        $this->authorize('create', [TrainingProgram::class, $target]);

        $copy = $duplicate->execute($source, $target, auth()->user());

        session()->flash('success', "Programa copiado a {$target->full_name}.");
        $this->redirectRoute('admin.training.programs.edit', $copy, navigate: true);
    }

    public function delete(int $programId): void
    {
        $program = $this->ownProgram($programId);
        $this->authorize('delete', $program);

        $program->delete();
        $this->toast("Programa {$program->name} eliminado.", 'warning');
    }

    public function render(ExerciseProgress $progress): View
    {
        $member = $this->member();
        $this->authorize('viewAny', TrainingProgram::class);
        $user = auth()->user();

        $record = PhysiotherapyRecord::query()->where('member_id', $member->id)->first();
        $includeRehab = $record !== null && $user->can('view', $record);

        $programs = TrainingProgram::query()
            ->where('member_id', $member->id)
            ->when(! $includeRehab, fn ($q) => $q->where('type', ProgramType::Training))
            ->with('staff:id,first_name,last_name')
            ->withCount('workouts')
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->latest('id')
            ->get()
            ->filter(fn (TrainingProgram $p) => $user->can('view', $p));

        $logs = WorkoutLog::query()
            ->where('member_id', $member->id)
            ->whereHas('workout', fn ($w) => $w->whereIn('training_program_id', $programs->pluck('id')))
            ->with(['workout:id,name,training_program_id', 'logger:id,name'])
            ->withCount('sets')
            ->latest('performed_at')
            ->limit(15)
            ->get();

        $exercises = $progress->exercisesFor($member, $includeRehab);
        $selected = $this->progressExercise !== '' ? $exercises->firstWhere('id', (int) $this->progressExercise) : $exercises->first();
        $series = $selected ? $progress->seriesFor($member, Exercise::withTrashed()->findOrFail($selected->id), $includeRehab) : collect();

        return view('livewire.admin.members.training', [
            'member' => $member,
            'programs' => $programs,
            'programNames' => $programs->pluck('name', 'id'),
            'logs' => $logs,
            'exercises' => $exercises,
            'selected' => $selected,
            'series' => $series,
            'templates' => $this->showCreate ? TrainingProgram::query()->where('is_template', true)->where('status', '!=', ProgramStatus::Archived)->orderBy('name')->pluck('name', 'id') : collect(),
            'copyResults' => $this->showCopy && mb_strlen(trim($this->copySearch)) >= 2
                ? Member::query()->search($this->copySearch)->whereKeyNot($member->id)->orderBy('first_name')->limit(10)->get(['id', 'first_name', 'last_name', 'member_number'])
                : collect(),
        ]);
    }

    private function ownProgram(int $id): TrainingProgram
    {
        return TrainingProgram::query()->where('member_id', $this->memberId)->findOrFail($id);
    }
}
