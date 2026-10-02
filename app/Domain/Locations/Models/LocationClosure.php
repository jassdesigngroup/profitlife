<?php

namespace App\Domain\Locations\Models;

use Database\Factories\LocationClosureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Festivo o cierre puntual. location_id nulo = aplica a todas las sedes.
 */
#[Fillable(['location_id', 'closed_on', 'reason'])]
#[UseFactory(LocationClosureFactory::class)]
class LocationClosure extends Model
{
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'closed_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function isGlobal(): bool
    {
        return $this->location_id === null;
    }

    /**
     * Cierres que afectan a una sede: los suyos y los globales.
     *
     * @param  Builder<static>  $query
     */
    public function scopeAffecting(Builder $query, int $locationId): void
    {
        $query->where(fn (Builder $q) => $q->where('location_id', $locationId)->orWhereNull('location_id'));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('locations')
            ->logOnly(['location_id', 'closed_on', 'reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
