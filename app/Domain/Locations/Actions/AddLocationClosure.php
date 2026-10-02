<?php

namespace App\Domain\Locations\Actions;

use App\Domain\Locations\Models\LocationClosure;
use Illuminate\Validation\ValidationException;

class AddLocationClosure
{
    /**
     * @param  int|null  $locationId  nulo = cierre de todas las sedes
     */
    public function execute(?int $locationId, string $closedOn, ?string $reason): LocationClosure
    {
        $exists = LocationClosure::query()
            ->where('location_id', $locationId)
            ->whereDate('closed_on', $closedOn)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['closedOn' => 'Ya existe un cierre para esa fecha.']);
        }

        return LocationClosure::query()->create([
            'location_id' => $locationId,
            'closed_on' => $closedOn,
            'reason' => $reason,
        ]);
    }
}
