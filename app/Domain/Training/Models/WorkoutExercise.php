<?php

namespace App\Domain\Training\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ejercicio dentro de una rutina. Los que comparten `superset_group` se
 * hacen como superserie.
 */
#[Fillable(['workout_id', 'exercise_id', 'sort_order', 'superset_group', 'notes'])]
#[WithoutTimestamps]
class WorkoutExercise extends Model
{
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'superset_group' => 'integer'];
    }

    /**
     * @return BelongsTo<Workout, $this>
     */
    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    /**
     * @return BelongsTo<Exercise, $this>
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class)->withTrashed();
    }

    /**
     * @return HasMany<WorkoutExerciseSet, $this>
     */
    public function sets(): HasMany
    {
        return $this->hasMany(WorkoutExerciseSet::class)->orderBy('set_number');
    }

    /**
     * @return HasMany<WorkoutLogSet, $this>
     */
    public function logSets(): HasMany
    {
        return $this->hasMany(WorkoutLogSet::class);
    }
}
