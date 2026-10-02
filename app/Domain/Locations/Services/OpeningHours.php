<?php

namespace App\Domain\Locations\Services;

use App\Domain\Locations\Models\Location;
use App\Domain\Locations\Models\LocationClosure;
use Carbon\CarbonInterface;

/**
 * Indica si una sede está abierta en un instante, según sus franjas
 * (hora local de la sede) y los cierres propios o globales.
 */
class OpeningHours
{
    public function isOpenAt(Location $location, CarbonInterface $instant): bool
    {
        $local = $instant->copy()->setTimezone($location->timezone ?: 'UTC');

        $closed = LocationClosure::query()
            ->affecting($location->id)
            ->whereDate('closed_on', $local->format('Y-m-d'))
            ->exists();

        if ($closed) {
            return false;
        }

        $hours = $location->hours()->get();

        // Sin horario configurado no hay contra qué comparar.
        if ($hours->isEmpty()) {
            return true;
        }

        $time = $local->format('H:i:s');

        return $hours
            ->filter(fn ($h) => $h->day_of_week->value === (int) $local->format('N'))
            ->contains(fn ($h) => $time >= $h->opens_at && $time < $h->closes_at);
    }
}
