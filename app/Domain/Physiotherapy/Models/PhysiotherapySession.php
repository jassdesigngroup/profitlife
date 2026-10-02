<?php

namespace App\Domain\Physiotherapy\Models;

use App\Domain\Appointments\Models\Appointment;
use App\Domain\Locations\Models\Location;
use App\Domain\Physiotherapy\Enums\SessionType;
use App\Domain\Staff\Models\Staff;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'physiotherapy_record_id', 'treatment_plan_id', 'appointment_id', 'staff_id', 'location_id', 'session_type',
    'performed_at', 'pain_scale', 'summary_for_member',
])]
class PhysiotherapySession extends Model
{
    protected function casts(): array
    {
        return [
            'session_type' => SessionType::class,
            'performed_at' => 'datetime',
            'pain_scale' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PhysiotherapyRecord, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(PhysiotherapyRecord::class, 'physiotherapy_record_id');
    }

    /**
     * @return BelongsTo<TreatmentPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class, 'treatment_plan_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withoutGlobalScopes();
    }

    /**
     * @return HasMany<ClinicalNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class)->orderBy('id');
    }
}
