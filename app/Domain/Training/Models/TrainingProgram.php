<?php

namespace App\Domain\Training\Models;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Members\Models\Member;
use App\Domain\Physiotherapy\Models\TreatmentPlan;
use App\Domain\Staff\Models\Staff;
use App\Domain\Training\Enums\ProgramStatus;
use App\Domain\Training\Enums\ProgramType;
use App\Domain\Training\Policies\TrainingProgramPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Programa de entrenamiento o de rehabilitación de un cliente, o plantilla
 * reutilizable (sin cliente). Un programa de rehabilitación sigue las
 * reglas de acceso de la historia clínica.
 */
#[Fillable([
    'member_id', 'staff_id', 'treatment_plan_id', 'type', 'is_template', 'name', 'goal', 'starts_on', 'ends_on', 'status',
])]
#[UsePolicy(TrainingProgramPolicy::class)]
class TrainingProgram extends Model
{
    use LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => ProgramType::class,
            'status' => ProgramStatus::class,
            'is_template' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function isRehab(): bool
    {
        return $this->type === ProgramType::Rehab;
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<TreatmentPlan, $this>
     */
    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class)->withTrashed();
    }

    /**
     * @return HasMany<Workout, $this>
     */
    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class)->orderBy('sort_order')->orderBy('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Sin nombre ni objetivo: en rehabilitación pueden describir la condición del paciente.
        return LogOptions::defaults()
            ->useLogName('training')
            ->logOnly(['member_id', 'staff_id', 'type', 'is_template', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event) => AuditEvent::describeModelEvent('programa de entrenamiento', $event));
    }
}
