<?php

namespace App\Domain\Physiotherapy\Models;

use App\Domain\Physiotherapy\Enums\PlanStatus;
use App\Domain\Staff\Models\Staff;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'physiotherapy_record_id', 'staff_id', 'title', 'diagnosis', 'goals', 'planned_sessions', 'starts_on', 'ends_on',
    'status', 'is_visible_to_member',
])]
#[Hidden(['diagnosis', 'goals'])]
class TreatmentPlan extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PlanStatus::class,
            'diagnosis' => 'encrypted',
            'goals' => 'encrypted',
            'planned_sessions' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_visible_to_member' => 'boolean',
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
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withoutGlobalScopes();
    }

    /**
     * @return HasMany<PhysiotherapySession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(PhysiotherapySession::class);
    }
}
