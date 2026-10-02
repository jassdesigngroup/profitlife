<?php

namespace App\Domain\Physiotherapy\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Physiotherapy\Enums\RecordStatus;
use App\Domain\Physiotherapy\Policies\PhysiotherapyRecordPolicy;
use App\Domain\Staff\Models\Staff;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Historia clínica de fisioterapia (una por cliente). No usa LocationScope:
 * el acceso lo decide el equipo tratante (Policy), no la sede. Los campos
 * clínicos se guardan cifrados.
 */
#[Fillable([
    'member_id', 'primary_staff_id', 'status', 'reason_for_consultation', 'medical_history', 'medications',
    'allergies', 'opened_at', 'opened_by',
])]
#[Hidden(['reason_for_consultation', 'medical_history', 'medications', 'allergies'])]
#[UsePolicy(PhysiotherapyRecordPolicy::class)]
class PhysiotherapyRecord extends Model
{
    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'reason_for_consultation' => 'encrypted',
            'medical_history' => 'encrypted',
            'medications' => 'encrypted',
            'allergies' => 'encrypted',
            'opened_at' => 'datetime',
        ];
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
    public function primaryStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'primary_staff_id')->withoutGlobalScopes();
    }

    /**
     * @return HasMany<PhysiotherapyRecordStaff, $this>
     */
    public function team(): HasMany
    {
        return $this->hasMany(PhysiotherapyRecordStaff::class);
    }

    /**
     * @return HasMany<PhysiotherapyRecordStaff, $this>
     */
    public function activeTeam(): HasMany
    {
        return $this->team()->whereNull('revoked_at');
    }

    /**
     * @return HasMany<TreatmentPlan, $this>
     */
    public function plans(): HasMany
    {
        return $this->hasMany(TreatmentPlan::class)->latest('starts_on');
    }

    /**
     * @return HasMany<PhysiotherapySession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(PhysiotherapySession::class)->latest('performed_at');
    }

    /**
     * @return HasMany<ClinicalNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function hasOnTeam(?Staff $staff): bool
    {
        return $staff !== null && $this->activeTeam()->where('staff_id', $staff->id)->exists();
    }
}
