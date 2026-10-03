<?php

namespace App\Domain\Training\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rutina del programa (p. ej. "Día A – Pierna").
 */
#[Fillable(['training_program_id', 'name', 'scheduled_on', 'sort_order', 'notes'])]
class Workout extends Model
{
    protected function casts(): array
    {
        return ['scheduled_on' => 'date', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<TrainingProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class, 'training_program_id');
    }

    /**
     * @return HasMany<WorkoutExercise, $this>
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(WorkoutExercise::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<WorkoutLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(WorkoutLog::class);
    }
}
