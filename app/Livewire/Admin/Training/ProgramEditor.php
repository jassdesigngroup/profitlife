<?php

namespace App\Livewire\Admin\Training;

use App\Domain\Training\Actions\DuplicateTrainingProgram;
use App\Domain\Training\Actions\SaveProgramStructure;
use App\Domain\Training\Actions\SaveTrainingProgram;
use App\Domain\Training\Actions\SendTrainingProgram;
use App\Domain\Training\Enums\ProgramStatus;
use App\Domain\Training\Models\Exercise;
use App\Domain\Training\Models\TrainingProgram;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Editor de un programa (o plantilla): datos generales y estructura de
 * rutinas → ejercicios → series. Se guarda todo junto.
 */
class ProgramEditor extends Component
{
    use InteractsWithToasts;

    #[Locked]
    public int $programId;

    public string $name = '';

    public string $goal = '';

    public string $startsOn = '';

    public string $endsOn = '';

    public string $status = 'active';

    /** @var list<array{id: ?int, name: string, notes: string, exercises: list<array{id: ?int, exercise_id: int, name: string, superset_group: string, notes: string, sets: list<array<string, string>>}>}> */
    public array $workouts = [];

    public bool $dirty = false;

    // Selector de ejercicios
    public bool $showPicker = false;

    #[Locked]
    public ?int $pickerWorkout = null;

    public string $pickerSearch = '';

    // Usar como plantilla
    public bool $showTemplate = false;

    public string $templateName = '';

