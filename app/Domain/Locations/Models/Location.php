<?php

namespace App\Domain\Locations\Models;

use App\Domain\Locations\Policies\LocationPolicy;
use App\Domain\Staff\Models\LocationStaff;
use App\Domain\Staff\Models\Staff;
use App\Support\Concerns\HasLocationScope;
use Database\Factories\LocationFactory;
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

#[Fillable(['name', 'slug', 'code', 'address_line', 'city', 'department', 'phone', 'email', 'timezone', 'is_active'])]
#[UseFactory(LocationFactory::class)]
#[UsePolicy(LocationPolicy::class)]
class Location extends Model
{
    use HasFactory, HasLocationScope, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * En la tabla de sedes el alcance se aplica sobre su propio id.
     */
    public function locationScopeColumn(): string
    {
        return 'id';
    }

    /**
     * @return HasMany<LocationHour, $this>
     */
    public function hours(): HasMany
    {
        return $this->hasMany(LocationHour::class)->orderBy('day_of_week')->orderBy('opens_at');
    }

    /**
     * Cierres propios de la sede (sin los globales).
     *
     * @return HasMany<LocationClosure, $this>
     */
    public function closures(): HasMany
    {
        return $this->hasMany(LocationClosure::class)->orderBy('closed_on');
    }

    /**
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * @return BelongsToMany<Staff, $this>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class)
            ->using(LocationStaff::class)
            ->withPivot(['id', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('locations')
            ->logOnly(['name', 'slug', 'code', 'address_line', 'city', 'department', 'phone', 'email', 'timezone', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
