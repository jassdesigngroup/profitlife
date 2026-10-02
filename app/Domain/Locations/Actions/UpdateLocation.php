<?php

namespace App\Domain\Locations\Actions;

use App\Domain\Locations\DTOs\LocationData;
use App\Domain\Locations\Models\Location;

class UpdateLocation
{
    public function execute(Location $location, LocationData $data): Location
    {
        $location->update($data->toAttributes());

        return $location;
    }
}
