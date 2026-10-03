<?php

namespace App\Domain\Training\Actions;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Training\Models\TrainingProgram;
use App\Domain\Training\Models\Workout;
use App\Domain\Training\Models\WorkoutLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra lo que el cliente hizo de una rutina. Las series vacías se
 * omiten; debe quedar al menos una.
 */
class LogWorkout
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{performed_at: CarbonImmutable, duration_minutes: ?int, rpe: ?float, notes: ?string, sets: array<int|string, list<array<string, mixed>>>}  $data  sets por workout_exercise_id
     */
    public function execute(TrainingProgram $program, Workout $workout, User $actor, array $data): WorkoutLog
    {
        if ($program->is_template || $workout->training_program_id !== $program->id) {
            throw ValidationException::withMessages(['workoutId' => 'Rutina no válida.']);
        }

        if ($data['performed_at']->isFuture()) {
            throw ValidationException::withMessages(['performedAt' => 'La fecha no puede ser futura.']);
        }

        $validItems = $workout->exercises()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $rows = [];

        foreach ($data['sets'] as $itemId => $sets) {
            if (! in_array((int) $itemId, $validItems, true)) {
                throw ValidationException::withMessages(['sets' => 'Hay series de ejercicios que no son de esta rutina.']);
            }

            foreach (array_values($sets) as $index => $set) {
                if (! ($set['done'] ?? true)) {
                    continue;
                }
                $values = [
                    'reps' => SaveProgramStructure::int($set['reps'] ?? null),
                    'weight_kg' => SaveProgramStructure::decimal($set['weight_kg'] ?? null),
                    'duration_seconds' => SaveProgramStructure::int($set['duration_seconds'] ?? null),
                    'distance_meters' => SaveProgramStructure::int($set['distance_meters'] ?? null),
                    'rpe' => SaveProgramStructure::decimal($set['rpe'] ?? null),
                ];
                if (array_filter($values, fn ($v) => $v !== null) === []) {
                    continue;
                }
                $rows[] = ['workout_exercise_id' => (int) $itemId, 'set_number' => $index + 1] + $values;
            }
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['sets' => 'Registre al menos una serie.']);
        }

        return DB::transaction(function () use ($program, $workout, $actor, $data, $rows) {
            $log = WorkoutLog::query()->create([
                'workout_id' => $workout->id,
                'member_id' => $program->member_id,
                'logged_by' => $actor->id,
                'performed_at' => $data['performed_at'],
                'duration_minutes' => $data['duration_minutes'],
                'rpe' => $data['rpe'],
                'notes' => $data['notes'] ?: null,
            ]);

            foreach ($rows as $row) {
                $log->sets()->create($row);
            }

            $this->audit->log('training', AuditEvent::WorkoutLogged, $log, $actor, [
                'member_id' => $program->member_id,
                'program_id' => $program->id,
                'sets' => count($rows),
            ]);

            return $log;
        });
    }
}
