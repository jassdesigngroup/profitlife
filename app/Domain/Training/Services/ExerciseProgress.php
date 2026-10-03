<?php

namespace App\Domain\Training\Services;

use App\Domain\Members\Models\Member;
use App\Domain\Training\Models\Exercise;
use App\Domain\Training\Models\WorkoutLogSet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Progreso por ejercicio de un cliente: peso máximo y volumen
 * (series × repeticiones × peso) por entrenamiento registrado.
 */
class ExerciseProgress
{
    /**
     * Ejercicios que el cliente ha registrado, con cuántas veces.
     *
     * @return Collection<int, object{id: int, name: string, sessions: int}>
     */
    public function exercisesFor(Member $member, bool $includeRehab = false): Collection
    {
        return $this->base($member, $includeRehab)
            ->selectRaw('exercises.id, exercises.name, COUNT(DISTINCT workout_logs.id) as sessions')
            ->groupBy('exercises.id', 'exercises.name')
            ->orderByDesc('sessions')
            ->get()
            ->map(fn ($r) => (object) ['id' => (int) $r->id, 'name' => $r->name, 'sessions' => (int) $r->sessions]);
    }

    /**
     * @return Collection<int, object{date: string, max_weight: ?float, volume: float, best_reps: ?int}>
     */
    public function seriesFor(Member $member, Exercise $exercise, bool $includeRehab = false): Collection
    {
        return $this->base($member, $includeRehab)
            ->where('exercises.id', $exercise->id)
            ->selectRaw('workout_logs.id as log_id, workout_logs.performed_at, MAX(workout_log_sets.weight_kg) as max_weight, SUM(COALESCE(workout_log_sets.reps, 0) * COALESCE(workout_log_sets.weight_kg, 0)) as volume, MAX(workout_log_sets.reps) as best_reps')
            ->groupBy('workout_logs.id', 'workout_logs.performed_at')
            ->orderBy('workout_logs.performed_at')
            ->get()
            ->map(fn ($r) => (object) [
                'date' => (string) $r->performed_at,
                'max_weight' => $r->max_weight === null ? null : (float) $r->max_weight,
                'volume' => (float) $r->volume,
                'best_reps' => $r->best_reps === null ? null : (int) $r->best_reps,
            ]);
    }

    /**
     * @return Builder<WorkoutLogSet>
     */
    private function base(Member $member, bool $includeRehab)
    {
        return WorkoutLogSet::query()
            ->join('workout_logs', 'workout_logs.id', '=', 'workout_log_sets.workout_log_id')
            ->join('workout_exercises', 'workout_exercises.id', '=', 'workout_log_sets.workout_exercise_id')
            ->join('exercises', 'exercises.id', '=', 'workout_exercises.exercise_id')
            ->join('workouts', 'workouts.id', '=', 'workout_logs.workout_id')
            ->join('training_programs', 'training_programs.id', '=', 'workouts.training_program_id')
            ->where('workout_logs.member_id', $member->id)
            ->when(! $includeRehab, fn ($q) => $q->where('training_programs.type', 'training'));
    }
}
