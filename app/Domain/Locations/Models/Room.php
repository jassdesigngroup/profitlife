<?php

namespace App\Domain\Locations\Models;

use App\Domain\Locations\Enums\RoomType;
use App\Domain\Locations\Policies\RoomPolicy;
use App\Support\Concerns\HasLocationScope;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['location_id', 'name', 'type', 'capacity', 'is_active'])]
#[UseFactory(RoomFactory::class)]
#[UsePolicy(RoomPolicy::class)]
class Room extends Model
{
    use HasFactory, HasLocationScope, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => RoomType::class,
            'capacity' => 'integer',
            'is_active' => 'boolean',
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
            ->useLogName('locations')
            ->logOnly(['location_id', 'name', 'type', 'capacity', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
