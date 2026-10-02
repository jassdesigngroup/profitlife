<?php

namespace App\Domain\Staff\Models;

use App\Domain\Locations\Enums\DayOfWeek;
use App\Domain\Locations\Models\Location;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Franja semanal en la que un profesional atiende en una sede (hora local
 * de la sede). `valid_from`/`valid_until` acotan franjas temporales.
 */
#[Fillable(['staff_id', 'location_id', 'day_of_week', 'starts_at', 'ends_at', 'valid_from', 'valid_until'])]
class StaffSchedule extends Model
{
    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class)->withoutGlobalScopes();
    }

    /**
     * Franjas vigentes en una fecha local (Y-m-d).
     *
     * @param  Builder<static>  $query
     */
    public function scopeOnDate(Builder $query, string $date): void
    {
        $query->where('day_of_week', (int) date('N', strtotime($date)))
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $date))
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date));
    }
}