    public function mount(TrainingProgram $program): void
    {
        $this->authorize('view', $program);
        $this->programId = $program->id;
        $this->loadProgram($program);
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'workouts')) {
            $this->dirty = true;
        }
    }

    public function addWorkout(): void
    {
        $this->authorize('update', $this->program());
        $letter = chr(ord('A') + count($this->workouts) % 26);
        $this->workouts[] = ['id' => null, 'name' => "Día {$letter}", 'notes' => '', 'exercises' => []];
        $this->dirty = true;
    }

    public function removeWorkout(int $w): void
    {
        $this->authorize('update', $this->program());
        unset($this->workouts[$w]);
        $this->workouts = array_values($this->workouts);
        $this->dirty = true;
    }

    public function moveWorkout(int $w, int $direction): void
    {
        $this->authorize('update', $this->program());
        $this->workouts = $this->swap($this->workouts, $w, $w + $direction);
        $this->dirty = true;
    }

    public function openPicker(int $w): void
    {
        $this->authorize('update', $this->program());
        $this->pickerWorkout = $w;
        $this->pickerSearch = '';
        $this->showPicker = true;
    }

    public function pickExercise(int $exerciseId): void
    {
        $this->authorize('update', $this->program());
        $exercise = Exercise::query()->where('is_active', true)->findOrFail($exerciseId);
        $w = (int) $this->pickerWorkout;

        if (! isset($this->workouts[$w])) {
            return;
        }

        $this->workouts[$w]['exercises'][] = [
            'id' => null,
            'exercise_id' => $exercise->id,
            'name' => $exercise->name,
            'superset_group' => '',
            'notes' => '',
            'sets' => array_fill(0, 3, $this->emptySet(['reps' => '10', 'rest_seconds' => '60'])),
        ];
        $this->dirty = true;
        $this->toast("{$exercise->name} agregado.");
    }

    public function removeExercise(int $w, int $e): void
    {
        $this->authorize('update', $this->program());
        unset($this->workouts[$w]['exercises'][$e]);
        $this->workouts[$w]['exercises'] = array_values($this->workouts[$w]['exercises']);
        $this->dirty = true;
    }

    public function moveExercise(int $w, int $e, int $direction): void
    {
        $this->authorize('update', $this->program());
        $this->workouts[$w]['exercises'] = $this->swap($this->workouts[$w]['exercises'], $e, $e + $direction);
        $this->dirty = true;
    }

    public function addSet(int $w, int $e): void
    {
        $this->authorize('update', $this->program());
        $sets = $this->workouts[$w]['exercises'][$e]['sets'] ?? [];
        $sets[] = $sets === [] ? $this->emptySet() : end($sets);
        $this->workouts[$w]['exercises'][$e]['sets'] = array_values($sets);
        $this->dirty = true;
    }

    public function removeSet(int $w, int $e, int $s): void
    {
        $this->authorize('update', $this->program());
        unset($this->workouts[$w]['exercises'][$e]['sets'][$s]);
        $this->workouts[$w]['exercises'][$e]['sets'] = array_values($this->workouts[$w]['exercises'][$e]['sets']);
        $this->dirty = true;
    }

    public function save(SaveTrainingProgram $saveProgram, SaveProgramStructure $saveStructure): void
    {
        $program = $this->program();
        $this->authorize('update', $program);

        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'goal' => ['nullable', 'string', 'max:2000'],
            'startsOn' => ['nullable', 'date_format:Y-m-d'],
            'endsOn' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:startsOn'],
            'status' => ['required', Rule::enum(ProgramStatus::class)],
            'workouts' => ['array', 'max:14'],
            'workouts.*.name' => ['required', 'string', 'max:150'],
            'workouts.*.notes' => ['nullable', 'string', 'max:1000'],
            'workouts.*.exercises' => ['array', 'max:30'],
            'workouts.*.exercises.*.exercise_id' => ['required', 'integer'],
            'workouts.*.exercises.*.superset_group' => ['nullable', 'integer', 'between:1,20'],
            'workouts.*.exercises.*.notes' => ['nullable', 'string', 'max:500'],
            'workouts.*.exercises.*.sets' => ['array', 'max:20'],
            'workouts.*.exercises.*.sets.*.reps' => ['nullable', 'integer', 'between:0,1000'],
            'workouts.*.exercises.*.sets.*.weight_kg' => ['nullable', 'numeric', 'between:0,1000'],
            'workouts.*.exercises.*.sets.*.duration_seconds' => ['nullable', 'integer', 'between:0,32000'],
            'workouts.*.exercises.*.sets.*.distance_meters' => ['nullable', 'integer', 'between:0,1000000'],
            'workouts.*.exercises.*.sets.*.rest_seconds' => ['nullable', 'integer', 'between:0,3600'],
            'workouts.*.exercises.*.sets.*.rpe' => ['nullable', 'numeric', 'between:1,10'],
            'workouts.*.exercises.*.sets.*.notes' => ['nullable', 'string', 'max:255'],
        ], [], [
            'name' => 'nombre', 'workouts.*.name' => 'nombre de la rutina', 'workouts.*.exercises.*.superset_group' => 'superserie',
            'workouts.*.exercises.*.sets.*.reps' => 'repeticiones', 'workouts.*.exercises.*.sets.*.weight_kg' => 'peso',
            'workouts.*.exercises.*.sets.*.rpe' => 'RPE', 'workouts.*.exercises.*.sets.*.rest_seconds' => 'descanso',
        ]);

        $saveProgram->update($program, [
            'name' => $this->name,
            'goal' => $this->goal,
            'starts_on' => $this->startsOn ? CarbonImmutable::createFromFormat('!Y-m-d', $this->startsOn) : null,
            'ends_on' => $this->endsOn ? CarbonImmutable::createFromFormat('!Y-m-d', $this->endsOn) : null,
            'status' => ProgramStatus::from($this->status),
        ]);

        $saveStructure->execute($program, array_map(fn (array $w) => [
            'id' => $w['id'] ?? null,
            'name' => $w['name'],
            'notes' => $w['notes'] ?? null,
            'exercises' => array_map(fn (array $e) => [
                'id' => $e['id'] ?? null,
                'exercise_id' => (int) $e['exercise_id'],
                'superset_group' => $e['superset_group'] === '' ? null : (int) $e['superset_group'],
                'notes' => $e['notes'] ?? null,
                'sets' => $e['sets'] ?? [],
            ], $w['exercises'] ?? []),
        ], $this->workouts));

        $this->loadProgram($program->fresh());
        $this->toast('Programa guardado.');
    }

    public function openTemplate(): void
    {
        $program = $this->program();
        $this->authorize('view', $program);
        $this->authorize('create', TrainingProgram::class);
        $this->templateName = $program->name;
        $this->showTemplate = true;
    }

    public function saveAsTemplate(DuplicateTrainingProgram $duplicate): void
    {
        $program = $this->program();
        $this->authorize('view', $program);
        $this->authorize('create', TrainingProgram::class);
        $this->validate(['templateName' => ['required', 'string', 'max:150']], [], ['templateName' => 'nombre']);

        $copy = $duplicate->execute($program, null, auth()->user(), $this->templateName);

        $this->showTemplate = false;
        session()->flash('success', 'Plantilla creada.');
        $this->redirectRoute('admin.training.programs.edit', $copy, navigate: true);
    }

    public function send(SendTrainingProgram $send): void
    {
        $program = $this->program();
        $this->authorize('view', $program);

        $send->execute($program, auth()->user());
        $this->toast('Programa enviado a '.$program->member->email.'.');
    }

    public function render(): View
    {
        $program = $this->program()->load(['member', 'staff:id,first_name,last_name', 'treatmentPlan:id,title']);
        $this->authorize('view', $program);

        return view('livewire.admin.training.program-editor', [
            'program' => $program,
            'canEdit' => auth()->user()->can('update', $program),
            'statuses' => ProgramStatus::options(),
            'pickerResults' => $this->showPicker
                ? Exercise::query()->where('is_active', true)->search($this->pickerSearch)->with('muscleGroups:id,name')->orderBy('name')->limit(40)->get()
                : collect(),
            'hasLogs' => $program->workouts()->whereHas('logs')->exists(),
        ])->title($program->name);
    }

    private function loadProgram(TrainingProgram $program): void
    {
        $program->load('workouts.exercises.exercise:id,name', 'workouts.exercises.sets');
        $this->name = $program->name;
        $this->goal = (string) $program->goal;
        $this->startsOn = $program->starts_on?->toDateString() ?? '';
        $this->endsOn = $program->ends_on?->toDateString() ?? '';
        $this->status = $program->status->value;
        $this->workouts = $program->workouts->map(fn ($w) => [
            'id' => $w->id,
            'name' => $w->name,
            'notes' => (string) $w->notes,
            'exercises' => $w->exercises->map(fn ($e) => [
                'id' => $e->id,
                'exercise_id' => $e->exercise_id,
                'name' => (string) $e->exercise?->name,
                'superset_group' => $e->superset_group === null ? '' : (string) $e->superset_group,
                'notes' => (string) $e->notes,
                'sets' => $e->sets->map(fn ($s) => $this->emptySet(array_map(fn ($v) => $v === null ? '' : (string) $v, $s->only(['reps', 'weight_kg', 'duration_seconds', 'distance_meters', 'rest_seconds', 'rpe', 'notes']))))->all(),
            ])->all(),
        ])->all();
        $this->dirty = false;
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private function emptySet(array $values = []): array
    {
        return $values + ['reps' => '', 'weight_kg' => '', 'duration_seconds' => '', 'distance_meters' => '', 'rest_seconds' => '', 'rpe' => '', 'notes' => ''];
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    private function swap(array $items, int $a, int $b): array
    {
        if (isset($items[$a], $items[$b])) {
            [$items[$a], $items[$b]] = [$items[$b], $items[$a]];
        }

        return array_values($items);
    }

    private function program(): TrainingProgram
    {
        return TrainingProgram::query()->findOrFail($this->programId);
    }
}
