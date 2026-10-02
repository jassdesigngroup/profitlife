<?php

namespace App\Domain\Locations\Models;

use App\Domain\Locations\Enums\DayOfWeek;
use Database\Factories\LocationHourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Franja de atención. Varias filas por día permiten jornada partida.
 * Las horas son locales de la sede (columna `locations.timezone`).
 */
#[Fillable(['location_id', 'day_of_week', 'opens_at', 'closes_at'])]
#[UseFactory(LocationHourFactory::class)]
#[WithoutTimestamps]
class LocationHour extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
