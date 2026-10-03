<?php

namespace App\Livewire\Admin\Training;

use App\Domain\Settings\Services\Settings;
use App\Domain\Training\Actions\LogWorkout;
use App\Domain\Training\Models\TrainingProgram;
use App\Domain\Training\Models\Workout;
use App\Domain\Training\Models\WorkoutLog;
use App\Domain\Training\Models\WorkoutLogSet;
use App\Livewire\Admin\Concerns\InteractsWithToasts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Registro rápido (móvil/tablet) de lo que el cliente hizo en una rutina:
 * parte de lo prescrito y el entrenador marca y ajusta cada serie.
 */
class WorkoutLogger extends Component
{
    use InteractsWithToasts;

    #[Locked]
    public int $programId;

    #[Url(as: 'rutina')]
    public ?int $workoutId = null;

    public string $performedAt = '';

    public string $duration = '';

    public string $rpe = '';

    public string $notes = '';

    /** @var array<int, list<array{reps: string, weight_kg: string, duration_seconds: string, distance_meters: string, rpe: string, done: bool}>> */
    public array $sets = [];

    public function mount(TrainingProgram $program, Settings $settings): void
    {
        $this->authorize('log', $program);
        $this->programId = $program->id;
        $this->performedAt = now($settings->displayTimezone())->format('Y-m-d\TH:i');

        $ids = $program->workouts()->pluck('id')->all();
        if (! in_array($this->workoutId, $ids, true)) {
            $this->workoutId = $ids[0] ?? null;
        }
        $this->prefill();
    }

    public function selectWorkout(int $id): void
    {
        $this->authorize('log', $this->program());
        $this->workoutId = $this->workout($id)->id;
        $this->prefill();
    }

    public function addSet(int $itemId): void
    {
        $this->authorize('log', $this->program());
        if (! isset($this->sets[$itemId])) {
            return;
        }
        $last = end($this->sets[$itemId]) ?: $this->blank();
        $this->sets[$itemId][] = ['done' => true] + $last;
    }

    public function save(LogWorkout $log, Settings $settings): void
    {
        $program = $this->program();
        $this->authorize('log', $program);

        $this->validate([
            'performedAt' => ['required', 'date_format:Y-m-d\TH:i'],
            'duration' => ['nullable', 'integer', 'between:1,600'],
            'rpe' => ['nullable', 'numeric', 'between:1,10'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sets' => ['array', 'max:40'],
            'sets.*' => ['array', 'max:30'],
            'sets.*.*.reps' => ['nullable', 'integer', 'between:0,1000'],
            'sets.*.*.weight_kg' => ['nullable', 'numeric', 'between:0,1000'],
            'sets.*.*.duration_seconds' => ['nullable', 'integer', 'between:0,32000'],
            'sets.*.*.distance_meters' => ['nullable', 'integer', 'between:0,1000000'],
            'sets.*.*.rpe' => ['nullable', 'numeric', 'between:1,10'],
            'sets.*.*.done' => ['boolean'],
        ], [], [
            'performedAt' => 'fecha', 'duration' => 'duración', 'rpe' => 'RPE', 'notes' => 'notas',
            'sets.*.*.reps' => 'repeticiones', 'sets.*.*.weight_kg' => 'peso', 'sets.*.*.rpe' => 'RPE',
        ]);

        $workout = $this->workout((int) $this->workoutId);

        $log->execute($program, $workout, auth()->user(), [
            'performed_at' => CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->performedAt, $settings->displayTimezone())->utc(),
            'duration_minutes' => $this->duration === '' ? null : (int) $this->duration,
            'rpe' => $this->rpe === '' ? null : (float) $this->rpe,
            'notes' => $this->notes,
            'sets' => $this->sets,
        ]);

        session()->flash('success', "Entrenamiento «{$workout->name}» registrado.");
        $this->redirectRoute('admin.members.show', ['member' => $program->member_id, 'tab' => 'training'], navigate: true);
    }

    public function render(): View
    {
        $program = $this->program()->load('member', 'workouts:id,training_program_id,name,sort_order');
        $this->authorize('log', $program);

        $workout = $this->workoutId ? $this->workout($this->workoutId)->load('exercises.exercise:id,name', 'exercises.sets') : null;

        return view('livewire.admin.training.logger', [
            'program' => $program,
            'workout' => $workout,
            'previous' => $workout ? $this->previousSets($workout) : collect(),
        ])->title('Registrar · '.$program->name);
    }

    private function prefill(): void
    {
        $this->sets = [];
        if ($this->workoutId === null) {
            return;
        }

        foreach ($this->workout($this->workoutId)->exercises()->with('sets')->get() as $item) {
            $prescribed = $item->sets->map(fn ($s) => [
                'reps' => (string) ($s->reps ?? ''),
                'weight_kg' => $s->weight_kg === null ? '' : self::kg($s->weight_kg),
                'duration_seconds' => (string) ($s->duration_seconds ?? ''),
                'distance_meters' => (string) ($s->distance_meters ?? ''),
                'rpe' => '',
                'done' => true,
            ])->all();
            $this->sets[$item->id] = $prescribed ?: [$this->blank()];
        }
    }

    /**
     * Lo último registrado para cada ejercicio de la rutina, como referencia.
     *
     * @return Collection<int, string>
     */
    private function previousSets(Workout $workout)
    {
        $lastLog = WorkoutLog::query()->where('workout_id', $workout->id)->latest('performed_at')->first();
        if ($lastLog === null) {
            return collect();
        }

        return WorkoutLogSet::query()->where('workout_log_id', $lastLog->id)->orderBy('set_number')->get()
            ->groupBy('workout_exercise_id')
            ->map(fn ($sets) => $sets->map(fn ($s) => trim(($s->reps !== null ? $s->reps : '').($s->weight_kg !== null ? '×'.self::kg($s->weight_kg).'kg' : '')) ?: ($s->duration_seconds ? $s->duration_seconds.'s' : '—'))->implode(' · '));
    }

    private static function kg(float|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    /**
     * @return array{reps: string, weight_kg: string, duration_seconds: string, distance_meters: string, rpe: string, done: bool}
     */
    private function blank(): array
    {
        return ['reps' => '', 'weight_kg' => '', 'duration_seconds' => '', 'distance_meters' => '', 'rpe' => '', 'done' => true];
    }

    private function workout(int $id): Workout
    {
        return Workout::query()->where('training_program_id', $this->programId)->findOrFail($id);
    }

    private function program(): TrainingProgram
    {
        return TrainingProgram::query()->findOrFail($this->programId);
    }
}
