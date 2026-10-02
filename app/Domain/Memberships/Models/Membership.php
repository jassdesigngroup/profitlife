<?php

namespace App\Domain\Memberships\Models;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Identity\Models\User;
use App\Domain\Locations\Models\Location;
use App\Domain\Members\Models\Member;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Policies\MembershipPolicy;
use App\Support\BusinessDate;
use App\Support\Concerns\HasLocationScope;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Contrato de un cliente con un plan por un periodo. Es visible para quien
 * puede ver al cliente.
 */
#[Fillable([
    'member_id', 'membership_plan_id', 'purchase_location_id', 'status', 'starts_on', 'ends_on', 'next_billing_on',
    'price_cents', 'currency', 'auto_renews', 'renewed_from_id', 'cancelled_at', 'cancellation_reason', 'notes', 'created_by',
])]
#[UseFactory(MembershipFactory::class)]
#[UsePolicy(MembershipPolicy::class)]
class Membership extends Model
{
    use HasFactory, HasLocationScope, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'next_billing_on' => 'date',
            'price_cents' => 'integer',
            'auto_renews' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Visible si el cliente es visible (su propio LocationScope decide).
     *
     * @param  Builder<static>  $query
     * @param  list<int>  $locationIds
     */
    public function applyLocationRestriction(Builder $query, array $locationIds): void
    {
        $query->whereHas('member');
    }

    /**
     * @return list<int>
     */
    public function locationIds(): array
    {
        return $this->member()->withoutGlobalScopes()->first()?->locationIds() ?? [];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<MembershipPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function purchaseLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'purchase_location_id')->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    /**
     * @return HasMany<MembershipFreeze, $this>
     */
    public function freezes(): HasMany
    {
        return $this->hasMany(MembershipFreeze::class)->orderByDesc('starts_on');
    }

    /**
     * @return HasMany<MembershipStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(MembershipStatusHistory::class)->latest('id');
    }

    /**
     * Comprobante que cobra este periodo (renglón con billable = membership).
     */
    public function invoice(): ?Invoice
    {
        return Invoice::query()->withoutGlobalScopes()
            ->whereHas('items', fn (Builder $q) => $q->where('billable_type', 'membership')->where('billable_id', $this->id))
            ->latest('id')
            ->first();
    }

    public function openFreeze(): ?MembershipFreeze
    {
        return $this->freezes()->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>', BusinessDate::today()))->first();
    }

    /**
     * Días ya consumidos en congelaciones cerradas y la abierta hasta hoy.
     */
    public function frozenDaysUsed(?CarbonImmutable $asOf = null): int
    {
        $asOf ??= BusinessDate::today();

        return (int) $this->freezes()->get()->sum(fn (MembershipFreeze $f) => $f->days($asOf));
    }

    public function freezeDaysLeft(): int
    {
        return max(0, (int) $this->plan->max_freeze_days - $this->frozenDaysUsed());
    }

    public function daysRemaining(): ?int
    {
        if ($this->ends_on === null) {
            return null;
        }

        return max(0, (int) BusinessDate::today()->diffInDays($this->ends_on, false) + 1);
    }

    public function price(): Money
    {
        return Money::ofCents($this->price_cents, $this->currency);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereIn('status', MembershipStatus::currentValues());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
