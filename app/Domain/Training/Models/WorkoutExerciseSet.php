<?php

namespace App\Domain\Training\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * Serie prescrita.
 */
#[Fillable(['workout_exercise_id', 'set_number', 'reps', 'weight_kg', 'duration_seconds', 'distance_meters', 'rest_seconds', 'rpe', 'notes'])]
#[WithoutTimestamps]
class WorkoutExerciseSet extends Model
{
    protected function casts(): array
    {
        return [
            'set_number' => 'integer',
            'reps' => 'integer',
            'weight_kg' => 'float',
            'duration_seconds' => 'integer',
            'distance_meters' => 'integer',
            'rest_seconds' => 'integer',
            'rpe' => 'float',
        ];
    }
}
