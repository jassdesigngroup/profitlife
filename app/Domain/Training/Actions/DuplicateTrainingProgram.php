<?php

namespace App\Domain\Training\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Training\Enums\ProgramStatus;
use App\Domain\Training\Enums\ProgramType;
use App\Domain\Training\Models\TrainingProgram;
use App\Support\BusinessDate;
use Illuminate\Support\Facades\DB;

/**
 * Copia un programa con sus rutinas, ejercicios y series: a otro cliente
 * (o desde una plantilla) o como plantilla (sin cliente).
 */
class DuplicateTrainingProgram
{
    public function execute(TrainingProgram $source, ?Member $target, User $actor, ?string $name = null): TrainingProgram
    {
        $source->loadMissing('workouts.exercises.sets');

        return DB::transaction(function () use ($source, $target, $actor, $name) {
            $copy = TrainingProgram::query()->create([
                'member_id' => $target?->id,
                'staff_id' => $actor->staff->id,
                'treatment_plan_id' => null,
                // Una copia nunca hereda el vínculo clínico.
                'type' => ProgramType::Training,
                'is_template' => $target === null,
                'name' => $name ?: $source->name,
                'goal' => $source->goal,
                'starts_on' => $target ? BusinessDate::today()->toDateString() : null,
                'status' => ProgramStatus::Active,
            ]);

            foreach ($source->workouts as $workout) {
                $newWorkout = $copy->workouts()->create($workout->only(['name', 'sort_order', 'notes']));

                foreach ($workout->exercises as $item) {
                    $newItem = $newWorkout->exercises()->create($item->only(['exercise_id', 'sort_order', 'superset_group', 'notes']));

                    foreach ($item->sets as $set) {
                        $newItem->sets()->create($set->only(['set_number', 'reps', 'weight_kg', 'duration_seconds', 'distance_meters', 'rest_seconds', 'rpe', 'notes']));
                    }
                }
            }

            return $copy;
        });
    }
}
