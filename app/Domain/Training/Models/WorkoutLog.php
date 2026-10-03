<?php

namespace App\Domain\Training\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Entrenamiento realizado (una rutina en una fecha).
 */
#[Fillable(['workout_id', 'member_id', 'logged_by', 'performed_at', 'duration_minutes', 'rpe', 'notes'])]
class WorkoutLog extends Model
{
    protected function casts(): array
    {
        return ['performed_at' => 'datetime', 'duration_minutes' => 'integer', 'rpe' => 'float'];
    }

    /**
     * @return BelongsTo<Workout, $this>
     */
    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function logger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    /**
     * @return HasMany<WorkoutLogSet, $this>
     */
    public function sets(): HasMany
    {
        return $this->hasMany(WorkoutLogSet::class)->orderBy('workout_exercise_id')->orderBy('set_number');
    }
}
