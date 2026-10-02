<?php

namespace App\Domain\Appointments\Models;

use App\Domain\Appointments\Enums\ServiceCategory;
use App\Domain\Appointments\Policies\ServicePolicy;
use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Locations\Models\Location;
use App\Domain\Staff\Models\Staff;
use App\Support\Money;
use App\Support\Scopes\LocationScope;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Servicio que se agenda (fisioterapia, entrenamiento personal…). Se
 * ofrece en las sedes de `location_service` y lo prestan los profesionales
 * de `service_staff`.
 */
#[Fillable([
    'name', 'slug', 'category', 'description', 'duration_minutes', 'buffer_minutes', 'price_cents', 'currency',
    'tax_rate_bps', 'requires_room', 'is_clinical', 'is_bookable_online', 'color', 'is_active',
])]
#[UseFactory(ServiceFactory::class)]
#[UsePolicy(ServicePolicy::class)]
class Service extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'category' => ServiceCategory::class,
            'duration_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'price_cents' => 'integer',
            'tax_rate_bps' => 'integer',
            'requires_room' => 'boolean',
            'is_clinical' => 'boolean',
            'is_bookable_online' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Location, $this>
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class)
            ->withoutGlobalScope(LocationScope::class)
            ->withPivot(['price_cents', 'is_active']);
    }

    /**
     * @return BelongsToMany<Staff, $this>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class)->withoutGlobalScope(LocationScope::class);
    }

    public function isOfferedAt(int $locationId): bool
    {
        $location = $this->locations->firstWhere('id', $locationId);

        return $this->is_active && $location !== null && (bool) $location->pivot->is_active;
    }

    /**
     * Precio en la sede (propio de la sede o el general).
     */
    public function priceCentsAt(int $locationId): int
    {
        $own = $this->locations->firstWhere('id', $locationId)?->pivot->price_cents;

        return $own === null ? $this->price_cents : (int) $own;
    }

    public function priceAt(int $locationId): Money
    {
        return Money::ofCents($this->priceCentsAt($locationId), $this->currency);
    }

    /**
     * Minutos que ocupa en la agenda: la sesión más el margen.
     */
    public function blockMinutes(): int
    {
        return $this->duration_minutes + $this->buffer_minutes;
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOfferedAt(Builder $query, int $locationId): void
    {
        $query->where('is_active', true)->whereHas('locations', fn (Builder $l) => $l
            ->where('locations.id', $locationId)
            ->where('location_service.is_active', true));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('appointments')
            ->logOnly(['name', 'category', 'duration_minutes', 'buffer_minutes', 'price_cents', 'tax_rate_bps', 'requires_room', 'is_clinical', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event) => AuditEvent::describeModelEvent('servicio', $event));
    }
}
