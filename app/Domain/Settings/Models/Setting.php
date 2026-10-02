<?php

namespace App\Domain\Settings\Models;

use App\Domain\Locations\Models\Location;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Ajuste clave-valor. location_id nulo = ajuste global. La unicidad de
 * (group, key, location_id) se garantiza en Settings::set().
 */
#[Fillable(['location_id', 'group', 'key', 'value'])]
class Setting extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('settings')
            ->logOnly(['location_id', 'group', 'key', 'value'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
