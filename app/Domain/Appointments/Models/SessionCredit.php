<?php

namespace App\Domain\Appointments\Models;

use App\Domain\Appointments\Enums\CreditReason;
use App\Domain\Identity\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Memberships\Models\Membership;
use App\Support\Concerns\HasLocationScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento del libro de sesiones. Solo inserción: el saldo es SUM(delta).
 */
#[Fillable(['member_id', 'membership_id', 'service_id', 'delta', 'reason', 'appointment_id', 'expires_on', 'created_by'])]
class SessionCredit extends Model
{
    use HasLocationScope;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'delta' => 'integer',
            'reason' => CreditReason::class,
            'expires_on' => 'date',
        ];
    }

    /**
     * Hereda la visibilidad del cliente.
     *
     * @param  Builder<static>  $query
     * @param  list<int>  $locationIds
     */
    public function applyLocationRestriction(Builder $query, array $locationIds): void
    {
        $query->whereHas('member');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<Membership, $this>
     */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
