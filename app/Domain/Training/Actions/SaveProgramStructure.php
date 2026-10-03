<?php

namespace App\Domain\Training\Actions;

use App\Domain\Training\Models\Exercise;
use App\Domain\Training\Models\TrainingProgram;
use App\Domain\Training\Models\Workout;
use App\Domain\Training\Models\WorkoutExercise;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Guarda rutinas, ejercicios y series de un programa. Conserva los ids de
 * lo que ya existía; lo que tiene entrenamientos registrados no se puede
 * quitar (se pierde el historial), solo cambiar sus series.
 */
class SaveProgramStructure
{
    /**
     * @param  list<array{id: ?int, name: string, notes: ?string, exercises: list<array{id: ?int, exercise_id: int, superset_group: ?int, notes: ?string, sets: list<array<string, mixed>>}>}>  $workouts
     */
    public function execute(TrainingProgram $program, array $workouts): void
    {
        $exerciseIds = collect($workouts)->flatMap(fn ($w) => collect($w['exercises'])->pluck('exercise_id'))->unique();
        $known = Exercise::withTrashed()->whereIn('id', $exerciseIds)->pluck('id');
        if ($exerciseIds->diff($known)->isNotEmpty()) {
            throw ValidationException::withMessages(['workouts' => 'Hay ejercicios que no existen en la biblioteca.']);
        }

        DB::transaction(function () use ($program, $workouts) {
            $existing = $program->workouts()->get()->keyBy('id');
            $kept = [];

            foreach (array_values($workouts) as $wIndex => $data) {
                $workout = isset($data['id']) && $existing->has($data['id']) ? $existing[$data['id']] : new Workout(['training_program_id' => $program->id]);
                $workout->fill(['name' => $data['name'], 'notes' => $data['notes'] ?: null, 'sort_order' => $wIndex])->save();
                $kept[] = $workout->id;

                $this->syncExercises($workout, $data['exercises']);
            }

            foreach ($existing->except($kept) as $removed) {
                if ($removed->logs()->exists()) {
                    throw ValidationException::withMessages(['workouts' => "La rutina «{$removed->name}» ya tiene entrenamientos registrados y no se puede quitar."]);
                }
                $removed->delete();
            }
        });
    }

    /**
     * @param  list<array{id: ?int, exercise_id: int, superset_group: ?int, notes: ?string, sets: list<array<string, mixed>>}>  $exercises
     */
    private function syncExercises(Workout $workout, array $exercises): void
    {
        $existing = $workout->exercises()->get()->keyBy('id');
        $kept = [];

        foreach (array_values($exercises) as $eIndex => $data) {
            $item = isset($data['id']) && $existing->has($data['id']) ? $existing[$data['id']] : new WorkoutExercise(['workout_id' => $workout->id]);

            if ($item->exists && $item->exercise_id !== (int) $data['exercise_id'] && $item->logSets()->exists()) {
                throw ValidationException::withMessages(['workouts' => 'No se puede cambiar un ejercicio que ya tiene entrenamientos registrados.']);
            }

            $item->fill([
                'exercise_id' => (int) $data['exercise_id'],
                'sort_order' => $eIndex,
                'superset_group' => $data['superset_group'] ?: null,
                'notes' => $data['notes'] ?: null,
            ])->save();
            $kept[] = $item->id;

            $item->sets()->delete();
            foreach (array_values($data['sets']) as $sIndex => $set) {
                $item->sets()->create([
                    'set_number' => $sIndex + 1,
                    'reps' => self::int($set['reps'] ?? null),
                    'weight_kg' => self::decimal($set['weight_kg'] ?? null),
                    'duration_seconds' => self::int($set['duration_seconds'] ?? null),
                    'distance_meters' => self::int($set['distance_meters'] ?? null),
                    'rest_seconds' => self::int($set['rest_seconds'] ?? null),
                    'rpe' => self::decimal($set['rpe'] ?? null),
                    'notes' => trim((string) ($set['notes'] ?? '')) ?: null,
                ]);
            }
        }

        foreach ($existing->except($kept) as $removed) {
            if ($removed->logSets()->exists()) {
                throw ValidationException::withMessages(['workouts' => "«{$removed->exercise?->name}» ya tiene entrenamientos registrados y no se puede quitar de la rutina «{$workout->name}»."]);
            }
            $removed->delete();
        }
    }

    public static function int(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    public static function decimal(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : round((float) str_replace(',', '.', (string) $value), 2);
    }
}
