<?php

namespace App\Domain\CheckIns\Models;

use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\CheckIns\Policies\KioskDevicePolicy;
use App\Domain\Locations\Models\Location;
use Database\Factories\KioskDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Tablet o celular fijo en la entrada de una sede. Se autentica con un
 * token Sanctum propio que se entrega una sola vez al vincularlo.
 *
 * No usa LocationScope: Sanctum lo carga mientras resuelve el usuario
 * autenticado, y el scope consulta ese mismo usuario. Las pantallas lo
 * filtran por sede de forma explícita y la Policy valida la sede.
 */
#[Fillable(['location_id', 'name', 'is_active'])]
#[UseFactory(KioskDeviceFactory::class)]
#[UsePolicy(KioskDevicePolicy::class)]
class KioskDevice extends Model
{
    use HasApiTokens, HasFactory, LogsActivity, SoftDeletes;

    public const TOKEN_NAME = 'kiosk';

    public const ABILITY = 'kiosk:check-in';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withoutGlobalScopes();
    }

    /**
     * @return HasMany<CheckIn, $this>
     */
    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function isPaired(): bool
    {
        return $this->tokens()->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('check_ins')
            ->logOnly(['location_id', 'name', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event) => AuditEvent::describeModelEvent('kiosco', $event));
    }
}
