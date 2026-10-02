<?php

namespace App\Domain\CheckIns\Models;

use App\Domain\CheckIns\Enums\CheckInMethod;
use App\Domain\CheckIns\Enums\CheckInResult;
use App\Domain\CheckIns\Enums\RejectionReason;
use App\Domain\CheckIns\Policies\CheckInPolicy;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Members\Models\Member;
use App\Domain\Memberships\Models\Membership;
use App\Support\Concerns\HasLocationScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Intento de ingreso a una sede. Solo inserción: guarda los aceptados y
 * los rechazados con su motivo.
 */
#[Fillable([
    'member_id', 'location_id', 'membership_id', 'kiosk_device_id', 'registered_by', 'method', 'result',
    'rejection_reason', 'checked_in_at',
])]
#[UsePolicy(CheckInPolicy::class)]
class CheckIn extends Model
{
    use HasLocationScope;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'method' => CheckInMethod::class,
            'result' => CheckInResult::class,
            'rejection_reason' => RejectionReason::class,
            'checked_in_at' => 'datetime',
        ];
    }

    public function isAccepted(): bool
    {
        return $this->result === CheckInResult::Accepted;
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Membership, $this>
     */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<KioskDevice, $this>
     */
    public function kioskDevice(): BelongsTo
    {
        return $this->belongsTo(KioskDevice::class)->withoutGlobalScopes()->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeAccepted(Builder $query): void
    {
        $query->where('result', CheckInResult::Accepted);
    }
}
