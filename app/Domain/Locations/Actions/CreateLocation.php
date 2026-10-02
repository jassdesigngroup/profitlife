<?php

namespace App\Domain\Locations\Actions;

use App\Domain\Locations\DTOs\LocationData;
use App\Domain\Locations\Models\Location;

class CreateLocation
{
    public function execute(LocationData $data): Location
    {
        return Location::query()->create($data->toAttributes() + ['is_active' => true]);
    }
}
