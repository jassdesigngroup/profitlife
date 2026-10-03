<?php

namespace App\Domain\Training\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Serie realizada.
 */
#[Fillable(['workout_log_id', 'workout_exercise_id', 'set_number', 'reps', 'weight_kg', 'duration_seconds', 'distance_meters', 'rpe'])]
#[WithoutTimestamps]
class WorkoutLogSet extends Model
{
    protected function casts(): array
    {
        return [
            'set_number' => 'integer',
            'reps' => 'integer',
            'weight_kg' => 'float',
            'duration_seconds' => 'integer',
            'distance_meters' => 'integer',
            'rpe' => 'float',
        ];
    }

    /**
     * @return BelongsTo<WorkoutExercise, $this>
     */
    public function workoutExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutExercise::class);
    }
}
