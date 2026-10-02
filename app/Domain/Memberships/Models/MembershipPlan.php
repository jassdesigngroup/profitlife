<?php

namespace App\Domain\Memberships\Models;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Locations\Models\Location;
use App\Domain\Memberships\Enums\AccessScope;
use App\Domain\Memberships\Enums\DurationUnit;
use App\Domain\Memberships\Enums\VisitLimitPeriod;
use App\Domain\Memberships\Policies\MembershipPlanPolicy;
use App\Support\Money;
use App\Support\Scopes\LocationScope;
use Database\Factories\MembershipPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Plan comercial. Su precio se copia a cada membresía al venderla, así que
 * cambiarlo no altera contratos vigentes.
 */
#[Fillable([
    'name', 'slug', 'description', 'duration_unit', 'duration_count', 'billing_unit', 'billing_count', 'price_cents',
    'enrollment_fee_cents', 'currency', 'tax_rate_bps', 'access_scope', 'visit_limit_count', 'visit_limit_period',
    'max_freeze_days', 'auto_renews', 'benefits', 'is_active', 'sort_order',
])]
#[UseFactory(MembershipPlanFactory::class)]
#[UsePolicy(MembershipPlanPolicy::class)]
class MembershipPlan extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'duration_unit' => DurationUnit::class,
            'billing_unit' => DurationUnit::class,
            'access_scope' => AccessScope::class,
            'visit_limit_period' => VisitLimitPeriod::class,
            'duration_count' => 'integer',
            'billing_count' => 'integer',
            'price_cents' => 'integer',
            'enrollment_fee_cents' => 'integer',
            'tax_rate_bps' => 'integer',
            'visit_limit_count' => 'integer',
            'max_freeze_days' => 'integer',
            'auto_renews' => 'boolean',
            'benefits' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Location, $this>
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class)->withoutGlobalScope(LocationScope::class);
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function price(): Money
    {
        return Money::ofCents($this->price_cents, $this->currency);
    }

    public function enrollmentFee(): Money
    {
        return Money::ofCents($this->enrollment_fee_cents, $this->currency);
    }

    public function durationLabel(): string
    {
        return $this->duration_unit?->describe((int) $this->duration_count) ?? 'Indefinida';
    }

    public function visitLimitLabel(): string
    {
        return $this->visit_limit_count === null
            ? 'Ingresos ilimitados'
            : "{$this->visit_limit_count} ingresos {$this->visit_limit_period?->label()}";
    }

    public function isValidAt(int $locationId): bool
    {
        return $this->access_scope === AccessScope::AllLocations
            || $this->locations->contains('id', $locationId);
    }

    public function allowsFreeze(): bool
    {
        return (int) $this->max_freeze_days > 0;
    }

    /**
     * Planes activos que se pueden vender en una sede.
     *
     * @param  Builder<static>  $query
     */
    public function scopeAvailableAt(Builder $query, int $locationId): void
    {
        $query->where('is_active', true)->where(fn (Builder $q) => $q
            ->where('access_scope', AccessScope::AllLocations)
            ->orWhereHas('locations', fn (Builder $l) => $l->where('locations.id', $locationId)));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('memberships')
            ->logOnly(['name', 'price_cents', 'enrollment_fee_cents', 'tax_rate_bps', 'duration_unit', 'duration_count', 'access_scope', 'visit_limit_count', 'visit_limit_period', 'max_freeze_days', 'auto_renews', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event) => AuditEvent::describeModelEvent('plan', $event));
    }
}
